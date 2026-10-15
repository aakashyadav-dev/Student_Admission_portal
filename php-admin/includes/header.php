<?php
/** Page chrome. Expects $page_title and optionally $nav ('dashboard'|'students'|'add'). */
defined('PORTAL_APP') or exit;
$user = current_user();
$nav = $nav ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'Admin') ?> | Student Admission Portal</title>
  <link rel="icon" href="<?= e(url('assets/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(url('assets/css/portal.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar">
  <div class="container">
    <a class="brand" href="<?= e(url('index.php')) ?>"><img class="brand-mark" src="<?= e(url('assets/logo.svg')) ?>" alt=""> Student Admission Portal &middot; Admin</a>
    <?php if ($user): ?>
    <nav aria-label="Main">
      <a href="<?= e(url('index.php')) ?>"<?= $nav === 'dashboard' ? ' aria-current="page"' : '' ?>>Dashboard</a>
      <a href="<?= e(url('students/index.php')) ?>"<?= $nav === 'students' ? ' aria-current="page"' : '' ?>>Students</a>
      <a href="<?= e(url('students/add.php')) ?>"<?= $nav === 'add' ? ' aria-current="page"' : '' ?>>Add student</a>
      <span class="who"><?= e($user['full_name']) ?> (<?= e($user['role']) ?>)</span>
      <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button type="submit">Sign out</button></form>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main id="main" class="page container">
<?php foreach (flash_take() as $f): ?>
  <div class="alert alert-<?= $f['type'] === 'error' ? 'err' : 'ok' ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
