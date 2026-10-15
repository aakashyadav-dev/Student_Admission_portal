/*
 * Takes REAL screenshots of the registration page (frontend/) with headless Chromium.
 * Usage (project root):  npm i playwright && npx playwright install chromium
 *                        python3 -m http.server 8000 --directory frontend &
 *                        node tools/take_frontend_screenshots.js
 * The page runs in DEMO_MODE here (config.js is overridden inside the test browser only),
 * so no backend is needed. A second check mocks the PHP API to test the fetch() integration code.
 */
const { chromium } = require('playwright');
const path = require('path');
const OUT = path.join(__dirname, '..', 'screenshots');
const PHOTO = path.join(__dirname, '..', 'storage', 'sample_uploads', '1', 'e5eb07cd7ecc55ebd6f68ea54764e42e.png');
const BASE = 'http://localhost:8000/index.html';
const DEMO_CFG = "window.PORTAL_CONFIG={API_URL:'http://localhost:8080/api/register.php',DEMO_MODE:true};";

(async () => {
  const browser = await chromium.launch(process.env.CHROME ? { executablePath: process.env.CHROME } : {});
  const results = [];
  const check = (name, ok) => { results.push([name, ok]); console.log((ok ? 'PASS ' : 'FAIL ') + name); };

  async function open(viewport, demo) {
    const ctx = await browser.newContext({ viewport, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    if (demo) await page.route('**/js/config.js', r => r.fulfill({ contentType: 'application/javascript', body: DEMO_CFG }));
    await page.goto(BASE);
    return { ctx, page };
  }

  // ---- Desktop, demo mode ----
  let { ctx, page } = await open({ width: 1280, height: 900 }, true);
  await page.screenshot({ path: `${OUT}/01_registration_form_desktop.png`, fullPage: true });

  await page.click('#submit-btn');
  check('empty submit shows required-field errors', (await page.locator('.error-msg:not(:empty)').count()) >= 6);
  check('focus moves to first invalid field', await page.evaluate(() => document.activeElement.id) === 'full_name');
  await page.screenshot({ path: `${OUT}/02_validation_errors_desktop.png`, fullPage: true });

  await page.fill('#full_name', 'Aarthi Subramanian');
  await page.fill('#roll_number', '24cse101');
  await page.fill('#email', 'not-an-email');
  await page.selectOption('#course', 'CSE');
  await page.fill('#password', 'weak');
  await page.fill('#confirm_password', 'different');
  await page.setInputFiles('#profile_image', { name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('hello') });
  await page.click('#submit-btn');
  check('invalid email rejected', /valid email/i.test(await page.textContent('#email_error')));
  check('weak password rejected', /needs/i.test(await page.textContent('#password_error')));
  check('mismatched confirm rejected', /do not match/i.test(await page.textContent('#confirm_password_error')));
  check('wrong file type rejected', /JPG, PNG, WEBP/i.test(await page.textContent('#profile_image_error')));
  await page.screenshot({ path: `${OUT}/03_validation_mixed_errors.png`, fullPage: true });

  await page.fill('#email', 'aarthi.s@example.edu');
  await page.fill('#password', 'Str0ng!Passw0rd');
  await page.fill('#confirm_password', 'Str0ng!Passw0rd');
  await page.setInputFiles('#profile_image', PHOTO);
  await page.setInputFiles('#id_proof', { name: 'id.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 test') });
  check('strength meter says Strong', /Strong/.test(await page.textContent('#strength-label')));
  check('image preview rendered', (await page.locator('#profile_preview img').count()) === 1);
  check('all 5 password rules ticked', (await page.locator('.pw-checks li.met').count()) === 5);
  await page.screenshot({ path: `${OUT}/04_valid_form_with_previews.png`, fullPage: true });

  await page.click('#submit-btn');
  await page.waitForSelector('#success-panel:not([hidden])');
  check('demo-mode success panel shown with upper-cased roll', (await page.textContent('#success-roll')) === '24CSE101');
  await page.screenshot({ path: `${OUT}/05_success_demo_mode.png` });
  await ctx.close();

  // ---- Mobile ----
  ({ ctx, page } = await open({ width: 390, height: 844 }, true));
  await page.screenshot({ path: `${OUT}/06_registration_mobile.png`, fullPage: true });
  await page.click('#submit-btn');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  check('no horizontal scrolling at 390px', !overflow);
  await page.screenshot({ path: `${OUT}/07_validation_errors_mobile.png`, fullPage: true });
  await ctx.close();

  // ---- Tablet ----
  ({ ctx, page } = await open({ width: 820, height: 1100 }, true));
  check('no horizontal scrolling at 820px', !(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)));
  await page.screenshot({ path: `${OUT}/08_registration_tablet.png`, fullPage: true });
  await ctx.close();

  // ---- Real (non-demo) code path against a MOCKED API, to test fetch() handling. Not a screenshot. ----
  ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  page = await ctx.newPage();
  let received = null;
  await page.route('**/api/register.php', async route => {
    received = route.request().postData() || '';
    await route.fulfill({ status: 422, contentType: 'application/json', headers: { 'access-control-allow-origin': '*' },
      body: JSON.stringify({ ok: false, message: 'Please fix the highlighted fields.', errors: { roll_number: 'This roll number is already registered.' } }) });
  });
  await page.goto(BASE);
  await page.fill('#full_name', 'Test Student'); await page.fill('#roll_number', '24CSE101'); await page.fill('#email', 't@example.edu');
  await page.selectOption('#course', 'CSE'); await page.fill('#password', 'Str0ng!Passw0rd'); await page.fill('#confirm_password', 'Str0ng!Passw0rd');
  await page.setInputFiles('#profile_image', PHOTO);
  await page.click('#submit-btn');
  await page.waitForFunction(() => document.getElementById('roll_number_error').textContent.length > 0);
  check('[mock API] multipart POST contains the form fields', received && received.includes('name="roll_number"') && received.includes('name="profile_image"'));
  check('[mock API] server error shown beside the roll-number field', /already registered/.test(await page.textContent('#roll_number_error')));
  await ctx.close();

  ctx = await browser.newContext(); page = await ctx.newPage();
  await page.route('**/api/register.php', route => route.abort());
  await page.goto(BASE);
  await page.fill('#full_name', 'Test Student'); await page.fill('#roll_number', '24CSE199'); await page.fill('#email', 't2@example.edu');
  await page.selectOption('#course', 'CSE'); await page.fill('#password', 'Str0ng!Passw0rd'); await page.fill('#confirm_password', 'Str0ng!Passw0rd');
  await page.setInputFiles('#profile_image', PHOTO); await page.click('#submit-btn');
  await page.waitForFunction(() => /Could not reach/.test(document.getElementById('form-status').textContent));
  check('[mock API] network failure gives a helpful message', true);
  await ctx.close();

  await browser.close();
  const failed = results.filter(r => !r[1]).length;
  console.log(`\n${results.length - failed}/${results.length} browser checks passed`);
  process.exit(failed ? 1 : 0);
})();
