<?php
/**
 * Serves an uploaded file to a signed-in admin. Files live outside the web root, so this script is
 * the only way to read them. The id selects a database row; the path comes from that row and is
 * re-validated by safe_upload_path(), so a visitor can never choose an arbitrary file.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = query_id();
if ($id === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid request.');
}
$row = file_find(db(), $id);
$path = $row ? safe_upload_path($row['relative_path']) : null;
if (!$row || $path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('File not found.');
}

$mime = isset(MIME_EXTENSION[$row['mime_type']]) ? $row['mime_type'] : 'application/octet-stream';
$isImage = str_starts_with($mime, 'image/');
$asDownload = isset($_GET['download']) || !$isImage;
$asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $row['original_name']) ?: 'file';

session_write_close();               // do not hold the session lock while streaming
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($asDownload ? 'attachment' : 'inline') . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($row['original_name']));
header('Content-Security-Policy: default-src \'none\'; sandbox');   // even if sniffed as HTML, nothing can run
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
