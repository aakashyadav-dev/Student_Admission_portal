<?php
/** Included first by every page: config, secure session, security headers, helpers. */
declare(strict_types=1);

define('PORTAL_APP', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/repository.php';

/** Start the session with hardened cookie settings. API/file endpoints may skip this. */
function start_secure_session(): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('ADMINSESSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/* style-src allows inline style="" attributes (dashboard bar widths). Scripts are still restricted to our own files. */
function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; style-src-attr 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");
}

/** Log the real error, show a friendly one. Never leaks SQL or file paths to the browser. */
function fail_gracefully(Throwable $e): never
{
    error_log('[student-portal] ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    $page_title = 'Something went wrong';
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Error</title>'
       . '<link rel="stylesheet" href="' . e(url('assets/css/portal.css')) . '"></head><body><main class="page container">'
       . '<div class="card"><h1>Something went wrong</h1><p>The request could not be completed. '
       . 'Check that MySQL is running and that config/portal.properties is correct, then try again.</p>'
       . '<p><a class="btn" href="' . e(url('index.php')) . '">Back to dashboard</a></p></div></main></body></html>';
    exit;
}

if (defined('PORTAL_API')) {
    // JSON endpoints (api/register.php): no session cookie, errors reported as JSON.
    set_exception_handler(static function (Throwable $e): void {
        error_log('[student-portal] ' . get_class($e) . ': ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'The server could not save your registration. Please try again later.']);
        exit;
    });
    header('X-Content-Type-Options: nosniff');
} else {
    set_exception_handler('fail_gracefully');
    send_security_headers();
    start_secure_session();
}
