-- ============================================================================
-- Student Admission and Profile Management Portal -- database schema
-- Shared by the PHP admin panel and the Java servlet portal.
-- Requires MySQL 8.0+ (or MariaDB 10.5+).  Import with:
--     mysql -u root -p < database/schema.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS student_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_portal;

-- Drop in dependency order so the script can be re-run on a scratch database.
DROP TABLE IF EXISTS student_files;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS users;

-- Staff accounts that may sign in to the PHP admin panel and the Java portal.
-- password_hash format (both languages understand it):
--   pbkdf2_sha256$<iterations>$<base64 salt>$<base64 hash>
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB;

-- Lookup table: one row per course. Keeps course data out of the students table (3NF).
CREATE TABLE courses (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)  NOT NULL,
  name       VARCHAR(120) NOT NULL,
  department VARCHAR(80)  NOT NULL,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_courses_code (code)
) ENGINE=InnoDB;

CREATE TABLE students (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  roll_number    VARCHAR(20)  NOT NULL,
  full_name      VARCHAR(100) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  phone          VARCHAR(20)  NULL,
  date_of_birth  DATE         NULL,
  gender         ENUM('female','male','other') NULL,
  course_id      INT UNSIGNED NOT NULL,
  year_of_study  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  address        VARCHAR(500) NULL,
  status         ENUM('pending','active','inactive') NOT NULL DEFAULT 'active',
  password_hash  VARCHAR(255) NULL,   -- set only for self-registered students
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_students_roll  (roll_number),   -- enforces unique roll numbers
  UNIQUE KEY uq_students_email (email),
  KEY idx_students_course (course_id),
  KEY idx_students_status (status),
  CONSTRAINT fk_students_course FOREIGN KEY (course_id) REFERENCES courses (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- File metadata only; the bytes live in the shared UPLOAD_DIR on disk.
-- relative_path is "<student_id>/<stored_name>" and is resolved against UPLOAD_DIR.
CREATE TABLE student_files (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id    INT UNSIGNED NOT NULL,
  category      ENUM('profile_image','id_proof','certificate','other') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(80)  NOT NULL,
  relative_path VARCHAR(160) NOT NULL,
  mime_type     VARCHAR(100) NOT NULL,
  file_size     INT UNSIGNED NOT NULL,
  uploaded_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_files_stored_name (stored_name),   -- no two rows can point at one stored file
  KEY idx_files_student (student_id, category),
  CONSTRAINT fk_files_student FOREIGN KEY (student_id) REFERENCES students (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Optional: a least-privilege account for the applications (edit the password!).
-- CREATE USER 'portal_app'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON student_portal.* TO 'portal_app'@'localhost';
