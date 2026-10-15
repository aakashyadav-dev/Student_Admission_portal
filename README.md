# Student Admission and Profile Management Portal

| | |
|---|---|
| **Course** | U21CS501 Web Technology, Assignment II |
| **Institution** | KPR Institute of Engineering and Technology, CSE, 2026-27 |
| **Student** | Aakash Yadav |
| **Roll number** | 24CS281 |
| **Section** | CSE-B |
| **GitHub repository** | https://github.com/aakashyadav-dev/Student_Admission_portal |

A college admission system in three cooperating parts that share **one MySQL database** and **one upload folder**:

| Part | Assignment question | Technology | Default URL |
|---|---|---|---|
| A. Registration form | Q1 (8 marks) | HTML5, CSS3, JavaScript | http://localhost:8000 |
| B. Admin panel (CRUD, uploads) | Q2 (16 marks) | PHP 8.1+, MySQL (PDO) | http://localhost:8080 |
| C. Student profile portal | Q3 (16 marks) | Java Servlets, JSP/JSTL, JDBC, Tomcat 10.1 | http://localhost:8081/student-portal |

```
 Browser ──► A. frontend (static)  ── POST /api/register.php ──►┐
 Browser ──► B. PHP admin panel  ───────────── PDO ─────────────┼──► MySQL "student_portal"
 Browser ──► C. Java servlet portal ────────── JDBC ────────────┘     (users, courses, students, student_files)
                 B and C both read/write ───► storage/uploads/   (outside every web root)
```

## What each part does

**A. Registration (`frontend/`)**: responsive form with live validation (required fields, e-mail format, password strength meter and checklist, matching passwords, file type and size), image preview, accessible error messages, and a real submission to the PHP API. Without the API it falls back to a clearly labelled demo mode.

**B. PHP admin (`php-admin/`)**: staff sign-in, dashboard, searchable and paginated student list, add / view / edit / delete (with a confirmation page), upload of profile image, ID proof, certificates and other documents, metadata stored in MySQL, files shown on the profile page, plus a public JSON registration endpoint.

**C. Java portal (`java-servlet/`)**: independent sign-in with hashed passwords, session-protected pages, a JSP student list (loops, conditionals, filters, pagination), a profile page, and a servlet that streams images and documents from the shared upload folder through `<img>` and download links.

## Quick start (details in SETUP_GUIDE.md)

```bash
cp config/portal.properties.example config/portal.properties      # then edit DB_USER / DB_PASS
mysql -u root -p < database/schema.sql && mysql -u root -p < database/sample_data.sql
mkdir -p storage/uploads && cp -R storage/sample_uploads/* storage/uploads/
php -d upload_max_filesize=6M -d post_max_size=24M -S localhost:8080 -t php-admin php-admin/router.php   # B
python3 -m http.server 8000 --directory frontend                                                    # A
cd java-servlet && mvn clean package   # then deploy target/student-portal.war to Tomcat 10.1 on port 8081 (C)
```

Demo staff logins (seed data, **change before any real use**): `admin` / `Admin@12345` and `staff` / `Staff@12345`.

## Folder structure

```
Student_Admission_Portal/
├── README.md, SETUP_GUIDE.md, DOCUMENTATION.md, SUBMISSION_CHECKLIST.md, .gitignore
├── config/portal.properties.example     shared settings for PHP and Java (copy to portal.properties)
├── database/schema.sql, sample_data.sql
├── frontend/                            Q1: index.html, css/, js/ (validation.js, app.js, config.js), assets/, tests/
├── php-admin/                           Q2
│   ├── index.php, login.php, logout.php, router.php
│   ├── api/register.php                 public registration endpoint used by the form
│   ├── students/                        index, add, edit, view, delete, delete_file, file, _form
│   ├── includes/                        bootstrap, auth, csrf, validation, upload, repository, helpers, header, footer
│   ├── config/                          config.php, database.php
│   ├── bin/setup.php                    CLI: check / install / passwd
│   └── assets/                          css, js, logo
├── java-servlet/                        Q3 (Maven WAR)
│   ├── pom.xml
│   └── src/{main,test}/
│       ├── java/edu/kpriet/portal/      config, db, model, dao, security, web (servlets + filters)
│       └── webapp/                      assets/, META-INF/context.xml, WEB-INF/{web.xml, views/}
├── storage/sample_uploads/              demo images/PDFs referenced by sample_data.sql
├── storage/uploads/                     the live upload folder (created/filled at run time; git-ignored)
├── screenshots/                         real browser screenshots + README.md (what you still need to capture)
├── docs/architecture.md, docs/TEST_REPORT.md
└── tools/                               test and helper scripts (screenshots, static checks, hashing, sample data)
```
## Screenshot 

All the screenshot are present on Screenshots folder. 

## Assignment conclusion

This project joins a validated HTML/CSS/JavaScript registration form, a PHP/MySQL administration panel and a Java Servlet/JSP/JDBC profile portal around a single normalised database and upload folder. Every query uses prepared statements, every output is escaped, uploads are verified by content and stored under random names outside the web root, and each application authenticates users with salted PBKDF2 hashes and CSRF-protected forms. Building the PHP and Java sides against one schema showed why a shared contract (table design, hash format, file-path rules) matters more than the language used on either side.
