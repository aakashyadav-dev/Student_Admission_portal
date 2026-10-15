<?php
declare(strict_types=1);

/** Escape for HTML output (XSS protection). Use on EVERY value printed into a page. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a URL inside the admin panel (honours APP_BASE_PATH). */
function url(string $path = ''): string
{
    return rtrim((string) cfg('APP_BASE_PATH', ''), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** One-time messages shown after a redirect. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    return $bytes < 1048576 ? round($bytes / 1024, 1) . ' KB' : round($bytes / 1048576, 1) . ' MB';
}

function category_label(string $category): string
{
    return [
        'profile_image' => 'Profile image',
        'id_proof'      => 'ID proof',
        'certificate'   => 'Certificate',
        'other'         => 'Other document',
    ][$category] ?? 'Document';
}

function initials(string $name): string
{
    $out = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $out;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Read an integer id from the query string; returns null when missing or invalid. */
function query_id(string $key = 'id'): ?int
{
    $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $v === false || $v === null ? null : $v;
}

/** Render a friendly error page (404, 400 ...) inside the normal layout and stop. */
function abort_page(int $code, string $message): never
{
    http_response_code($code);
    $page_title = 'Error';
    require __DIR__ . '/header.php';
    echo '<div class="card"><h1>' . e($message) . '</h1><p><a class="btn" href="' . e(url('students/index.php')) . '">Back to students</a></p></div>';
    require __DIR__ . '/footer.php';
    exit;
}
