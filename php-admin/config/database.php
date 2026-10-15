<?php
/** PDO connection factory. Uses prepared statements everywhere (native, not emulated). */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            cfg('DB_HOST', '127.0.0.1'), cfg('DB_PORT', '3306'), cfg('DB_NAME', 'student_portal'));
        $pdo = new PDO($dsn, cfg('DB_USER', 'root'), cfg('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // errors become exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                    // real server-side prepared statements
        ]);
    }
    return $pdo;
}
