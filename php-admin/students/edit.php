<?php
/** UPDATE: edit details, add documents, and remove individual documents. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$pdo = db();
$id = query_id();
$student = $id ? student_find($pdo, $id) : null;
if (!$student) {
    abort_page(404, 'Student not found.');
}
$courses = courses_active($pdo);
$values = $student;
$errors = [];

if (is_post()) {
    if (post_too_large()) {
        $errors['_form'] = 'The upload is larger than the server allows. Use smaller files.';
    } else {
        csrf_verify();
        $values = $_POST + $student;
        [$errors, $clean] = validate_student($pdo, $_POST, $id);   // $id: own roll number is not a duplicate
        [$uploadErrors, $valid] = process_uploads(FORM_UPLOAD_SLOTS);

        if (!$errors && !$uploadErrors) {
            $written = [];
            $replaced = [];
            try {
                $pdo->beginTransaction();
                student_update($pdo, $id, $clean);
                $replaced = persist_uploads($pdo, $id, $valid, $written);
                $pdo->commit();
                foreach ($replaced as $old) {           // remove superseded profile image bytes after commit
                    delete_stored_file($old);
                }
                flash('ok', 'Changes saved.');
                redirect('students/view.php?id=' . $id);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($written as $path) {
                    delete_stored_file($path);
                }
                if ($e instanceof PDOException && $e->getCode() === '23000') {
                    $errors['roll_number'] = 'This roll number or email is already registered.';
                } else {
                    error_log('[student-portal] edit failed: ' . $e->getMessage());
                    $errors['_form'] = 'The changes could not be saved.';
                }
            }
        } elseif ($uploadErrors) {
            $errors['_files'] = $uploadErrors;
        }
    }
}

$files = student_files($pdo, $id);
$page_title = 'Edit ' . $student['full_name'];
$nav = 'students';
$isEdit = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Edit student</h1><p><?= e($student['full_name']) ?> &middot; <?= e($student['roll_number']) ?></p></div>
  <a class="btn btn-ghost" href="<?= e(url('students/view.php?id=' . $id)) ?>">Back to profile</a></div>
<div class="card">
  <?php if (!empty($errors['_form'])): ?><div class="alert alert-err" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>
  <?php if (!empty($errors['_files'])): ?><div class="alert alert-err" role="alert">Some files were rejected:<ul><?php foreach ($errors['_files'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if ($errors && empty($errors['_form']) && empty($errors['_files'])): ?><div class="alert alert-err" role="alert">Fix the highlighted fields and try again.</div><?php endif; ?>
  <?php require __DIR__ . '/_form.php'; ?>
</div>

<!-- Separate forms (HTML forbids nesting forms) so each file can be removed on its own. -->
<section class="card" aria-labelledby="files-h">
  <h2 id="files-h">Stored files</h2>
  <?php if (!$files): ?>
    <div class="empty"><h3>No files yet</h3><p>Use the upload fields above to add a profile image or documents.</p></div>
  <?php else: ?>
    <ul class="file-list">
    <?php foreach ($files as $f): ?>
      <li>
        <?php if (str_starts_with($f['mime_type'], 'image/')): ?><img src="<?= e(url('students/file.php?id=' . (int) $f['id'])) ?>" alt="<?= e(category_label($f['category'])) ?> preview" loading="lazy">
        <?php else: ?><span class="file-icon">PDF</span><?php endif; ?>
        <div class="meta"><strong><?= e(category_label($f['category'])) ?></strong>: <?= e($f['original_name']) ?>
          <small><?= e($f['mime_type']) ?> &middot; <?= e(format_bytes((int) $f['file_size'])) ?> &middot; <?= e($f['uploaded_at']) ?></small></div>
        <form method="post" action="<?= e(url('students/delete_file.php?id=' . (int) $f['id'])) ?>"><?= csrf_field() ?>
          <button class="btn btn-danger btn-sm" type="submit">Remove file</button></form>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
