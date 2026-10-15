# Documentation: Student Admission and Profile Management Portal

U21CS501 Web Technology, Assignment II. This document explains what was built for each question, how the database is designed, how security is handled, and what the important source files do. Setup commands are in SETUP_GUIDE.md; the verification record is in docs/TEST_REPORT.md.

## 1. Design overview

Three applications cooperate through two shared resources:

* **One database**, `student_portal` (MySQL 8, utf8mb4), described in `database/schema.sql`.
* **One upload folder**, `storage/uploads/`, configured once in `config/portal.properties` and read by both PHP and Java. It lives **outside** every web root; the only way to read a file is through `students/file.php` (PHP) or `FileServlet` (Java), and both look the file up by database id.

The two back ends also share three *contracts*, so neither depends on the other being installed:

| Contract | Rule | Where it is implemented |
|---|---|---|
| Table design | `users`, `courses`, `students`, `student_files` | `database/schema.sql`; queries in `repository.php` and the Java DAOs |
| Password hash | `pbkdf2_sha256$<iterations>$<base64 salt>$<base64 hash>` | `php-admin/includes/auth.php`, `PasswordHasher.java` |
| Stored-file path | `<student id>/<32 hex chars>.<jpg|png|webp|pdf>`, relative to the upload folder | `php-admin/includes/upload.php`, `UploadStorage.java` |

## 2. Database design

```
courses 1 ──< students 1 ──< student_files          users (staff accounts, independent)
```

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Staff who may sign in to the admin panel and the servlet portal | `username` (unique), `password_hash`, `role` (`admin`/`staff`), `is_active`, `last_login_at` |
| `courses` | Lookup table, so a course name is stored once (third normal form) | `code` (unique), `name`, `department` |
| `students` | One row per student | `roll_number` (**unique**), `email` (unique), `course_id` (FK, `RESTRICT`), `status` (`pending`/`active`/`inactive`), `password_hash` (only for self-registered students), `created_at`, `updated_at` |
| `student_files` | Metadata for each uploaded file | `student_id` (FK, `ON DELETE CASCADE`), `category` (`profile_image`/`id_proof`/`certificate`/`other`), `original_name`, `stored_name` (unique), `relative_path`, `mime_type`, `file_size`, `uploaded_at` |

Why this satisfies the brief: files are linked to the right student by a foreign key; the stored filename is unique, so two uploads can never overwrite each other; deleting a student cascades to the file rows (PHP then removes the bytes); and a course cannot be deleted while students use it. The `original_name` is kept only for display and downloads and is never used to build a path.

## 3. Question 1: Registration form (`frontend/`)

* **Fields:** full name, roll number, e-mail, course, profile photo (required), ID proof (optional), password, confirm password.
* **Validation (`js/validation.js`, pure functions):** required fields, e-mail format, roll-number pattern, password strength (8+ characters with upper case, lower case, digit and symbol, shown as a meter plus a live checklist), matching passwords, and file extension, MIME type and size (photo up to 2 MB; ID proof up to 5 MB). Messages appear next to the field, are linked to it with `aria-describedby`, and focus moves to the first invalid field on submit.
* **Interaction (`js/app.js`):** validates on blur and on every change after a field has been touched, previews the chosen photo, offers "Show passwords", submits with `fetch` + `FormData` to the PHP API, shows server-side field errors (for example a duplicate roll number) next to the right field and handles network failure with a clear message.
* **Responsive CSS (`css/base.css`, `css/register.css`):** design tokens in `:root`, two-column layout that collapses to one column on tablets and phones, visible focus styles, no external fonts or libraries.
* **Persistence:** the form posts to `php-admin/api/register.php`, which re-validates everything on the server and creates a *pending* student. If `DEMO_MODE` is `true` in `js/config.js`, nothing is sent and the page says so.

## 4. Question 2: PHP and MySQL administration panel (`php-admin/`)

