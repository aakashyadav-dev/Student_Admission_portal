# Setup guide (macOS)

Tested target: macOS with [Homebrew](https://brew.sh). Commands assume Apple Silicon or Intel; adjust paths if you installed software another way. Where this guide says "unverified", the step was written from documentation but could not be executed on the machine used to build the project, so watch for small differences on your Mac.

## 0. Prerequisites

```bash
brew install php mysql openjdk@21 maven tomcat@10 node     # node is optional (only for the front-end tests)
brew services start mysql
php -v        # needs 8.1 or newer
java -version # 17 or newer (21 is fine)
mvn -v
```

If `java -version` does not show OpenJDK 21: `sudo ln -sfn $(brew --prefix openjdk@21)/libexec/openjdk.jdk /Library/Java/JavaVirtualMachines/openjdk-21.jdk`.

PHP needs the extensions `pdo_mysql`, `fileinfo` and `mbstring` (all included in Homebrew PHP).

## 1. Shared configuration

```bash
cd Student_Admission_Portal
cp config/portal.properties.example config/portal.properties
```

Edit `config/portal.properties`. The **same file** is read by PHP and by Java:

| Key | Meaning |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME` | MySQL server and database (`student_portal`) |
| `DB_USER`, `DB_PASS` | the application's MySQL account (created in step 2) |
| `UPLOAD_DIR` | shared upload folder; relative paths are resolved from the `config/` folder (default `../storage/uploads`) |
| `MAX_UPLOAD_MB` | upper limit for any single upload |
| `ALLOWED_ORIGINS` | browser origins allowed to call the PHP registration API (default `http://localhost:8000`) |
| `APP_BASE_PATH` | PHP only: URL prefix if the admin panel is not served from the site root (leave empty locally) |

`config/portal.properties` is git-ignored, so your password is never committed.

## 2. Database

```bash
mysql -u root -p < database/schema.sql          # creates database student_portal and the 4 tables (DROPS existing ones)
mysql -u root -p < database/sample_data.sql     # 2 staff users, 6 courses, 8 students, 15 file records
```

Create a least-privilege account for the applications (choose your own password and put it in `config/portal.properties`):

```bash
mysql -u root -p -e "CREATE USER 'portal_app'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON student_portal.* TO 'portal_app'@'localhost';"
```

Copy the demo files that the sample data refers to into the live upload folder:

```bash
mkdir -p storage/uploads && cp -R storage/sample_uploads/* storage/uploads/
```

*Alternative to the three commands above* (needs a MySQL account that may create databases; the script asks you to type `yes` before dropping tables):

```bash
PORTAL_DB_ADMIN_USER=root PORTAL_DB_ADMIN_PASS='your-root-password' php php-admin/bin/setup.php install --sample
php php-admin/bin/setup.php check        # verifies extensions, database connection, upload folder
```

## 3. Part B: PHP admin panel (port 8080)

```bash
php -d upload_max_filesize=6M -d post_max_size=24M -S localhost:8080 -t php-admin php-admin/router.php
```

Open http://localhost:8080 and sign in with `admin` / `Admin@12345`. The two `-d` options matter: PHP's default `upload_max_filesize` is 2 MB, which is smaller than the 5 MB document limit. `router.php` blocks the `config/`, `includes/` and `bin/` folders (on Apache the `.htaccess` files do this).

Change the demo passwords straight away: `php php-admin/bin/setup.php passwd admin`.

**Authentication behaviour (PHP):** staff sign in against the `users` table. The session cookie is `ADMINSESSID` (HttpOnly, SameSite=Lax, regenerated on login), idle sessions expire after 30 minutes, and five wrong passwords in five minutes lock the sign-in form temporarily (the counter is kept in the session, so it slows a normal browser but a script that discards cookies is not stopped; see "Known limitations" in DOCUMENTATION.md). Only the `admin` role may delete students; `staff` may view, add and edit.

## 4. Part A: registration form (port 8000)

```bash
python3 -m http.server 8000 --directory frontend
```

Open http://localhost:8000. With the PHP server from step 3 running, submitting the form creates a **pending** student that appears in the admin panel. `frontend/js/config.js` holds `API_URL` and a `DEMO_MODE` switch (`true` = validate in the browser only, send nothing).

## 5. Part C: Java servlet portal (Tomcat on port 8081)

PHP uses port 8080, and the links in the front end expect Tomcat on **8081**. Change Tomcat's HTTP port once:

```bash
TOMCAT_HOME="$(brew --prefix tomcat@10)/libexec"
sed -i '' 's/port="8080"/port="8081"/' "$TOMCAT_HOME/conf/server.xml"     # unverified path for your brew version: check it exists
```

Tell the application where the shared config file is, using Tomcat's `setenv.sh`:

```bash
echo "export PORTAL_CONFIG=\"$PWD/config/portal.properties\"" > "$TOMCAT_HOME/bin/setenv.sh"
chmod +x "$TOMCAT_HOME/bin/setenv.sh"
```

Build, deploy, start:

```bash
cd java-servlet
mvn clean package                                    # runs the unit tests, builds target/student-portal.war (needs internet the first time)
cp target/student-portal.war "$TOMCAT_HOME/webapps/"
"$TOMCAT_HOME/bin/catalina.sh" run                   # runs in the foreground; stop with Ctrl+C
```

Open http://localhost:8081/student-portal and sign in with `admin` / `Admin@12345`.

Compatibility note: the WAR targets **Jakarta EE 10 (Servlet 6.0, JSP 3.1, JSTL 3.0)**, which needs **Tomcat 10.1.x**. Tomcat 9 and older use the `javax.*` packages and will not run it. Java 17 or newer is required (Tomcat 10.1 itself needs Java 11+).

**Authentication behaviour (Java):** the servlet portal has its own sign-in, session (`JSESSIONID`, HttpOnly, SameSite=Lax, new id after login, 30-minute idle timeout) and logout (POST + CSRF token, session invalidated). It verifies the same PBKDF2 hashes as PHP, but **PHP and Java do not share sessions**: signing in to one never signs you in to the other.

## 6. Smoke test to run before you submit

Run these once on your Mac; they exercise the parts that could not be run when the project was built. Tick them off in SUBMISSION_CHECKLIST.md.

1. `php php-admin/bin/setup.php check` reports OK for extensions, database and upload folder.
2. Admin panel: sign in, open Students (8 records with photos), open a profile (images + document download links work).
3. Add a student with a profile image and a PDF; edit it; remove the PDF; delete the student (confirmation page). Check `storage/uploads/` gains and loses the files.
4. Upload `.php` renamed to `.png`, a 6 MB PDF and a text file: each must be rejected with a clear message.
5. Registration form: submit a new student; confirm it shows as **pending** in the admin panel. Submit the same roll number again: the form shows "already registered".
6. Java portal: visiting http://localhost:8081/student-portal/portal/students while signed out redirects to the login page; after signing in, photos load; Sign out; press Back: the login page, not the list.
7. Java portal: http://localhost:8081/student-portal/portal/file?id=9999 shows the "Not found" page; `?id=abc` shows "Invalid request".
8. Stop MySQL and reload a page in each app: you should get a friendly error page, not a stack trace.

## 7. Taking the screenshots

See `screenshots/README.md` for the list of screenshots the assignment expects and the exact steps.

## 8. Publishing to GitHub

1. Create an empty repository on https://github.com/new (no README, no .gitignore, no licence). Choose public or private as your course requires.
2. In the project folder:
   ```bash
   git init
   git add .
   git status                       # confirm config/portal.properties is NOT listed
   git commit -m "U21CS501 Assignment II: Student Admission and Profile Management Portal"
   git branch -M main
   git remote add origin https://github.com/<your-username>/<your-repository>.git
   git push -u origin main
   ```
3. Paste the repository URL into the top of `README.md`, into the cover page of your submission, and into SUBMISSION_CHECKLIST.md, then commit and push again. The URL is a placeholder until you do this; nothing has been pushed for you.

## Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| "Configuration file not found" (PHP) | You did not copy `portal.properties.example` to `portal.properties`. |
| Java pages show "Something went wrong" | `PORTAL_CONFIG` not set for Tomcat (step 5), wrong DB password, or MySQL stopped. Read `$TOMCAT_HOME/logs/catalina.out`. |
| `SQLException ... Public Key Retrieval` | Already handled by `allowPublicKeyRetrieval=true` in the default JDBC parameters; override with `DB_JDBC_PARAMS` if you use TLS. |
| Large uploads rejected | Start PHP with the `-d upload_max_filesize` / `post_max_size` options from step 3. |
| Images missing in the Java portal | The files were not copied into `storage/uploads/` (step 2), or `UPLOAD_DIR` differs between PHP and Java (they read the same file, so check it once). |
| Registration form says it "could not reach the server" | PHP server not running on port 8080, or the form is opened from an origin not listed in `ALLOWED_ORIGINS`. |
