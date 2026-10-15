// Unit tests for the client-side rules.  Run from the project root:  node --test frontend/tests/
const test = require('node:test');
const assert = require('node:assert');
const V = require('../js/validation.js');

test('name', () => {
  assert.notStrictEqual(V.validateName(''), '');
  assert.notStrictEqual(V.validateName('A'), '');
  assert.notStrictEqual(V.validateName('R2D2'), '');
  assert.strictEqual(V.validateName("Aarthi Subramanian"), '');
  assert.strictEqual(V.validateName("D'Souza-Iyer Jr."), '');
});
test('roll number', () => {
  assert.strictEqual(V.validateRoll('24CSE101'), '');
  assert.strictEqual(V.validateRoll('22/EEE-601'), '');
  assert.notStrictEqual(V.validateRoll('ab'), '');
  assert.notStrictEqual(V.validateRoll('24 CSE 101'), '');
  assert.notStrictEqual(V.validateRoll(''), '');
});
test('email', () => {
  assert.strictEqual(V.validateEmail('a.b@college.edu'), '');
  ['', 'plain', 'a@b', 'a b@c.com', '@c.com'].forEach(v => assert.notStrictEqual(V.validateEmail(v), '', v));
});
test('password rules and strength', () => {
  assert.strictEqual(V.validatePassword('Str0ng!pass'), '');
  ['', 'short1!', 'alllowercase1!', 'ALLUPPERCASE1!', 'NoDigits!!', 'NoSymbol123'].forEach(v => assert.notStrictEqual(V.validatePassword(v), '', v));
  assert.strictEqual(V.passwordStrength('abc').label, 'Weak');
  assert.strictEqual(V.passwordStrength('Str0ng!pass').label, 'Good');
  assert.strictEqual(V.passwordStrength('Str0ng!passphrase').label, 'Strong');
});
test('confirm password', () => {
  assert.strictEqual(V.validateConfirm('Abc!1234', 'Abc!1234'), '');
  assert.notStrictEqual(V.validateConfirm('Abc!1234', 'Abc!12345'), '');
  assert.notStrictEqual(V.validateConfirm('Abc!1234', ''), '');
});
test('file type and size', () => {
  const MB = 1024 * 1024;
  assert.strictEqual(V.validateFile({ name: 'me.png', size: MB, type: 'image/png' }, 'profile_image', true), '');
  assert.notStrictEqual(V.validateFile(null, 'profile_image', true), '');
  assert.strictEqual(V.validateFile(null, 'id_proof', false), '');
  assert.notStrictEqual(V.validateFile({ name: 'shell.php', size: 10, type: 'application/x-php' }, 'id_proof', false), '');
  assert.notStrictEqual(V.validateFile({ name: 'me.png', size: 3 * MB, type: 'image/png' }, 'profile_image', true), '');
  assert.notStrictEqual(V.validateFile({ name: 'doc.pdf', size: MB, type: 'application/pdf' }, 'profile_image', true), '');
  assert.notStrictEqual(V.validateFile({ name: 'fake.png', size: 100, type: 'application/pdf' }, 'profile_image', true), '');
  assert.notStrictEqual(V.validateFile({ name: 'empty.png', size: 0, type: 'image/png' }, 'profile_image', true), '');
});
