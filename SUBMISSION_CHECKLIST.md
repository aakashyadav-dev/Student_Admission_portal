# Submission checklist (U21CS501 Assignment II, due 20.10.2026)

Status key: **Built + tested** = run and checked in this project; **Built, run locally** = complete code, not yet executed (no PHP/MySQL/Tomcat was available when it was built), so tick it yourself after the smoke test in SETUP_GUIDE.md section 6.

## Question 1: Registration form (8 marks, CO3)

| Requirement | Where | Status |
|---|---|---|
| HTML form: name, roll number, e-mail, course, file upload | `frontend/index.html` | Built + tested (browser) |
| CSS: layout, colours, responsive design, styled inputs/buttons/error messages | `frontend/css/base.css`, `register.css` | Built + tested at 1280, 820, 390 px |
| JS validation: required fields | `frontend/js/validation.js`, `app.js` | Built + tested |
| JS validation: e-mail format | same | Built + tested |
| JS validation: password strength (and confirm) | same | Built + tested |
| File type and size validation, preview | same | Built + tested |
| Saves to a working backend | `php-admin/api/register.php` | Built, run locally |

## Question 2: PHP + MySQL (16 marks, CO4)

| Requirement | Where | Status |
|---|---|---|
| Add / Edit / Delete / View (list and single) | `php-admin/students/*.php` | Built, run locally |
| Prepared statements, unique roll numbers | `includes/repository.php`, `schema.sql` (`UNIQUE`) | SQL checked against schema; run locally |
| File upload: profile image, ID proof, certificates | `students/_form.php`, `includes/upload.php` | Built, run locally |
| Metadata in MySQL (path, type, upload date, linked to student) | `student_files` table, `file_insert()` | Schema + SQL checked; run locally |
| Display uploaded files on the details page | `students/view.php`, `students/file.php` | Built, run locally |
| Validation: size, type, no overwriting | `includes/upload.php` (random names) | Built, run locally |
| CSRF, XSS escaping, safe delete, error handling | `includes/csrf.php`, `helpers.php::e()`, `repository.php` | Built, run locally |
| Setup script + SQL schema + sample data | `php-admin/bin/setup.php`, `database/` | SQL checked; script run locally |

## Question 3: Servlets + JDBC (16 marks, CO5)

| Requirement | Where | Status |
|---|---|---|
| JDBC connection to MySQL, fetch details + file paths | `db/Database.java`, `dao/*.java` | Compiled (stub API); run locally |
| Retrieve and stream images/documents from the folder | `web/FileServlet.java`, `UploadStorage.java` | Path safety unit-tested; streaming run locally |
| HTML/JSP page with `<img>` pointing to the servlet | `views/students.jsp`, `student.jsp` | Built, run locally |
| Looping and conditional logic over multiple students/files | same (`c:forEach`, `c:choose`, `c:if`) | Built, run locally |
| Session handling: only logged-in users can access | `web/AuthFilter.java`, `LoginServlet`, `LogoutServlet` | Access control unit-tested; run locally |
| Login/logout, password hashing | `security/PasswordHasher.java` | Unit-tested incl. PHP-compatible hashes |
| Missing files, invalid requests, DB errors handled | `FileServlet`, `error.jsp`, `web.xml` | Built, run locally |
| Correct `WEB-INF` and mappings | `WEB-INF/web.xml` (Servlet 6.0) | XML well-formed; run locally |

## Required submission items (assignment sheet)

| Item | Status |
|---|---|
| PHP files, Servlet / WEB-INF files, .html .css .js | In the ZIP |
| DB schema script | `database/schema.sql` (+ `sample_data.sql`) |
| **Screenshots of output in the browser** | Registration form: 8 real screenshots in `screenshots/`. **PHP and Servlet screenshots are yours to take** (list in `screenshots/README.md`). |
| Explanation of code with inline comments | Inline comments in source; `DOCUMENTATION.md` |
| Brief conclusion (3 to 5 lines) | `DOCUMENTATION.md` section 8 and `README.md` |
| GitHub repository link | **Placeholder until you create and push the repository** (SETUP_GUIDE.md section 8) |

## What you must personalize before submitting

- [ ] Replace `<YOUR NAME>`, `<YOUR ROLL NUMBER>` and `<PASTE YOUR REPOSITORY URL HERE>` at the top of `README.md`.
- [ ] Run the smoke test (SETUP_GUIDE.md section 6) and fix anything that differs on your Mac.
- [ ] Capture the PHP and Servlet screenshots listed in `screenshots/README.md`.
- [ ] Change the demo staff passwords (`php php-admin/bin/setup.php passwd admin`), or state in your report that they are demo credentials.
- [ ] Create the GitHub repository, push, and paste the real URL in the README and your cover page.
- [ ] Read the code so you can explain it: Section 7 of DOCUMENTATION.md lists the important files. The sheet says this is an *individual* assignment and you must be able to explain your own submission.
