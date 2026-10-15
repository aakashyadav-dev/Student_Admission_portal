<?php
/** CREATE: add a student and (optionally) their documents in one transaction. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$pdo = db();
$courses = courses_active($pdo);
$values = ['status' => 'active', 'year_of_study' => 1];
$errors = [];

if (is_post()) {
    if (post_too_large()) {
        $errors['_form'] = 'The upload is larger than the server allows. Use smaller files (see upload_max_filesize in SETUP_GUIDE.md).';
    } else {
        csrf_verify();
        $values = $_POST;
        [$errors, $clean] = validate_student($pdo, $_POST);
        [$uploadErrors, $valid] = process_uploads(FORM_UPLOAD_SLOTS);

        if (!$errors && !$uploadErrors) {
            $written = [];
            $newId = 0;
            try {
                $pdo->beginTransaction();               // student row + file rows succeed or fail together
                $newId = student_insert($pdo, $clean);
                persist_uploads($pdo, $newId, $valid, $written);
                $pdo->commit();
                flash('ok', 'Student added.');
                redirect('students/view.php?id=' . $newId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($written as $path) {          // do not leave orphan files behind
                    delete_stored_file($path);
                }
                if ($newId) {
                    remove_student_dir($newId);
                }
                if ($e instanceof PDOException && $e->getCode() === '23000') {   // unique key race
                    $errors['roll_number'] = 'This roll number or email is already registered.';
                } else {
                    error_log('[student-portal] add failed: ' . $e->getMessage());
                    $errors['_form'] = 'The student could not be saved. Nothing was changed.';
                }
            }
        } elseif ($uploadErrors) {
            $errors['_files'] = $uploadErrors;
        }
    }
}

$page_title = 'Add student';
$nav = 'add';
$isEdit = false;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Add student</h1><p>Create a record and attach documents.</p></div></div>
<div class="card">
  <?php if (!empty($errors['_form'])): ?><div class="alert alert-err" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>
  <?php if (!empty($errors['_files'])): ?><div class="alert alert-err" role="alert">Some files were rejected:<ul><?php foreach ($errors['_files'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if ($errors && empty($errors['_form']) && empty($errors['_files'])): ?><div class="alert alert-err" role="alert">Fix the highlighted fields and try again.</div><?php endif; ?>
  <?php require __DIR__ . '/_form.php'; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
