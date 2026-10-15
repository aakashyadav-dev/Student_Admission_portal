<?php
/** CSRF protection: a random per-session token that every state-changing POST must send back. */
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Hidden input to place inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Abort with 419 when the token is missing or wrong. Call at the top of every POST handler. */
function csrf_verify(): void
{
    $sent = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Your session expired or the request was not valid. Go back, reload the page and try again.');
    }
}
