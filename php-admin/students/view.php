<?php
/** READ (detail): full profile with image previews and download links for every uploaded file. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$pdo = db();
$id = query_id();
$student = $id ? student_find($pdo, $id) : null;
if (!$student) {
    abort_page(404, 'Student not found.');
}
$files = student_files($pdo, $id);
$photo = null;
foreach ($files as $f) {
    if ($f['category'] === 'profile_image') { $photo = $f; break; }
}
$isAdmin = (current_user()['role'] ?? '') === 'admin';

$page_title = $student['full_name'];
$nav = 'students';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1>Student profile</h1><p>Record #<?= (int) $student['id'] ?></p></div>
  <div class="btn-row">
    <a class="btn btn-ghost" href="<?= e(url('students/index.php')) ?>">All students</a>
    <a class="btn" href="<?= e(url('students/edit.php?id=' . $id)) ?>">Edit</a>
    <?php if ($isAdmin): ?><a class="btn btn-danger" href="<?= e(url('students/delete.php?id=' . $id)) ?>">Delete</a><?php endif; ?>
  </div>
</div>

<section class="card">
  <div class="profile-head">
    <?php if ($photo): ?><img class="avatar" src="<?= e(url('students/file.php?id=' . (int) $photo['id'])) ?>" alt="Profile photo of <?= e($student['full_name']) ?>">
    <?php else: ?><div class="avatar-fallback" aria-hidden="true"><?= e(initials($student['full_name'])) ?></div><?php endif; ?>
    <div><h2><?= e($student['full_name']) ?></h2>
      <p class="muted"><?= e($student['roll_number']) ?> &middot; <?= e($student['course_code']) ?> &middot; Year <?= (int) $student['year_of_study'] ?></p>
      <span class="badge badge-<?= e($student['status']) ?>"><?= e($student['status']) ?></span></div>
  </div>
  <hr style="border:0;border-top:1px solid var(--line);margin:1.25rem 0">
  <dl class="dl">
    <dt>Email</dt><dd><?= e($student['email']) ?></dd>
    <dt>Phone</dt><dd><?= e($student['phone'] ?? '') ?: '<span class="muted">Not provided</span>' ?></dd>
    <dt>Course</dt><dd><?= e($student['course_name']) ?></dd>
    <dt>Date of birth</dt><dd><?= e($student['date_of_birth'] ?? '') ?: '<span class="muted">Not provided</span>' ?></dd>
    <dt>Gender</dt><dd><?= $student['gender'] ? e(ucfirst($student['gender'])) : '<span class="muted">Not provided</span>' ?></dd>
    <dt>Address</dt><dd><?= $student['address'] ? nl2br(e($student['address'])) : '<span class="muted">Not provided</span>' ?></dd>
    <dt>Registered</dt><dd><?= e($student['created_at']) ?></dd>
    <dt>Last updated</dt><dd><?= e($student['updated_at']) ?></dd>
  </dl>
</section>

<section class="card" aria-labelledby="files-h">
  <h2 id="files-h">Uploaded files (<?= count($files) ?>)</h2>
  <?php if (!$files): ?>
    <div class="empty"><h3>No files uploaded</h3><p>Add documents from the edit page.</p>
      <a class="btn" href="<?= e(url('students/edit.php?id=' . $id)) ?>">Upload files</a></div>
  <?php else: ?>
    <ul class="file-list">
    <?php foreach ($files as $f):
        $isImage = str_starts_with($f['mime_type'], 'image/');
        $exists = safe_upload_path($f['relative_path']) !== null; ?>
      <li>
        <?php if ($isImage && $exists): ?><a href="<?= e(url('students/file.php?id=' . (int) $f['id'])) ?>"><img src="<?= e(url('students/file.php?id=' . (int) $f['id'])) ?>" alt="<?= e(category_label($f['category'])) ?>" loading="lazy"></a>
        <?php else: ?><span class="file-icon"><?= $isImage ? 'IMG' : 'PDF' ?></span><?php endif; ?>
        <div class="meta"><strong><?= e(category_label($f['category'])) ?></strong>: <?= e($f['original_name']) ?>
          <small><?= e($f['mime_type']) ?> &middot; <?= e(format_bytes((int) $f['file_size'])) ?> &middot; uploaded <?= e($f['uploaded_at']) ?></small></div>
        <?php if ($exists): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('students/file.php?id=' . (int) $f['id'] . '&download=1')) ?>">Download</a>
        <?php else: ?><span class="badge badge-pending">File missing from storage</span><?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
