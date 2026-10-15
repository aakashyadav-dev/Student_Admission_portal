<?php
/** DELETE: GET shows a confirmation page, POST (with CSRF token) performs the delete. Admin role only. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_admin();

$pdo = db();
$id = query_id();
$student = $id ? student_find($pdo, $id) : null;
if (!$student) {
    abort_page(404, 'Student not found.');
}

if (is_post()) {
    csrf_verify();
    student_delete($pdo, (int) $student['id']);   // removes DB rows, then the files, using paths from OUR table
    flash('ok', 'Deleted ' . $student['full_name'] . ' and their files.');
    redirect('students/index.php');
}

$fileCount = count(student_files($pdo, (int) $student['id']));
$page_title = 'Delete student';
$nav = 'students';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:600px">
  <h1>Delete this student?</h1>
  <p><strong><?= e($student['full_name']) ?></strong> (<?= e($student['roll_number']) ?>) and <?= $fileCount ?> uploaded file<?= $fileCount === 1 ? '' : 's' ?> will be permanently removed. This cannot be undone.</p>
  <form method="post" class="btn-row"><?= csrf_field() ?>
    <button class="btn btn-danger" type="submit">Yes, delete permanently</button>
    <a class="btn btn-ghost" href="<?= e(url('students/view.php?id=' . (int) $student['id'])) ?>">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
