<?php
/**
 * PUBLIC registration endpoint used by frontend/index.html  (POST multipart/form-data, JSON reply).
 *
 * Why no CSRF token here?  CSRF abuses a victim's *logged-in browser session*. This endpoint has no
 * session and no cookies (the form sends credentials: 'omit'), so there is nothing to ride on.
 * Instead we (1) only answer browsers whose Origin is in ALLOWED_ORIGINS, (2) re-validate everything
 * on the server, (3) create the student as "pending" so staff must review them, and (4) reject bots
 * that fill the hidden "website" honeypot field.
 */
define('PORTAL_API', true);
require __DIR__ . '/../includes/bootstrap.php';

/** Send a JSON reply and stop. */
function reply(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- CORS: only the configured front-end origins may call this API from a browser ----
$origin  = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowed = array_filter(array_map('trim', explode(',', (string) cfg('ALLOWED_ORIGINS', ''))));
if ($origin !== '') {
    if (!in_array($origin, $allowed, true)) {
        reply(403, ['ok' => false, 'message' => 'This origin is not allowed to register students.']);
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {                       // browser pre-flight
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    header('Allow: POST, OPTIONS');
    reply(405, ['ok' => false, 'message' => 'Use POST to register.']);
}

if (post_too_large()) {
    reply(413, ['ok' => false, 'message' => 'The upload is too large.', 'errors' => ['profile_image' => 'The files are larger than the server allows.']]);
}

// ---- Honeypot: pretend success so bots learn nothing, but save nothing ----
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    reply(200, ['ok' => true, 'message' => 'Registration received.', 'roll_number' => '']);
}

$pdo = db();

// ---- Validate the text fields (same rules as the browser, enforced again here) ----
$input = $_POST;
$input['status'] = 'pending';          // self-registrations always wait for staff review
$input['year_of_study'] = 1;
[$errors, $clean] = validate_student($pdo, $input);

$password = (string) ($_POST['password'] ?? '');
$pwError = validate_password_strength($password);
if ($password === '') {
    $errors['password'] = 'Create a password.';
} elseif ($pwError !== '') {
    $errors['password'] = $pwError;
}
if (!isset($errors['password']) && !hash_equals($password, (string) ($_POST['confirm_password'] ?? ''))) {
    $errors['confirm_password'] = 'The passwords do not match.';
}

// ---- Validate the files (nothing is written to disk yet) ----
[$photoErrors, $photoValid] = process_uploads(['profile_image' => 'profile_image']);
[$proofErrors, $proofValid] = process_uploads(['id_proof' => 'id_proof']);
if ($photoErrors) {
    $errors['profile_image'] = implode(' ', $photoErrors);
} elseif (!$photoValid) {
    $errors['profile_image'] = 'Choose a profile photo.';
}
if ($proofErrors) {
    $errors['id_proof'] = implode(' ', $proofErrors);
}
if ($errors) {
    reply(422, ['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);
}

// ---- Save: student row + file rows in one transaction; remove files again if anything fails ----
$written = [];
$newId = 0;
try {
    $pdo->beginTransaction();
    $newId = student_insert($pdo, $clean, hash_password($password));
    persist_uploads($pdo, $newId, array_merge($photoValid, $proofValid), $written);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    discard_files($written);
    if ($newId) {
        remove_student_dir($newId);
    }
    if ($e instanceof PDOException && $e->getCode() === '23000') {   // two people raced for the same roll number/email
        reply(409, ['ok' => false, 'message' => 'This roll number or email is already registered.', 'errors' => ['roll_number' => 'This roll number or email is already registered.']]);
    }
    throw $e;   // the global handler logs it and returns a generic JSON error
}

reply(201, ['ok' => true, 'message' => 'Your registration was received. The admissions office will review it and activate your profile.', 'roll_number' => $clean['roll_number']]);
