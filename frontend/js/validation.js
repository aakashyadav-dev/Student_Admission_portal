/*
 * validation.js -- pure validation rules (no DOM access), so they can be unit-tested with Node.
 * The PHP backend (php-admin/includes/validation.php) enforces the SAME rules on the server,
 * because client-side checks can always be bypassed.
 * Every validator returns an empty string when the value is fine, otherwise a message to show.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) { module.exports = factory(); }
  else { root.PortalValidation = factory(); }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var MB = 1024 * 1024;

  // Allowed upload types per field: extension -> MIME type, plus a maximum size.
  var FILE_RULES = {
    profile_image: { label: 'Profile photo', max: 2 * MB, types: { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' } },
    id_proof: { label: 'Supporting document', max: 5 * MB, types: { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' } }
  };

  function validateName(value) {
    var v = (value || '').trim();
    if (!v) return 'Enter your full name.';
    if (v.length < 2) return 'Your name must have at least 2 characters.';
    if (v.length > 100) return 'Your name must be 100 characters or fewer.';
    if (!/^[\p{L}][\p{L} .'\-]*$/u.test(v)) return 'Use letters, spaces, dots, hyphens or apostrophes only.';
    return '';
  }

  function validateRoll(value) {
    var v = (value || '').trim();
    if (!v) return 'Enter your roll number.';
    if (!/^[A-Za-z0-9][A-Za-z0-9\-\/]{3,19}$/.test(v)) return 'Use 4 to 20 letters, digits, hyphens or slashes, for example 24CSE101.';
    return '';
  }

  function validateEmail(value) {
    var v = (value || '').trim();
    if (!v) return 'Enter your email address.';
    if (v.length > 150) return 'The email address is too long.';
    // Practical check: something@domain.tld with no spaces. The server re-checks with filter_var().
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Enter a valid email address, for example name@college.edu.';
    return '';
  }

  function validateCourse(value) {
    return value ? '' : 'Choose your course.';
  }

  // Returns the checks so the UI can render a live checklist and strength meter.
  function passwordChecks(pw) {
    pw = pw || '';
    return {
      length: pw.length >= 8,
      lower: /[a-z]/.test(pw),
      upper: /[A-Z]/.test(pw),
      digit: /\d/.test(pw),
      special: /[^A-Za-z0-9]/.test(pw)
    };
  }

  function passwordStrength(pw) {
    var c = passwordChecks(pw);
    var score = Object.keys(c).filter(function (k) { return c[k]; }).length;
    if ((pw || '').length >= 12 && score === 5) score = 6;
    var label = score <= 2 ? 'Weak' : score <= 4 ? 'Fair' : score === 5 ? 'Good' : 'Strong';
    return { score: score, max: 6, label: (pw ? label : '') };
  }

  function validatePassword(value) {
    var pw = value || '';
    if (!pw) return 'Create a password.';
    if (pw.length > 128) return 'The password must be 128 characters or fewer.';
    var c = passwordChecks(pw);
    var missing = [];
    if (!c.length) missing.push('at least 8 characters');
    if (!c.lower) missing.push('a lowercase letter');
    if (!c.upper) missing.push('an uppercase letter');
    if (!c.digit) missing.push('a number');
    if (!c.special) missing.push('a symbol such as ! @ # $');
    return missing.length ? 'Your password needs ' + missing.join(', ') + '.' : '';
  }

  function validateConfirm(password, confirm) {
    if (!confirm) return 'Re-enter your password.';
    return password === confirm ? '' : 'The passwords do not match.';
  }

  function extensionOf(name) {
    var i = (name || '').lastIndexOf('.');
    return i < 0 ? '' : name.slice(i + 1).toLowerCase();
  }

  // file: { name, size, type }  (a browser File object works)
  function validateFile(file, ruleKey, required) {
    var rule = FILE_RULES[ruleKey];
    if (!file) return required ? 'Choose a file for ' + rule.label.toLowerCase() + '.' : '';
    var ext = extensionOf(file.name);
    if (!Object.prototype.hasOwnProperty.call(rule.types, ext)) {
      return rule.label + ' must be ' + Object.keys(rule.types).filter(function (e) { return e !== 'jpeg'; }).join(', ').toUpperCase() + '.';
    }
    if (file.type && file.type !== rule.types[ext]) return 'The file contents do not match its ' + ext.toUpperCase() + ' extension.';
    if (file.size === 0) return 'The selected file is empty.';
    if (file.size > rule.max) return rule.label + ' must be ' + (rule.max / MB) + ' MB or smaller.';
    return '';
  }

  function formatBytes(n) {
    if (n < 1024) return n + ' B';
    if (n < MB) return (n / 1024).toFixed(1) + ' KB';
    return (n / MB).toFixed(1) + ' MB';
  }

  return {
    FILE_RULES: FILE_RULES, validateName: validateName, validateRoll: validateRoll, validateEmail: validateEmail,
    validateCourse: validateCourse, validatePassword: validatePassword, validateConfirm: validateConfirm,
    validateFile: validateFile, passwordChecks: passwordChecks, passwordStrength: passwordStrength, formatBytes: formatBytes
  };
}));
