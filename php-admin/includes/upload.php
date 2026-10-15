<?php
/**
 * Secure file-upload handling.
 *
 * Defences, in order:
 *  1. PHP upload error codes and is_uploaded_file()
 *  2. size limit per category
 *  3. REAL content type detected with finfo (the browser-supplied type and extension are not trusted)
 *  4. extension must agree with the detected type
 *  5. images must decode with getimagesize(); PDFs must start with %PDF-
 *  6. stored name is random hex + an extension WE choose, so user input never reaches the file system
 *     (no directory traversal, no ".php" files, no overwriting)
 *  7. files are written outside the web root, so nothing can be executed by requesting a URL.
 */
declare(strict_types=1);

/** category => allowed MIME types (with their allowed extensions) and max size in bytes */
const UPLOAD_RULES = [
    'profile_image' => ['label' => 'Profile image', 'max' => 2 * 1048576,
        'mimes' => ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']]],
    'id_proof'      => ['label' => 'ID proof', 'max' => 5 * 1048576,
        'mimes' => ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']]],
    'certificate'   => ['label' => 'Certificate', 'max' => 5 * 1048576,
        'mimes' => ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']]],
    'other'         => ['label' => 'Document', 'max' => 5 * 1048576,
        'mimes' => ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']]],
];

/** The extension we store for each detected MIME type. */
const MIME_EXTENSION = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

/**
 * Turn $_FILES[$key] (single or multiple) into a flat list of single-file arrays,
 * dropping the "no file selected" entries.
 */
function collect_files(string $key): array
{
    if (!isset($_FILES[$key])) {
        return [];
    }
    $f = $_FILES[$key];
    $list = [];
    if (is_array($f['name'])) {
        foreach (array_keys($f['name']) as $i) {
            $list[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
    } else {
        $list[] = $f;
    }
    return array_values(array_filter($list, static fn ($x) => $x['error'] !== UPLOAD_ERR_NO_FILE));
}

/**
 * Validate one uploaded file for a category.
 * @return array [string $error, array|null $info]  $error is '' when the file is acceptable.
 */
function validate_upload(array $file, string $category): array
{
    $rule = UPLOAD_RULES[$category] ?? null;
    if ($rule === null) {
        return ['Unknown file category.', null];
    }
    $label = $rule['label'];
    $display = sanitize_filename((string) $file['name']);

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ["{$display}: the file is larger than the server allows.", null];
        case UPLOAD_ERR_PARTIAL:
            return ["{$display}: the upload was interrupted. Try again.", null];
        default:
            return ["{$display}: the upload failed (code {$file['error']}).", null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ["{$display}: not a valid upload.", null];
    }
    $size = (int) $file['size'];
    $limit = min($rule['max'], max_upload_bytes());
    if ($size <= 0) {
        return ["{$display}: the file is empty.", null];
    }
    if ($size > $limit) {
        return ["{$display}: {$label} files must be " . format_bytes($limit) . ' or smaller.', null];
    }

    // Detect the real type from the file contents.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!isset($rule['mimes'][$mime])) {
        return ["{$display}: {$label} must be " . allowed_extensions_text($rule) . '.', null];
    }
    $ext = strtolower(pathinfo($display, PATHINFO_EXTENSION));
    if (!in_array($ext, $rule['mimes'][$mime], true)) {
        return ["{$display}: the file extension does not match its contents.", null];
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        return ["{$display}: the image is corrupted or not a real image.", null];
    }
    if ($mime === 'application/pdf') {
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($head !== '%PDF-') {
            return ["{$display}: the PDF is corrupted.", null];
        }
    }
    return ['', ['mime' => $mime, 'size' => $size, 'original' => $display, 'tmp' => $file['tmp_name']]];
}

function allowed_extensions_text(array $rule): string
{
    $exts = [];
    foreach ($rule['mimes'] as $list) {
        $exts[] = strtoupper($list[0]);
    }
    return implode(', ', $exts);
}

/** Make a user-supplied filename safe to STORE AND DISPLAY (it is never used as a path). */
function sanitize_filename(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));                 // drop any directory part
    $name = preg_replace('/[\x00-\x1F\x7F<>:"|?*]/u', '', $name) ?? ''; // control + reserved characters
    $name = trim($name, ". \t");
    if (mb_strlen($name) > 150) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $name = mb_substr($name, 0, 140) . ($ext !== '' ? '.' . $ext : '');
    }
    return $name === '' ? 'file' : $name;
}

/**
 * Move a validated upload into UPLOAD_DIR/<student_id>/ under a random name.
 * @return array metadata ready for the student_files table
 */
function store_upload(array $info, int $studentId, string $category): array
{
    $dir = upload_root() . '/' . $studentId;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create the student upload folder.');
    }
    $ext = MIME_EXTENSION[$info['mime']];
    do {   // random name; loop makes an overwrite impossible even in the freak collision case
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $stored;
    } while (file_exists($dest));

    if (!move_uploaded_file($info['tmp'], $dest)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    chmod($dest, 0640);
    return [
        'category'      => $category,
        'original_name' => $info['original'],
        'stored_name'   => $stored,
        'relative_path' => $studentId . '/' . $stored,
        'mime_type'     => $info['mime'],
        'file_size'     => $info['size'],
    ];
}

/**
 * Resolve a stored relative path to an absolute path INSIDE the upload root, or null.
 * The strict pattern + realpath() check blocks "../" tricks and symlink escapes.
 */
function safe_upload_path(string $relativePath): ?string
{
    if (!preg_match('~^[0-9]{1,10}/[a-f0-9]{32}\.(jpg|png|webp|pdf)$~', $relativePath)) {
        return null;
    }
    $root = upload_root();
    $full = realpath($root . '/' . $relativePath);
    if ($full === false || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full)) {
        return null;
    }
    return $full;
}

/** Delete a stored file by the path recorded in OUR database. Silent no-op when it is already gone. */
function delete_stored_file(string $relativePath): void
{
    $full = safe_upload_path($relativePath);
    if ($full !== null) {
        @unlink($full);
    }
}

/** Remove a student's upload folder if it is now empty. */
function remove_student_dir(int $studentId): void
{
    $dir = upload_root() . '/' . $studentId;
    if (is_dir($dir) && count(scandir($dir) ?: []) <= 2) {
        @rmdir($dir);
    }
}

/** Form field name => category, used by add.php and edit.php. */
const FORM_UPLOAD_SLOTS = ['profile_image' => 'profile_image', 'id_proof' => 'id_proof', 'certificates' => 'certificate', 'other_documents' => 'other'];