| Requirement | Implementation |
|---|---|
| Add / view all / view one / edit / delete | `students/add.php`, `index.php` (search, filters, pagination), `view.php`, `edit.php`, `delete.php` (a separate confirmation page; POST performs the delete) |
| File upload for profile image, ID proof, certificates, other documents | form fields in `students/_form.php`; processed by `process_uploads()` then `persist_uploads()` in `includes/repository.php` |
| Metadata in MySQL | `file_insert()` stores path, original name, safe stored name, MIME type, size, and upload time, linked by `student_id` |
| Display uploaded files | `view.php` lists every file; images are previews, other files have Download buttons; `students/file.php` serves them |
| Validation and error handling | `includes/validation.php` (server-side rules) and `includes/upload.php`; errors are shown inline; unexpected exceptions go to a generic error page and the log, never to the visitor |
| File size / type / overwrite prevention | size limit per category, extension allow-list, real MIME detection with `finfo`, `getimagesize()` for images, `%PDF-` signature check for PDFs, random 32-hex stored names |
| Safe delete | the file path comes from the database row and is re-checked by `safe_upload_path()` (pattern + `realpath` inside the upload root), so no request value can name a file to delete |
| Setup script and SQL | `bin/setup.php` (`check`, `install [--sample]`, `passwd`), `database/schema.sql`, `database/sample_data.sql` (8 realistic students, 6 courses, 15 file records) |

**Authentication (PHP):** `login.php` checks the `users` table; the session is `ADMINSESSID` (HttpOnly, SameSite=Lax, `Secure` when served over HTTPS), the id is regenerated on login, idle sessions expire after 30 minutes, and logout is a POST with a CSRF token. The `admin` role may delete students; `staff` may view, add and edit.

## 5. Question 3: Java Servlets, JSP and JDBC (`java-servlet/`)

| Requirement | Implementation |
|---|---|
| JDBC connection to MySQL | `db/Database.java` (`DriverManager`, driver loaded by name); settings from the shared `portal.properties` via `config/AppConfig.java` |
| Fetch student details and file paths | `dao/StudentDao.java`, `dao/StudentFileDao.java`, `dao/UserDao.java`: every query is a `PreparedStatement`; every connection is closed with try-with-resources |
| Servlet that streams images/documents | `web/FileServlet.java` mapped to `/portal/file`: looks the file up by id, resolves it with `UploadStorage`, sets `Content-Type`, `Content-Length`, `Content-Disposition` (inline for images, attachment for documents or `?download=1`), `X-Content-Type-Options: nosniff` and a locked-down `Content-Security-Policy`, then streams the bytes |
| `<img>` tags pointing to the servlet | `students.jsp` and `student.jsp` use `<img src="${ctx}/portal/file?id=...">` |
| Looping and conditional logic | `students.jsp`: `<c:forEach>` over students, `<c:choose>` photo-or-initials, empty state, pagination; `student.jsp`: loop over files with image/document and missing-file branches |
| Document download links | `student.jsp` renders a Download link (`...&download=1`) for every available file |
| Session handling | `web/AuthFilter.java` is mapped to `/portal/*`: no signed-in user means a redirect to `/login` and the servlet never runs; protected pages are sent with `Cache-Control: no-store` |
| Login and logout, hashing, secure sessions | `LoginServlet` (CSRF token, PBKDF2 verification with a dummy-hash for unknown users, `changeSessionId()` against fixation, per-user+IP throttle, open-redirect-safe `next`), `LogoutServlet` (POST + CSRF, `session.invalidate()`); cookie settings in `web.xml` and `META-INF/context.xml` |
| Missing files, invalid requests, DB errors | `400` for a bad id, `404` for unknown ids or files absent from disk, `500` for database errors; `web.xml` maps these to `views/error.jsp` with friendly text and no stack trace |
| Mappings and `WEB-INF` | explicit `<servlet>`/`<filter>` mappings in `WEB-INF/web.xml` (Servlet 6.0 schema); JSPs live under `WEB-INF/views/` so they cannot be requested directly |

**Authentication (Java):** separate from PHP. Same users and hashes, different session. Signing in to one application never signs you in to the other.

