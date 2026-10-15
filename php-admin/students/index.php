<?php
/** READ (list): searchable, filterable, paginated table of students. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$courseId = (int) ($_GET['course'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$pdo = db();
[$rows, $total] = students_search($pdo, $q, $status, $courseId, $page, $perPage);
$pages = max(1, (int) ceil($total / $perPage));
$courses = courses_active($pdo);
$isAdmin = (current_user()['role'] ?? '') === 'admin';

// Build links that keep the current filters.
$link = static fn (int $p) => url('students/index.php?' . http_build_query(array_filter(['q' => $q, 'status' => $status, 'course' => $courseId ?: null, 'page' => $p])));

$page_title = 'Students';
$nav = 'students';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1>Students</h1><p><?= $total ?> record<?= $total === 1 ? '' : 's' ?> found</p></div>
  <a class="btn" href="<?= e(url('students/add.php')) ?>">Add student</a>
</div>

<form class="toolbar" method="get" role="search">
  <label class="sr-only" for="q">Search by name, roll number or email</label>
  <input type="search" id="q" name="q" placeholder="Search by name, roll number or email" value="<?= e($q) ?>">
  <label class="sr-only" for="course">Course</label>
  <select id="course" name="course"><option value="0">All courses</option>
    <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $courseId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['code']) ?></option><?php endforeach; ?></select>
  <label class="sr-only" for="status">Status</label>
  <select id="status" name="status"><option value="">All statuses</option>
    <?php foreach (STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select>
  <button class="btn btn-ghost" type="submit">Filter</button>
</form>

<?php if (!$rows): ?>
  <div class="empty"><h3>No students match</h3><p>Try a different search, or add a new student.</p>
    <a class="btn" href="<?= e(url('students/add.php')) ?>">Add student</a></div>
<?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th></th><th>Name</th><th>Roll number</th><th>Course</th><th>Year</th><th>Status</th><th>Files</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?php if ($r['photo_id']): ?><img class="thumb" src="<?= e(url('students/file.php?id=' . (int) $r['photo_id'])) ?>" alt="" loading="lazy">
            <?php else: ?><span class="thumb" style="display:grid;place-items:center;font-weight:700"><?= e(initials($r['full_name'])) ?></span><?php endif; ?></td>
        <td><a href="<?= e(url('students/view.php?id=' . (int) $r['id'])) ?>"><?= e($r['full_name']) ?></a><br><small class="muted"><?= e($r['email']) ?></small></td>
        <td><?= e($r['roll_number']) ?></td>
        <td><?= e($r['course_code']) ?></td>
        <td><?= (int) $r['year_of_study'] ?></td>
        <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><?= (int) $r['file_count'] ?></td>
        <td class="actions">
          <a class="btn btn-ghost btn-sm" href="<?= e(url('students/view.php?id=' . (int) $r['id'])) ?>">View</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('students/edit.php?id=' . (int) $r['id'])) ?>">Edit</a>
          <?php if ($isAdmin): ?><a class="btn btn-danger btn-sm" href="<?= e(url('students/delete.php?id=' . (int) $r['id'])) ?>">Delete</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>

  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
      <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>">Previous</a><?php endif; ?>
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <?php if ($p === $page): ?><span class="current" aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= e($link($p)) ?>"><?= $p ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>">Next</a><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
