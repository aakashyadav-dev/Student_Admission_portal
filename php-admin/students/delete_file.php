<?php
/** Remove one uploaded file. POST + CSRF only. The path always comes from the database row, never the request. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if (!is_post()) {
    http_response_code(405);
    exit('Method not allowed.');
}
csrf_verify();
$pdo = db();
$id = query_id();
$row = $id ? file_find($pdo, $id) : null;
if (!$row) {
    abort_page(404, 'File not found.');
}
file_delete($pdo, $row);
flash('ok', 'File removed.');
redirect('students/edit.php?id=' . (int) $row['student_id']);
