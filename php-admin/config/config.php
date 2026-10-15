<?php
/**
 * Loads the SHARED configuration file (config/portal.properties) that the Java portal also reads.
 * Lookup order: environment variable PORTAL_CONFIG, then <project>/config/portal.properties.
 */
declare(strict_types=1);

/** Parse a KEY=VALUE properties file (# starts a comment). */
function load_properties(string $file): array
{
    $values = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $values[trim($key)] = trim($val);
    }
    return $values;
}

/** Absolute path of the active properties file. */
function config_file(): string
{
    $env = getenv('PORTAL_CONFIG');
    return ($env !== false && $env !== '') ? $env : dirname(__DIR__, 2) . '/config/portal.properties';
}

/** Read one setting, with an optional default. */
function cfg(string $key, ?string $default = null): ?string
{
    static $values = null;
    if ($values === null) {
        $file = config_file();
        if (!is_readable($file)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Configuration file not found: copy config/portal.properties.example to config/portal.properties "
                . "(or set the PORTAL_CONFIG environment variable) and edit it. See SETUP_GUIDE.md.\n");
        }
        $values = load_properties($file);
    }
    return $values[$key] ?? $default;
}

/** Absolute path of the upload directory shared with the Java portal (created if missing). */
function upload_root(): string
{
    $dir = cfg('UPLOAD_DIR', '../storage/uploads');
    // Relative paths are resolved against the folder holding portal.properties.
    if (!preg_match('~^([A-Za-z]:)?[\\\\/]~', $dir)) {
        $dir = dirname(config_file()) . '/' . $dir;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload directory could not be created.');
    }
    $real = realpath($dir);
    if ($real === false) {
        throw new RuntimeException('Upload directory is not accessible.');
    }
    return $real;
}

function max_upload_bytes(): int
{
    return max(1, (int) cfg('MAX_UPLOAD_MB', '5')) * 1024 * 1024;
}
