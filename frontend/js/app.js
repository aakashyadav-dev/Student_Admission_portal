/*
 * app.js -- wires the registration form to the validators in validation.js.
 * Responsibilities: inline errors, live password checklist, file previews, submit via fetch().
 */
(function () {
  'use strict';
  var V = window.PortalValidation;
  var cfg = window.PORTAL_CONFIG || {};
  var form = document.getElementById('registration-form');
  if (!form) return;

  var $ = function (id) { return document.getElementById(id); };
  var fields = {
    full_name: { el: $('full_name'), check: function () { return V.validateName($('full_name').value); } },
    roll_number: { el: $('roll_number'), check: function () { return V.validateRoll($('roll_number').value); } },
    email: { el: $('email'), check: function () { return V.validateEmail($('email').value); } },
    course: { el: $('course'), check: function () { return V.validateCourse($('course').value); } },
    profile_image: { el: $('profile_image'), check: function () { return V.validateFile($('profile_image').files[0], 'profile_image', true); } },
    id_proof: { el: $('id_proof'), check: function () { return V.validateFile($('id_proof').files[0], 'id_proof', false); } },
    password: { el: $('password'), check: function () { return V.validatePassword($('password').value); } },
    confirm_password: { el: $('confirm_password'), check: function () { return V.validateConfirm($('password').value, $('confirm_password').value); } }
  };
  var order = Object.keys(fields);
  var serverErrors = {};   // messages returned by the server; kept until the user edits that field

  // Show or clear the error for one field. Returns true when the field is valid.
  function showField(name, touched) {
    var f = fields[name];
    var msg = serverErrors[name] || f.check();
    var out = $(name + '_error');
    out.textContent = touched ? msg : '';
    f.el.setAttribute('aria-invalid', touched && msg ? 'true' : 'false');
    f.el.classList.toggle('is-valid', touched && !msg && f.el.value !== '');
    return !msg;
  }

  order.forEach(function (name) {
    var el = fields[name].el;
    el.addEventListener('blur', function () { showField(name, true); });
    el.addEventListener(el.tagName === 'SELECT' || el.type === 'file' ? 'change' : 'input', function () {
      delete serverErrors[name];   // the user is changing the value, so the server message is stale
      // Re-validate while typing only if the user has already seen an error for this field.
      if (el.getAttribute('aria-invalid') === 'true') showField(name, true);
    });
  });

  // ---- Password checklist and strength meter ----
  var meter = $('strength-bar'), meterLabel = $('strength-label');
  function updatePassword() {
    var pw = $('password').value, checks = V.passwordChecks(pw), s = V.passwordStrength(pw);
    Object.keys(checks).forEach(function (k) {
      var li = document.querySelector('[data-check="' + k + '"]');
      if (li) li.classList.toggle('met', checks[k]);
    });
    meter.style.width = Math.round(s.score / s.max * 100) + '%';
    meter.dataset.level = s.label.toLowerCase();
    meterLabel.textContent = s.label ? 'Strength: ' + s.label : '';
    if ($('confirm_password').value) showField('confirm_password', true);
  }
  $('password').addEventListener('input', updatePassword);
  $('confirm_password').addEventListener('input', function () { showField('confirm_password', true); });

  // Show/hide password toggle.
  $('toggle-password').addEventListener('click', function () {
    var show = $('password').type === 'password';
    $('password').type = $('confirm_password').type = show ? 'text' : 'password';
    this.textContent = show ? 'Hide passwords' : 'Show passwords';
    this.setAttribute('aria-pressed', String(show));
  });

  // ---- File previews ----
  var previewUrl = null;
  function renderPreview(inputId, boxId) {
    var input = $(inputId), box = $(boxId), file = input.files[0];
    box.innerHTML = '';
    if (inputId === 'profile_image' && previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
    if (!file || V.validateFile(file, inputId, false)) return; // only preview valid files
    var info = document.createElement('div');
    info.className = 'file-chip';
    if (file.type.indexOf('image/') === 0) {
      var img = document.createElement('img');
      var url = URL.createObjectURL(file);
      if (inputId === 'profile_image') previewUrl = url;
      img.src = url; img.alt = 'Preview of the selected file';
      info.appendChild(img);
    } else {
      var icon = document.createElement('span'); icon.className = 'file-icon'; icon.textContent = 'PDF';
      info.appendChild(icon);
    }
    var text = document.createElement('span');
    text.textContent = file.name + ' (' + V.formatBytes(file.size) + ')'; // textContent: no HTML injection
    info.appendChild(text);
    box.appendChild(info);
  }
  $('profile_image').addEventListener('change', function () { renderPreview('profile_image', 'profile_preview'); });
  $('id_proof').addEventListener('change', function () { renderPreview('id_proof', 'proof_preview'); });

  // ---- Submission ----
  var statusBox = $('form-status'), submitBtn = $('submit-btn');
  function setStatus(kind, html) {
    statusBox.className = 'alert alert-' + kind;
    statusBox.hidden = false;
    statusBox.replaceChildren();
    if (typeof html === 'string') statusBox.textContent = html; else statusBox.appendChild(html);
    statusBox.focus();
  }

  function showSuccess(message, roll) {
    form.hidden = true;
    var box = $('success-panel');
    $('success-message').textContent = message;
    $('success-roll').textContent = roll;
    box.hidden = false;
    box.focus();
    if (cfg.DEMO_MODE) box.querySelector('.demo-note').hidden = false;
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    statusBox.hidden = true;
    var firstBad = null;
    order.forEach(function (name) { if (!showField(name, true) && !firstBad) firstBad = fields[name].el; });
    if (firstBad) {
      setStatus('err', 'Fix the highlighted fields and submit again.');
      firstBad.focus();
      return;
    }
    if (cfg.DEMO_MODE) { showSuccess('Demo mode: the details passed validation. Nothing was saved.', form.roll_number.value.trim().toUpperCase()); return; }

    submitBtn.disabled = true; submitBtn.textContent = 'Submitting...';
    var data = new FormData();
    data.append('full_name', $('full_name').value.trim());
    data.append('roll_number', $('roll_number').value.trim());
    data.append('email', $('email').value.trim());
    data.append('course', $('course').value);
    data.append('password', $('password').value);
    data.append('confirm_password', $('confirm_password').value);
    data.append('website', $('website').value); // honeypot, must stay empty
    data.append('profile_image', $('profile_image').files[0]);
    if ($('id_proof').files[0]) data.append('id_proof', $('id_proof').files[0]);

    fetch(cfg.API_URL, { method: 'POST', body: data, credentials: 'omit' })
      .then(function (res) { return res.json().catch(function () { return {}; }).then(function (body) { return { status: res.status, body: body }; }); })
      .then(function (r) {
        if (r.body && r.body.ok) { showSuccess(r.body.message, r.body.roll_number); return; }
        // Server-side validation errors: show them next to the matching fields.
        var errs = (r.body && r.body.errors) || {};
        var list = document.createElement('div');
        list.appendChild(document.createTextNode((r.body && r.body.message) || 'The server could not save your registration.'));
        var firstServerField = null;
        Object.keys(errs).forEach(function (k) {
          if (fields[k]) { serverErrors[k] = errs[k]; showField(k, true); if (!firstServerField) firstServerField = fields[k].el; }
          else { var p = document.createElement('div'); p.textContent = errs[k]; list.appendChild(p); }
        });
        setStatus('err', list);
        if (firstServerField) firstServerField.focus();   // focus AFTER the status box, so no blur wipes the message
      })
      .catch(function () {
        setStatus('err', 'Could not reach the registration server at ' + cfg.API_URL + '. Start the PHP server (see SETUP_GUIDE.md) or set DEMO_MODE to true in js/config.js.');
      })
      .then(function () { submitBtn.disabled = false; submitBtn.textContent = 'Submit registration'; });
  });

  form.addEventListener('reset', function () {
    serverErrors = {};
    setTimeout(function () {
      order.forEach(function (name) { $(name + '_error').textContent = ''; fields[name].el.setAttribute('aria-invalid', 'false'); fields[name].el.classList.remove('is-valid'); });
      $('profile_preview').innerHTML = ''; $('proof_preview').innerHTML = ''; statusBox.hidden = true; updatePassword();
    }, 0);
  });

  if (cfg.DEMO_MODE) { var b = document.querySelector('.demo-banner'); if (b) b.hidden = false; }
  $('register-another').addEventListener('click', function () { form.reset(); form.hidden = false; $('success-panel').hidden = true; $('full_name').focus(); });
})();
