<?php
/**
 * Shared student form for add.php and edit.php.
 * Expects: $values (field => value), $errors (field => message), $courses, $isEdit.
 */
defined('PORTAL_APP') or exit;
$v = static fn (string $k) => e((string) ($values[$k] ?? ''));
$err = static fn (string $k) => isset($errors[$k]) ? '<div class="error-msg" id="' . e($k) . '_error">' . e($errors[$k]) . '</div>' : '';
$inv = static fn (string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="' . e($k) . '_error"' : '';
?>
<form method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <fieldset>
    <legend>Student details</legend>
    <div class="form-grid">
      <div class="field full"><label for="full_name">Full name <span class="req">*</span></label>
        <input type="text" id="full_name" name="full_name" maxlength="100" required value="<?= $v('full_name') ?>"<?= $inv('full_name') ?>><?= $err('full_name') ?></div>
      <div class="field"><label for="roll_number">Roll number <span class="req">*</span></label>
        <input type="text" id="roll_number" name="roll_number" maxlength="20" required value="<?= $v('roll_number') ?>"<?= $inv('roll_number') ?>><?= $err('roll_number') ?></div>
      <div class="field"><label for="email">Email <span class="req">*</span></label>
        <input type="email" id="email" name="email" maxlength="150" required value="<?= $v('email') ?>"<?= $inv('email') ?>><?= $err('email') ?></div>
      <div class="field"><label for="course_id">Course <span class="req">*</span></label>
        <select id="course_id" name="course_id" required<?= $inv('course_id') ?>>
          <option value="">Select a course</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= (string) ($values['course_id'] ?? '') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['code'] . ' - ' . $c['name']) ?></option>
          <?php endforeach; ?>
        </select><?= $err('course_id') ?></div>
      <div class="field"><label for="year_of_study">Year of study</label>
        <select id="year_of_study" name="year_of_study"<?= $inv('year_of_study') ?>>
          <?php for ($y = 1; $y <= 4; $y++): ?><option value="<?= $y ?>"<?= (int) ($values['year_of_study'] ?? 1) === $y ? ' selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?>
        </select><?= $err('year_of_study') ?></div>
      <div class="field"><label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" maxlength="20" value="<?= $v('phone') ?>"<?= $inv('phone') ?>><?= $err('phone') ?></div>
      <div class="field"><label for="date_of_birth">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth" value="<?= $v('date_of_birth') ?>"<?= $inv('date_of_birth') ?>><?= $err('date_of_birth') ?></div>
      <div class="field"><label for="gender">Gender</label>
        <select id="gender" name="gender"<?= $inv('gender') ?>>
          <option value="">Prefer not to say</option>
          <?php foreach (GENDERS as $g): ?><option value="<?= e($g) ?>"<?= ($values['gender'] ?? '') === $g ? ' selected' : '' ?>><?= e(ucfirst($g)) ?></option><?php endforeach; ?>
        </select><?= $err('gender') ?></div>
      <div class="field"><label for="status">Status</label>
        <select id="status" name="status"<?= $inv('status') ?>>
          <?php foreach (STATUSES as $s): ?><option value="<?= e($s) ?>"<?= ($values['status'] ?? 'active') === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
        </select><?= $err('status') ?></div>
      <div class="field full"><label for="address">Address</label>
        <textarea id="address" name="address" maxlength="500"<?= $inv('address') ?>><?= $v('address') ?></textarea><?= $err('address') ?></div>
    </div>
  </fieldset>

  <fieldset>
    <legend><?= $isEdit ? 'Add more documents' : 'Documents' ?></legend>
    <p class="hint">Images: JPG, PNG or WebP (profile image up to 2 MB). Documents: PDF, JPG or PNG up to 5 MB each. Select several files to upload multiple certificates.</p>
    <div class="form-grid">
      <div class="field"><label for="profile_image"><?= $isEdit ? 'Replace profile image' : 'Profile image' ?></label>
        <input class="file-input" type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp"></div>
      <div class="field"><label for="id_proof">ID proof</label>
        <input class="file-input" type="file" id="id_proof" name="id_proof" accept=".pdf,.jpg,.jpeg,.png"></div>
      <div class="field"><label for="certificates">Certificates</label>
        <input class="file-input" type="file" id="certificates" name="certificates[]" accept=".pdf,.jpg,.jpeg,.png" multiple></div>
      <div class="field"><label for="other_documents">Other supporting documents</label>
        <input class="file-input" type="file" id="other_documents" name="other_documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple></div>
    </div>
  </fieldset>

  <div class="btn-row">
    <button class="btn" type="submit"><?= $isEdit ? 'Save changes' : 'Add student' ?></button>
    <a class="btn btn-ghost" href="<?= e(url('students/index.php')) ?>">Cancel</a>
  </div>
</form>
