<?php
/** All database access for students and files. Every query is a prepared statement. */
declare(strict_types=1);

const STUDENT_SELECT = 'SELECT s.*, c.code AS course_code, c.name AS course_name FROM students s JOIN courses c ON c.id = s.course_id';

function courses_active(PDO $pdo): array
{
    return $pdo->query('SELECT id, code, name FROM courses WHERE is_active = 1 ORDER BY name')->fetchAll();
}

function student_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(STUDENT_SELECT . ' WHERE s.id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

/** Search + filter + paginate. Returns [rows, totalCount]. */
function students_search(PDO $pdo, string $q, string $status, int $courseId, int $page, int $perPage): array
{
    $where = [];
    $params = [];
    if ($q !== '') {
        // Distinct placeholder names: with real prepared statements a name may only appear once.
        $where[] = '(s.full_name LIKE :q1 OR s.roll_number LIKE :q2 OR s.email LIKE :q3)';
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
    }
    if (in_array($status, STATUSES, true)) {
        $where[] = 's.status = :status';
        $params[':status'] = $status;
    }
    if ($courseId > 0) {
        $where[] = 's.course_id = :course';
        $params[':course'] = $courseId;
    }
    $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $count = $pdo->prepare('SELECT COUNT(*) FROM students s' . $clause);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $offset = max(0, ($page - 1) * $perPage);
    $sql = 'SELECT s.id, s.roll_number, s.full_name, s.email, s.status, s.year_of_study, s.created_at,
                   c.code AS course_code,
                   (SELECT f.id FROM student_files f WHERE f.student_id = s.id AND f.category = \'profile_image\'
                      ORDER BY f.id DESC LIMIT 1) AS photo_id,
                   (SELECT COUNT(*) FROM student_files f WHERE f.student_id = s.id) AS file_count
            FROM students s JOIN courses c ON c.id = s.course_id' . $clause
         . ' ORDER BY s.created_at DESC, s.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset; // ints are cast, so safe
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return [$stmt->fetchAll(), $total];
}

function student_files(PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare('SELECT * FROM student_files WHERE student_id = :id ORDER BY category, uploaded_at DESC, id DESC');
    $stmt->execute([':id' => $studentId]);
    return $stmt->fetchAll();
}

function file_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM student_files WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function student_insert(PDO $pdo, array $c, ?string $passwordHash = null): int
{
    $stmt = $pdo->prepare('INSERT INTO students (roll_number, full_name, email, phone, date_of_birth, gender, course_id, year_of_study, address, status, password_hash)
        VALUES (:roll, :name, :email, :phone, :dob, :gender, :course, :year, :address, :status, :pw)');
    $stmt->execute([
        ':roll' => $c['roll_number'], ':name' => $c['full_name'], ':email' => $c['email'], ':phone' => $c['phone'],
        ':dob' => $c['date_of_birth'], ':gender' => $c['gender'], ':course' => $c['course_id'], ':year' => $c['year_of_study'],
        ':address' => $c['address'], ':status' => $c['status'], ':pw' => $passwordHash,
    ]);
    return (int) $pdo->lastInsertId();
}

function student_update(PDO $pdo, int $id, array $c): void
{
    $stmt = $pdo->prepare('UPDATE students SET roll_number = :roll, full_name = :name, email = :email, phone = :phone, date_of_birth = :dob,
        gender = :gender, course_id = :course, year_of_study = :year, address = :address, status = :status WHERE id = :id');
    $stmt->execute([
        ':roll' => $c['roll_number'], ':name' => $c['full_name'], ':email' => $c['email'], ':phone' => $c['phone'],
        ':dob' => $c['date_of_birth'], ':gender' => $c['gender'], ':course' => $c['course_id'], ':year' => $c['year_of_study'],
        ':address' => $c['address'], ':status' => $c['status'], ':id' => $id,
    ]);
}

function file_insert(PDO $pdo, int $studentId, array $m): int
{
    $stmt = $pdo->prepare('INSERT INTO student_files (student_id, category, original_name, stored_name, relative_path, mime_type, file_size)
        VALUES (:sid, :cat, :orig, :stored, :rel, :mime, :size)');
    $stmt->execute([':sid' => $studentId, ':cat' => $m['category'], ':orig' => $m['original_name'], ':stored' => $m['stored_name'],
        ':rel' => $m['relative_path'], ':mime' => $m['mime_type'], ':size' => $m['file_size']]);
    return (int) $pdo->lastInsertId();
}

/**
 * STEP 1 of handling uploads: validate every file sent with a form. Nothing is written yet.
 *
 * @param array $slots  map of form field => category, e.g. ['profile_image' => 'profile_image', 'certificates' => 'certificate']
 * @return array [string[] $errors, array $valid]  $valid is passed on to persist_uploads()
 */
function process_uploads(array $slots): array
{
    $errors = [];
    $valid = [];
    foreach ($slots as $field => $category) {
        $files = collect_files($field);
        if ($category === 'profile_image' && count($files) > 1) {
            $errors[] = 'Upload only one profile image.';
            continue;
        }
        foreach ($files as $file) {
            [$err, $info] = validate_upload($file, $category);
            if ($err !== '') {
                $errors[] = $err;
            } else {
                $valid[] = [$category, $info];
            }
        }
    }
    return [$errors, $valid];
}

/**
 * STEP 2: write the validated files to disk and record their metadata. Call inside a transaction.
 * A new profile image replaces the old one (one per student).
 *
 * @param array $stored  (by reference) filled with the paths written so far, so the caller can delete
 *                       them if the transaction fails part-way
 * @return array old file paths that were replaced; delete them AFTER the transaction commits
 */
function persist_uploads(PDO $pdo, int $studentId, array $valid, array &$stored): array
{
    $stored = [];
    $replaced = [];
    foreach ($valid as [$category, $info]) {
        if ($category === 'profile_image') {
            $old = $pdo->prepare("SELECT id, relative_path FROM student_files WHERE student_id = :sid AND category = 'profile_image'");
            $old->execute([':sid' => $studentId]);
            foreach ($old->fetchAll() as $row) {
                $pdo->prepare('DELETE FROM student_files WHERE id = :id')->execute([':id' => $row['id']]);
                $replaced[] = $row['relative_path'];
            }
        }
        $meta = store_upload($info, $studentId, $category);
        $stored[] = $meta['relative_path'];
        file_insert($pdo, $studentId, $meta);
    }
    return $replaced;
}

/** Remove files written during a request that later failed (their database rows were rolled back). */
function discard_files(array $relativePaths): void
{
    foreach ($relativePaths as $rel) {
        delete_stored_file($rel);
    }
}

/** Delete one file row + file on disk. Uses the DB id, never a path from the request. */
function file_delete(PDO $pdo, array $row): void
{
    $pdo->prepare('DELETE FROM student_files WHERE id = :id')->execute([':id' => $row['id']]);
    delete_stored_file($row['relative_path']);
}

/** Delete a student, their file rows (FK cascade) and their files on disk. */
function student_delete(PDO $pdo, int $id): void
{
    $files = student_files($pdo, $id);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM students WHERE id = :id')->execute([':id' => $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    // Remove bytes only after the database delete succeeded.
    foreach ($files as $f) {
        delete_stored_file($f['relative_path']);
    }
    remove_student_dir($id);
}

function dashboard_stats(PDO $pdo): array
{
    $row = $pdo->query("SELECT COUNT(*) AS total, SUM(status = 'active') AS active, SUM(status = 'pending') AS pending FROM students")->fetch();
    $files = (int) $pdo->query('SELECT COUNT(*) FROM student_files')->fetchColumn();
    $perCourse = $pdo->query('SELECT c.code, COUNT(s.id) AS n FROM courses c LEFT JOIN students s ON s.course_id = c.id GROUP BY c.id, c.code ORDER BY n DESC, c.code')->fetchAll();
    $recent = $pdo->query('SELECT s.id, s.full_name, s.roll_number, s.status, s.created_at, c.code AS course_code FROM students s JOIN courses c ON c.id = s.course_id ORDER BY s.created_at DESC, s.id DESC LIMIT 5')->fetchAll();
    return ['total' => (int) $row['total'], 'active' => (int) $row['active'], 'pending' => (int) $row['pending'], 'files' => $files, 'per_course' => $perCourse, 'recent' => $recent];
}
