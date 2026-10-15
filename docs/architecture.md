# Architecture

## Components and data flow

```
                         ┌────────────────────────────┐
  student ─ browser ───► │ A. frontend (static files) │  http://localhost:8000
                         └─────────────┬──────────────┘
                                       │ POST multipart/form-data (no cookies)
                                       ▼
  staff ─── browser ───► ┌────────────────────────────┐        ┌──────────────────────────┐
                         │ B. PHP admin  :8080        │◄──────►│  MySQL  student_portal   │
                         │  sessions: ADMINSESSID     │  PDO   │  users, courses,         │
                         └─────────────┬──────────────┘        │  students, student_files │
                                       │ read/write            └──────────▲───────────────┘
                                       ▼                                  │ JDBC
                         ┌────────────────────────────┐        ┌──────────┴───────────────┐
  staff ─── browser ───► │ storage/uploads/  (shared) │◄───────│ C. Java portal :8081     │
                         │ outside every web root     │ stream │  sessions: JSESSIONID    │
                         └────────────────────────────┘        └──────────────────────────┘
```

## Request flows

**Registration (A → B).** The form validates in the browser, then sends `FormData` to `php-admin/api/register.php`. The endpoint checks the `Origin` header against `ALLOWED_ORIGINS`, validates every field and file again, and inside one database transaction inserts the student (`status = pending`, hashed password) and the file metadata. If anything fails the transaction is rolled back and any files already written are deleted.

**Admin CRUD (B).** Every page calls `require_login()`. State-changing forms carry a CSRF token. Uploaded files are stored as `storage/uploads/<student id>/<random>.<ext>`, and the page `students/file.php?id=N` streams them after looking up row N.

**Student profiles (C).** `AuthFilter` guards `/portal/*`. `StudentListServlet` and `StudentDetailServlet` read the database through the DAOs and forward to JSPs. Each `<img>` points to `/portal/file?id=N`; `FileServlet` loads row N, resolves its path inside the upload folder, and streams the bytes.

## Authentication model

| | PHP admin | Java portal |
|---|---|---|
| Credentials | `users` table | same `users` table |
| Password hash | PBKDF2-HMAC-SHA256 (`pbkdf2_sha256$...`) | same format, verified by `PasswordHasher` |
| Session cookie | `ADMINSESSID` | `JSESSIONID` |
| Shared session? | **No.** The two applications never see each other's sessions. Signing in to one does not sign you in to the other. | |

Browsers share cookies per host, not per port. The two cookies have different names, so they do not collide on `localhost`.

## Why these choices

* **Uploads outside the web root + a streaming script/servlet:** a file can never be fetched, or executed, by guessing a URL, and access control applies to every file.
* **Relative paths in the database:** the same row works for PHP and Java regardless of where the project folder is on disk.
* **One properties file read by both languages:** one place to change the database password or upload folder.
* **PBKDF2 instead of bcrypt:** PHP and the Java standard library both implement it, so neither side needs a third-party package and the hashes are interchangeable.
