-- Demonstration data. Every person, roll number and e-mail below is fictional.
-- Run AFTER schema.sql:  mysql -u root -p student_portal < database/sample_data.sql
USE student_portal;

-- DEMO staff accounts. Passwords (demo only, change them!): admin / Admin@12345 , staff / Staff@12345
INSERT INTO users (username, full_name, password_hash, role) VALUES
  ('admin', 'Demo Administrator', 'pbkdf2_sha256$120000$ix0m7BfYuTObA27JuFiGHA==$1bTPc1x2oTbBGTqnSV4ZpdYnw+J7jWNyzgrZv3G3mCA=', 'admin'),
  ('staff', 'Demo Staff Member', 'pbkdf2_sha256$120000$ZtQ6zfcC65QCu5Wtgal2Bw==$Hq+X0JFbz33ntuqnuKyH+AXM3ksLRwbBUoAx7QP9D9g=', 'staff');

INSERT INTO courses (code, name, department) VALUES
  ('CSE', 'B.E. Computer Science and Engineering', 'Computer Science'),
  ('ECE', 'B.E. Electronics and Communication Engineering', 'Electronics'),
  ('MECH', 'B.E. Mechanical Engineering', 'Mechanical'),
  ('AIDS', 'B.Tech. Artificial Intelligence and Data Science', 'Computer Science'),
  ('IT', 'B.Tech. Information Technology', 'Computer Science'),
  ('EEE', 'B.E. Electrical and Electronics Engineering', 'Electrical');

INSERT INTO students (roll_number, full_name, email, phone, date_of_birth, gender, course_id, year_of_study, address, status) VALUES
  ('24CSE101', 'Aarthi Subramanian', 'aarthi.s@example.edu', '9876500101', '2005-03-14', 'female', 1, 2, '12 Gandhi Nagar, Coimbatore', 'active'),
  ('24CSE102', 'Karthik Rajan', 'karthik.r@example.edu', '9876500102', '2004-11-02', 'male', 1, 2, '45 Lake View Road, Tiruppur', 'active'),
  ('24ECE201', 'Meenakshi Iyer', 'meenakshi.i@example.edu', '9876500103', '2005-07-21', 'female', 2, 2, '7 Temple Street, Erode', 'active'),
  ('23MEC301', 'Vignesh Palanisamy', 'vignesh.p@example.edu', '9876500104', '2004-01-30', 'male', 3, 3, '88 Mill Road, Salem', 'active'),
  ('25AID401', 'Divya Lakshmi', 'divya.l@example.edu', '9876500105', '2006-05-09', 'female', 4, 1, '3 Park Avenue, Coimbatore', 'pending'),
  ('25IT501', 'Mohammed Farhan', 'farhan.m@example.edu', '9876500106', '2006-09-17', 'male', 5, 1, '21 Fort Road, Pollachi', 'active'),
  ('22EEE601', 'Sangeetha Krishnan', 'sangeetha.k@example.edu', '9876500107', '2003-12-25', 'female', 6, 4, '9 Canal Bank Road, Karur', 'inactive'),
  ('24CSE103', 'Rohit Venkatesh', 'rohit.v@example.edu', '9876500108', '2005-02-11', 'male', 1, 2, '60 Residency Road, Coimbatore', 'active');

-- File metadata. The matching files are in storage/sample_uploads/ (copy them with php-admin/setup.php).
INSERT INTO student_files (student_id, category, original_name, stored_name, relative_path, mime_type, file_size) VALUES
  (1, 'profile_image', 'profile_photo.png', 'e5eb07cd7ecc55ebd6f68ea54764e42e.png', '1/e5eb07cd7ecc55ebd6f68ea54764e42e.png', 'image/png', 7087),
  (1, 'id_proof', 'id_proof_sample.pdf', '0308680257a925d0f080b7af96758d68.pdf', '1/0308680257a925d0f080b7af96758d68.pdf', 'application/pdf', 45811),
  (1, 'certificate', 'certificate_sample.pdf', 'f606d540a27b2b2a863cf47d45d75298.pdf', '1/f606d540a27b2b2a863cf47d45d75298.pdf', 'application/pdf', 46615),
  (2, 'profile_image', 'profile_photo.png', '124fe4ee9497a9fc327b7fe3e20cbd10.png', '2/124fe4ee9497a9fc327b7fe3e20cbd10.png', 'image/png', 5243),
  (2, 'id_proof', 'id_proof_sample.pdf', '415060af4e37b70cf4adadbf7e88ad8b.pdf', '2/415060af4e37b70cf4adadbf7e88ad8b.pdf', 'application/pdf', 44879),
  (3, 'profile_image', 'profile_photo.png', '4b5d0280191b351b44ddb257e9b321a3.png', '3/4b5d0280191b351b44ddb257e9b321a3.png', 'image/png', 2847),
  (3, 'certificate', 'certificate_sample.pdf', '960b0db43ca12ffb488a523bf9c3b47d.pdf', '3/960b0db43ca12ffb488a523bf9c3b47d.pdf', 'application/pdf', 46132),
  (4, 'profile_image', 'profile_photo.png', 'c9da3aa9f978a705d899c07b596e2321.png', '4/c9da3aa9f978a705d899c07b596e2321.png', 'image/png', 4928),
  (5, 'profile_image', 'profile_photo.png', 'fdb5927b70d4c71006a28ddf5472b045.png', '5/fdb5927b70d4c71006a28ddf5472b045.png', 'image/png', 3254),
  (5, 'id_proof', 'id_proof_sample.pdf', 'c0be833eacb4d3f737a7b48247218cbb.pdf', '5/c0be833eacb4d3f737a7b48247218cbb.pdf', 'application/pdf', 45182),
  (7, 'profile_image', 'profile_photo.png', 'f12c63e5f6307dda77b246e28bc21979.png', '7/f12c63e5f6307dda77b246e28bc21979.png', 'image/png', 6769),
  (7, 'certificate', 'certificate_sample.pdf', 'ad3dccfd7d8efb65dd2ffca6850a4614.pdf', '7/ad3dccfd7d8efb65dd2ffca6850a4614.pdf', 'application/pdf', 47206),
  (8, 'profile_image', 'profile_photo.png', '9f06e021fe914f56430a5415b06f4325.png', '8/9f06e021fe914f56430a5415b06f4325.png', 'image/png', 5653),
  (8, 'id_proof', 'id_proof_sample.pdf', '9f51cce463426b5d051340af548374a7.pdf', '8/9f51cce463426b5d051340af548374a7.pdf', 'application/pdf', 45585),
  (8, 'certificate', 'certificate_sample.pdf', '6897cae60617bc59bbaaf17c0c168211.pdf', '8/6897cae60617bc59bbaaf17c0c168211.pdf', 'application/pdf', 46399);
