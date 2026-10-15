<?php
/** Sign out. POST only (with CSRF token) so another site cannot log you out by embedding an image. */
require __DIR__ . '/includes/bootstrap.php';
if (is_post()) {
    csrf_verify();
    logout_user();
}
header('Location: ' . url('login.php'));
exit;
