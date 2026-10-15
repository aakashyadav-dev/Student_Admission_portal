<?php
/** Dashboard: totals, students per course, newest registrations. */
require __DIR__ . '/includes/bootstrap.php';
require_login();

$stats = dashboard_stats(db());
$maxCourse = max(1, ...array_map(static fn ($r) => (int) $r['n'], $stats['per_course'] ?: [['n' => 1]]));
$page_title = 'Dashboard';
$nav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Dashboard</h1><p>Overview of admissions and uploaded documents.</p></div>
  <a class="btn" href="<?= e(url('students/add.php')) ?>">Add student</a>
</div>

<div class="stats">
  <div class="stat"><strong><?= $stats['total'] ?></strong><span>Students</span></div>
  <div class="stat"><strong><?= $stats['active'] ?></strong><span>Active</span></div>
  <div class="stat"><strong><?= $stats['pending'] ?></strong><span>Awaiting review</span></div>
  <div class="stat"><strong><?= $stats['files'] ?></strong><span>Files stored</span></div>
</div>

<div class="grid-2">
  <section class="card" aria-labelledby="by-course">
    <h2 id="by-course">Students by course</h2>
    <?php foreach ($stats['per_course'] as $row): ?>
      <div class="bar-row"><span><?= e($row['code']) ?></span>
        <div class="bar"><i style="width: <?= (int) round($row['n'] / $maxCourse * 100) ?>%"></i></div><span><?= (int) $row['n'] ?></span></div>
    <?php endforeach; ?>
  </section>
  <section class="card" aria-labelledby="recent">
    <h2 id="recent">Newest registrations</h2>
    <?php if (!$stats['recent']): ?>
      <div class="empty"><h3>No students yet</h3><p>Add a student or share the registration form.</p></div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Course</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($stats['recent'] as $r): ?>
          <tr><td><a href="<?= e(url('students/view.php?id=' . (int) $r['id'])) ?>"><?= e($r['full_name']) ?></a><br><small class="muted"><?= e($r['roll_number']) ?></small></td>
              <td><?= e($r['course_code']) ?></td><td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
