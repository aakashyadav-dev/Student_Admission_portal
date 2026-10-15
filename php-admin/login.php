<?php
/** Admin sign-in. Brute-force slow-down + session fixation protection + safe redirect. */
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
if (is_post()) {
    csrf_verify();
    $now = time();
    $fails = array_filter($_SESSION['login_fails'] ?? [], static fn ($t) => $now - $t < 300);   // failures in the last 5 minutes
    if (count($fails) >= 5) {
        $error = 'Too many failed attempts. Wait a few minutes and try again.';
    } else {
        $row = authenticate(db(), trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
        if ($row) {
            login_user(db(), $row);
            $next = (string) ($_GET['next'] ?? '');
            // Only follow local paths ("/x"), never "//evil.com" or "http://..." (open-redirect protection).
            $local = $next !== '' && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '\\');
            header('Location: ' . ($local ? $next : url('index.php')));
            exit;
        }
        $fails[] = $now;
        $error = 'Wrong username or password.';   // same message for both cases on purpose
    }
    $_SESSION['login_fails'] = array_values($fails);
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | Student Admission Portal</title>
  <link rel="icon" href="<?= e(url('assets/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(url('assets/css/portal.css')) ?>">
</head>
<body>
<main class="auth-wrap">
  <div class="card auth-card">
    <div class="brand"><img class="brand-mark" src="<?= e(url('assets/logo.svg')) ?>" alt=""> Student Admission Portal</div>
    <h1>Admin sign in</h1>
    <p class="muted">Staff accounts only. This sign-in is separate from the Java student-profile portal.</p>
    <?php if ($error): ?><div class="alert alert-err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>"></div>
      <div class="field"><label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required></div>
      <button class="btn" type="submit" style="width:100%">Sign in</button>
    </form>
  </div>
</main>
</body>
</html>
