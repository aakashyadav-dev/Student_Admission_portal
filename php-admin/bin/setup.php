<?php
/**
 * Command-line helper for first-time setup.     Run from the project root:
 *
 *   php php-admin/bin/setup.php check                 verify PHP extensions, database connection, upload folder
 *   php php-admin/bin/setup.php install [--sample]    create the schema (WARNING: drops existing tables); --sample adds demo data + files
 *   php php-admin/bin/setup.php passwd <username>     set a new password for a staff account
 *
 * "install" needs a MySQL account allowed to CREATE DATABASE. Either put such an account in
 * config/portal.properties temporarily, or export PORTAL_DB_ADMIN_USER / PORTAL_DB_ADMIN_PASS for this command only.
 * (You can always import database/schema.sql with the mysql command instead; see SETUP_GUIDE.md.)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';

const ROOT = __DIR__ . '/../..';

function out(string $m): void { fwrite(STDOUT, $m . PHP_EOL); }
function bail(string $m): never { fwrite(STDERR, 'ERROR: ' . $m . PHP_EOL); exit(1); }

/** Read a line from the terminal; hide the typing for passwords where the OS supports it. */
function ask(string $prompt, bool $secret = false): string
{
    fwrite(STDOUT, $prompt);
    $canHide = $secret && DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
    if ($canHide) { shell_exec('stty -echo 2>/dev/null'); }
    $line = fgets(STDIN);
    if ($canHide) { shell_exec('stty echo 2>/dev/null'); fwrite(STDOUT, PHP_EOL); }
    return $line === false ? '' : rtrim($line, "\r\n");
}

