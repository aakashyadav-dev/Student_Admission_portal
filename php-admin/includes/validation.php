<?php
/**
 * Server-side validation. These rules mirror frontend/js/validation.js, but this is the copy that
 * actually protects the database -- never trust the browser.
 */
declare(strict_types=1);

const GENDERS  = ['female', 'male', 'other'];
const STATUSES = ['pending', 'active', 'inactive'];

/** Validate the password-strength policy. Returns an error string or ''. */
function validate_password_strength(string $pw): string
{
    if (strlen($pw) < 8)                 return 'Use at least 8 characters.';
    if (strlen($pw) > 128)               return 'Use 128 characters or fewer.';
    if (!preg_match('/[a-z]/', $pw))     return 'Include a lowercase letter.';
    if (!preg_match('/[A-Z]/', $pw))     return 'Include an uppercase letter.';
    if (!preg_match('/\d/', $pw))        return 'Include a number.';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) return 'Include a symbol such as ! @ # $.';
    return '';
}

/**
 * Validate and normalise the student fields shared by the admin forms and the public API.
 * Returns [array $errors (field => message), array $clean (trusted values)].
 *
 * @param int|null $ignoreId  student being edited, so their own roll number/e-mail is not a "duplicate"
 */
function validate_student(PDO $pdo, array $in, ?int $ignoreId = null): array
{
    $errors = [];
    $clean = [];

    $name = trim((string) ($in['full_name'] ?? ''));
    if ($name === '') {
        $errors['full_name'] = 'Enter the full name.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['full_name'] = 'The name must be 2 to 100 characters.';
    } elseif (!preg_match("/^[\\p{L}][\\p{L} .'\\-]*$/u", $name)) {
        $errors['full_name'] = 'Use letters, spaces, dots, hyphens or apostrophes only.';
    }
    $clean['full_name'] = $name;

    $roll = strtoupper(trim((string) ($in['roll_number'] ?? '')));
    if ($roll === '') {
        $errors['roll_number'] = 'Enter the roll number.';
    } elseif (!preg_match('~^[A-Z0-9][A-Z0-9\-/]{3,19}$~', $roll)) {
        $errors['roll_number'] = 'Use 4 to 20 letters, digits, hyphens or slashes.';
    } elseif (value_exists($pdo, 'roll_number', $roll, $ignoreId)) {
        $errors['roll_number'] = 'This roll number is already registered.';
    }
    $clean['roll_number'] = $roll;

    $email = strtolower(trim((string) ($in['email'] ?? '')));
    if ($email === '') {
        $errors['email'] = 'Enter the email address.';
    } elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (value_exists($pdo, 'email', $email, $ignoreId)) {
        $errors['email'] = 'This email address is already registered.';
    }
    $clean['email'] = $email;

    // Optional fields: empty string becomes NULL in the database.
    $phone = preg_replace('/[\s\-]/', '', (string) ($in['phone'] ?? ''));
    if ($phone !== '' && !preg_match('/^\+?[0-9]{10,13}$/', $phone)) {
        $errors['phone'] = 'Enter 10 to 13 digits, with an optional leading +.';
    }
    $clean['phone'] = $phone === '' ? null : $phone;

    $dob = trim((string) ($in['date_of_birth'] ?? ''));
    if ($dob !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $dob);
        $valid = $d && $d->format('Y-m-d') === $dob;
        if (!$valid) {
            $errors['date_of_birth'] = 'Enter a valid date.';
        } else {
            $age = (int) $d->diff(new DateTime('today'))->format('%r%y');
            if ($age < 14 || $age > 70) {
                $errors['date_of_birth'] = 'The date of birth is outside the expected range.';
            }
        }
    }
    $clean['date_of_birth'] = $dob === '' ? null : $dob;

    $gender = (string) ($in['gender'] ?? '');
    if ($gender !== '' && !in_array($gender, GENDERS, true)) {
        $errors['gender'] = 'Choose a valid option.';
    }
    $clean['gender'] = $gender === '' ? null : $gender;

    $year = filter_var($in['year_of_study'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
    if ($year === false) {
        $errors['year_of_study'] = 'Year of study must be 1 to 4.';
    }
    $clean['year_of_study'] = $year === false ? 1 : $year;

    $address = trim((string) ($in['address'] ?? ''));
    if (mb_strlen($address) > 500) {
        $errors['address'] = 'The address must be 500 characters or fewer.';
    }
    $clean['address'] = $address === '' ? null : $address;

    $status = (string) ($in['status'] ?? 'active');
    if (!in_array($status, STATUSES, true)) {
        $errors['status'] = 'Choose a valid status.';
        $status = 'active';
    }
    $clean['status'] = $status;

    // Course: the admin form sends course_id; the public form sends the course code.
    $courseId = null;
    if (!empty($in['course_id'])) {
        $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = :id AND is_active = 1');
        $stmt->execute([':id' => (int) $in['course_id']]);
        $courseId = $stmt->fetchColumn() ?: null;
    } elseif (!empty($in['course'])) {
        $stmt = $pdo->prepare('SELECT id FROM courses WHERE code = :c AND is_active = 1');
        $stmt->execute([':c' => (string) $in['course']]);
        $courseId = $stmt->fetchColumn() ?: null;
    }
    if (!$courseId) {
        $errors[isset($in['course']) ? 'course' : 'course_id'] = 'Choose a valid course.';
    }
    $clean['course_id'] = $courseId ? (int) $courseId : null;

    return [$errors, $clean];
}

/** True when another student already uses this roll number / email. Column name comes from our own code only. */
function value_exists(PDO $pdo, string $column, string $value, ?int $ignoreId): bool
{
    if (!in_array($column, ['roll_number', 'email'], true)) {
        throw new InvalidArgumentException('Unexpected column');
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE {$column} = :v AND id <> :id");
    $stmt->execute([':v' => $value, ':id' => $ignoreId ?? 0]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Detect a request body larger than post_max_size (PHP then silently empties $_POST/$_FILES). */
function post_too_large(): bool
{
    return is_post() && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}