**Stack:** Java 17+, Maven, Jakarta Servlet 6.0 / JSP 3.1 / JSTL 3.0, MySQL Connector/J 8.4, Tomcat 10.1.x. Tomcat 10 is required because Jakarta EE 9+ renamed `javax.servlet` to `jakarta.servlet`; Tomcat 9 would not load these classes.

## 6. Security summary

| Threat | Defence |
|---|---|
| SQL injection | prepared statements everywhere (PDO and JDBC); the dynamic `WHERE` clauses are built from fixed fragments with bound parameters; `LIKE` wildcards in search terms are escaped |
| Cross-site scripting | all PHP output through `e()` (`htmlspecialchars`); all JSP output through `<c:out>`; a Content-Security-Policy forbids inline scripts in both apps |
| CSRF | per-session random token checked with a constant-time comparison on every state-changing POST; SameSite=Lax cookies; the public registration API has no session, so it uses an origin allow-list instead |
| Malicious uploads | extension allow-list, server-side MIME detection, image decoding check, PDF signature check, size limits, random names generated by the server, files stored outside the web root, never executed; served with `nosniff` and `sandbox` CSP |
| Directory traversal / arbitrary file read or delete | the URL carries only a database id; the path from the database must match a strict pattern and resolve (after following symlinks) inside the upload root |
| Password theft | salted PBKDF2-HMAC-SHA256; constant-time comparison; generic "wrong username or password" message; Java runs a dummy verification for unknown users |
| Session attacks | new session id after login, HttpOnly cookies, idle timeout, POST-only logout, `no-store` on protected pages |
| Open redirect | the post-login `next` value must be a local path (PHP) or match `/portal/...` (Java) |
| Information leaks | friendly error pages; details only in server logs |

### Known limitations (stated plainly)

* The PHP sign-in lockout counter is stored in the session, so a script that discards cookies is not slowed down. The Java throttle is keyed by username + address (in memory, reset on restart). A production system would use a shared store.
* The public registration endpoint has a honeypot field and origin check but no CAPTCHA or per-IP rate limit.
* Self-registered students have a stored password hash but this assignment has **no student sign-in**; the hash is kept for a future feature.
* The Java portal opens one JDBC connection per query. A pooled `DataSource` would be better for heavy traffic.
* Demo staff passwords are public in this repository by design; change them before any real use.
* Local development uses plain HTTP. Behind HTTPS, set `<secure>true</secure>` in `web.xml` (the PHP cookie becomes `Secure` automatically).

## 7. Important source files

| File | Role |
|---|---|
| `database/schema.sql` | tables, keys, indexes |
| `php-admin/includes/bootstrap.php` | session, security headers, error handler, loads everything |
| `php-admin/includes/validation.php` | server-side rules for student fields and password strength |
| `php-admin/includes/upload.php` | upload inspection, safe storage, safe path resolution, deletion |
| `php-admin/includes/repository.php` | all SQL for the PHP side |
| `php-admin/api/register.php` | public registration endpoint |
| `frontend/js/validation.js` | validation rules (unit-tested with Node) |
| `java-servlet/.../web/AuthFilter.java` | session gate for `/portal/*` |
| `java-servlet/.../web/FileServlet.java` | streams images and documents |
| `java-servlet/.../web/UploadStorage.java` | safe path resolution on the Java side |
| `java-servlet/.../security/PasswordHasher.java` | PBKDF2 compatible with PHP |
| `java-servlet/src/main/webapp/WEB-INF/web.xml` | servlet/filter mappings, sessions, error pages |
| `java-servlet/src/main/webapp/WEB-INF/views/*.jsp` | login, list, profile, error views |

## 8. Conclusion (3 to 5 lines)

The project delivers a responsive, validated registration form, a PHP/MySQL admin panel with full CRUD and safe file handling, and a Java Servlet/JSP/JDBC portal that streams stored images and documents to signed-in users. One normalised schema, one upload folder and one password-hash format keep the PHP and Java sides consistent. Security was designed in from the start: prepared statements, output escaping, CSRF tokens, content-verified uploads, and salted password hashing. The main lesson is that agreeing on shared contracts matters more than the language each part is written in.