/** Split a .sql file into statements. Understands -- comments and quoted strings containing semicolons. */
function split_sql(string $sql): array
{
    $statements = [];
    $buf = '';
    $inString = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if (!$inString && $c === '-' && ($sql[$i + 1] ?? '') === '-') {      // comment: skip to end of line
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            $buf .= "\n";
            continue;
        }
        if ($inString && $c === '\\') {                                       // backslash escape inside a string
            $buf .= $c . ($sql[$i + 1] ?? '');
            $i++;
            continue;
        }
        if ($c === "'") {
            if ($inString && ($sql[$i + 1] ?? '') === "'") {                  // '' is an escaped quote
                $buf .= "''";
                $i++;
                continue;
            }
            $inString = !$inString;
        }
        if ($c === ';' && !$inString) {
            if (trim($buf) !== '') { $statements[] = trim($buf); }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') { $statements[] = trim($buf); }
    return $statements;
}

/** Connection WITHOUT a default database (the schema file creates and selects it). */
function server_connection(): PDO
{
    $user = getenv('PORTAL_DB_ADMIN_USER') ?: cfg('DB_USER', 'root');
    $pass = getenv('PORTAL_DB_ADMIN_PASS');
    $pass = $pass === false ? cfg('DB_PASS', '') : $pass;
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', cfg('DB_HOST', '127.0.0.1'), cfg('DB_PORT', '3306'));
    return new PDO($dsn, $user, (string) $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function run_sql_file(PDO $pdo, string $file): int
{
    $sql = file_get_contents($file);
    if ($sql === false) { bail("Cannot read $file"); }
    $n = 0;
    foreach (split_sql($sql) as $statement) {
        $pdo->exec($statement);
        $n++;
    }
    return $n;
}

/** Copy storage/sample_uploads/<id>/<file> into the shared upload folder. */
function copy_sample_files(): int
{
    $src = realpath(ROOT . '/storage/sample_uploads');
    if ($src === false) { out('No storage/sample_uploads folder found; skipping sample files.'); return 0; }
    $dest = upload_root();
    $count = 0;
    foreach (glob($src . '/*/*') ?: [] as $file) {
        $dir = basename(dirname($file));
        $name = basename($file);
        if (!preg_match('/^[0-9]{1,10}$/', $dir) || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|pdf)$/', $name)) { continue; }
        if (!is_dir("$dest/$dir") && !mkdir("$dest/$dir", 0750, true)) { bail("Cannot create $dest/$dir"); }
        if (!copy($file, "$dest/$dir/$name")) { bail("Cannot copy $name"); }
        chmod("$dest/$dir/$name", 0640);
        $count++;
    }
    return $count;
}

$command = $argv[1] ?? 'help';
switch ($command) {
    case 'check':
        $ok = true;
        foreach (['pdo_mysql', 'fileinfo', 'mbstring'] as $ext) {
            $has = extension_loaded($ext);
            out(($has ? 'OK     ' : 'MISSING') . " PHP extension $ext");
            $ok = $ok && $has;
        }
        $versionOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        out('PHP version ' . PHP_VERSION . ($versionOk ? ' (OK)' : ' (needs 8.1 or newer)'));
        $ok = $ok && $versionOk;
        try {
            require_once __DIR__ . '/../config/database.php';
            $n = (int) db()->query('SELECT COUNT(*) FROM students')->fetchColumn();
            out("OK     database connection ($n students)");
        } catch (Throwable $e) {
            out('FAILED database connection: ' . $e->getMessage());
            $ok = false;
        }
        try {
            $dir = upload_root();
            $writable = is_writable($dir);
            out(($writable ? 'OK     ' : 'FAILED ') . "upload folder $dir");
            $ok = $ok && $writable;
        } catch (Throwable $e) {
            out('FAILED upload folder: ' . $e->getMessage());
            $ok = false;
        }
        out('upload_max_filesize=' . ini_get('upload_max_filesize') . ' post_max_size=' . ini_get('post_max_size') . ' (CLI values; the web server may differ)');
        exit($ok ? 0 : 1);

    case 'install':
        $sample = in_array('--sample', $argv, true);
        out('This DROPS and recreates the portal tables in database "' . cfg('DB_NAME', 'student_portal') . '".');
        if (strtolower(ask('Type "yes" to continue: ')) !== 'yes') { bail('Cancelled.'); }
        try {
            $pdo = server_connection();
            out('Schema: ' . run_sql_file($pdo, ROOT . '/database/schema.sql') . ' statements executed.');
            if ($sample) {
                out('Sample data: ' . run_sql_file($pdo, ROOT . '/database/sample_data.sql') . ' statements executed.');
                out('Sample files copied: ' . copy_sample_files());
                out('Demo logins (CHANGE THEM): admin / Admin@12345 and staff / Staff@12345');
            } else {
                out('No users exist yet. Create the first one as described in SETUP_GUIDE.md, or re-run with --sample.');
            }
        } catch (Throwable $e) {
            bail($e->getMessage() . "\nIf this is a permissions problem, use PORTAL_DB_ADMIN_USER / PORTAL_DB_ADMIN_PASS or import database/schema.sql with the mysql command.");
        }
        out('Done.');
        exit(0);

    case 'passwd':
        $username = $argv[2] ?? '';
        if ($username === '') { bail('Usage: php php-admin/bin/setup.php passwd <username>'); }
        $pw = ask('New password: ', true);
        $problem = validate_password_strength($pw);
        if ($problem !== '') { bail($problem); }
        if ($pw !== ask('Repeat password: ', true)) { bail('The passwords do not match.'); }
        require_once __DIR__ . '/../config/database.php';
        $stmt = db()->prepare('UPDATE users SET password_hash = :h WHERE username = :u');
        $stmt->execute([':h' => hash_password($pw), ':u' => $username]);
        if ($stmt->rowCount() === 0) { bail("No user named \"$username\" (or the password is unchanged)."); }
        out("Password updated for $username.");
        exit(0);

    default:
        out('Commands: check | install [--sample] | passwd <username>   (details at the top of this file)');
        exit($command === 'help' ? 0 : 1);
}
