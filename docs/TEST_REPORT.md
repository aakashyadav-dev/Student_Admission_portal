# Test report

Honest record of what was verified, how, and what was **not**. Environment used to build the project: Linux sandbox with Java 21 (runtime plus the `jdk.compiler` module), Node 22, Python 3.12, headless Chromium. It had **no PHP, MySQL/MariaDB, Maven or Tomcat, and no internet access**.

## Verified by actually running it

| Area | Check | Result |
|---|---|---|
| Front end | 6 Node unit tests of `validation.js` (`node --test frontend/tests/validation.test.js`) | 6 / 6 pass |
| Front end | 15 real-browser checks with headless Chromium (`tools/take_frontend_screenshots.js`): required-field errors, focus on first invalid field, bad e-mail, weak password, mismatched passwords, wrong file type, strength meter, image preview, success panel, no horizontal scroll at 390 px and 820 px | 15 / 15 pass |
| Front end ↔ API | Same script runs the real `fetch` code against a **mocked** API: multipart body contains the fields and the file; a server-side "roll number already registered" error is shown beside the field; a network failure shows a helpful message | pass |
| Bug found and fixed | Server-side field errors were erased by the browser's blur event right after being shown (found by the mocked-API check, fixed in `frontend/js/app.js`) | fixed |
| Java | 22 JUnit-style tests: PBKDF2 (including hashes made by Python, proving PHP/Java compatibility), login throttle, upload-path safety (traversal, bad names, symlink escape), URL/redirect helpers, config resolution, and `AuthFilter` access control (anonymous → redirect to `/login`, never reaches the servlet) | 22 / 22 pass |
| Java | All 22 main Java source files compile at language level 17 | pass (see caveat below) |
| XML | `pom.xml`, `web.xml`, `context.xml` are well-formed (this caught an illegal `--` in a pom comment, fixed) | pass |
| SQL | 34 SQL statements extracted from the PHP and Java sources run against the column names of `schema.sql` loaded into SQLite, with `sample_data.sql` as data (`tools/check_sql_consistency.py`) | 34 / 34 pass |
| Data | 15 file records in `sample_data.sql` all point to real files whose size, type and path pattern match | pass |
| PHP | Static heuristics (`tools/php_static_check.py`): brackets and quotes balanced in all 25 files, all `require` targets exist, all called helper functions are defined | pass |
| Hashes | The two seeded demo hashes verify with their documented passwords in both Python and Java | pass |

## Caveats on the checks above

* **Java was compiled against hand-written stubs** of the Servlet API (kept outside the project), because the real `jakarta.servlet-api` jar was not available offline. This catches syntax, typing and internal-consistency errors but cannot prove my usage matches the real API signatures. `mvn clean package` on your Mac is the real test.
* **Unit tests ran under a minimal stand-in JUnit runner**, not Maven Surefire.
* **SQLite is not MySQL.** The SQL check proves names and joins agree, not MySQL-specific behaviour (for example `LIMIT ? OFFSET ?` binding or strict-mode defaults).
* **The PHP static checker is a heuristic**, not `php -l`. It cannot catch every syntax error or any runtime error.
* **The browser tests ran in demo mode or against a mocked API**, never against the real PHP endpoint.

## NOT verified (could not be run here)

| Area | What is unverified | How to verify |
|---|---|---|
| PHP | No PHP file has been parsed or executed: CRUD pages, uploads, CSRF, login, `register.php`, `bin/setup.php` | `find php-admin -name '*.php' -exec php -l {} \;` then the smoke test in SETUP_GUIDE.md section 6 |
| MySQL | `schema.sql` and `sample_data.sql` have not been imported into MySQL | `mysql -u root -p < database/schema.sql` etc. |
| Maven | Dependency resolution, WAR packaging, Surefire tests | `cd java-servlet && mvn clean package` |
| Tomcat / JSP | The five JSP pages have not been compiled or rendered; JSTL dependency coordinates were chosen from knowledge, not resolved | deploy the WAR (SETUP_GUIDE.md section 5) and open each page |
| Servlet streaming | `FileServlet` against a real response, real browser `<img>` loading | smoke-test steps 6 and 7 |
| End to end | registration form → PHP API → database → admin list → Java portal | smoke-test step 5, then open the student in the Java portal |
| Screenshots of B and C | none exist (see `screenshots/README.md`) | capture them while running the smoke test |

## Commands to re-run everything that can run

```bash
node --test frontend/tests/validation.test.js
python3 -m http.server 8000 --directory frontend &  node tools/take_frontend_screenshots.js    # needs: npm i playwright
python3 tools/check_sql_consistency.py
python3 tools/php_static_check.py
cd java-servlet && mvn clean package          # compiles and runs all Java tests
find php-admin -name '*.php' -exec php -l {} \;
```
