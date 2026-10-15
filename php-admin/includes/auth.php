<?php
/**
 * Authentication for the PHP admin panel.
 * - Passwords are stored as PBKDF2-SHA256 ("pbkdf2_sha256$iterations$salt$hash"), the same format
 *   the Java portal verifies, so one `users` table can serve both applications.
 * - The PHP session (cookie ADMINSESSID) is completely separate from the Java session (JSESSIONID):
 *   signing in to one application does NOT sign you in to the other.
 */
declare(strict_types=1);

const PBKDF2_ITERATIONS = 120000;

function hash_password(string $password): string
{
    $salt = random_bytes(16);
    $hash = hash_pbkdf2('sha256', $password, $salt, PBKDF2_ITERATIONS, 32, true);
    return sprintf('pbkdf2_sha256$%d$%s$%s', PBKDF2_ITERATIONS, base64_encode($salt), base64_encode($hash));
}

function verify_password(string $password, string $stored): bool
{
    $parts = explode('$', $stored);
    if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256') {
        return false;
    }
    $salt = base64_decode($parts[2], true);
    $expected = base64_decode($parts[3], true);
    $iterations = (int) $parts[1];
    if ($salt === false || $expected === false || $iterations < 1000) {
        return false;
    }
    $actual = hash_pbkdf2('sha256', $password, $salt, $iterations, strlen($expected), true);
    return hash_equals($expected, $actual);   // constant-time comparison
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Gatekeeper placed at the top of every protected page. */
function require_login(): array
{
    $user = current_user();
    $idleLimit = 1800; // 30 minutes of inactivity logs the user out
    if ($user === null || (time() - ($_SESSION['last_seen'] ?? 0)) > $idleLimit) {
        $_SESSION = [];
        $next = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . url('login.php') . '?next=' . rawurlencode($next));
        exit;
    }
    $_SESSION['last_seen'] = time();
    return $user;
}

function require_admin(): void
{
    if ((current_user()['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Only administrators can do this.');
    }
}

/** Returns the user row on success, or null. Always spends similar time for unknown users. */
function authenticate(PDO $pdo, string $username, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, full_name, password_hash, role FROM users WHERE username = :u AND is_active = 1');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    // Verify against a dummy hash when the user does not exist, so timing does not reveal valid usernames.
    $dummy = 'pbkdf2_sha256$120000$AAAAAAAAAAAAAAAAAAAAAA==$AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
    $ok = verify_password($password, $row['password_hash'] ?? $dummy);
    return ($row && $ok) ? $row : null;
}

function login_user(PDO $pdo, array $row): void
{
    session_regenerate_id(true);               // prevents session fixation
    $_SESSION = [
        'user' => ['id' => (int) $row['id'], 'username' => $row['username'], 'full_name' => $row['full_name'], 'role' => $row['role']],
        'last_seen' => time(),
    ];
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => $row['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}
