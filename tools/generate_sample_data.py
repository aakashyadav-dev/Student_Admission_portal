#!/usr/bin/env python3
"""Regenerates database/sample_data.sql and the placeholder files in storage/sample_uploads/.

All people, roll numbers and e-mail addresses below are fictional. The "documents" are
clearly marked SAMPLE images/PDFs drawn with Pillow -- they are not real certificates.
Run from the project root:  python3 tools/generate_sample_data.py
"""
import hashlib, os, sys
from PIL import Image, ImageDraw, ImageFont

sys.path.insert(0, os.path.dirname(__file__))
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_DIR = os.path.join(ROOT, "storage", "sample_uploads")

# Hashes for the DEMO staff accounts (passwords are documented in README: demo use only).
ADMIN_HASH = "pbkdf2_sha256$120000$ix0m7BfYuTObA27JuFiGHA==$1bTPc1x2oTbBGTqnSV4ZpdYnw+J7jWNyzgrZv3G3mCA="
STAFF_HASH = "pbkdf2_sha256$120000$ZtQ6zfcC65QCu5Wtgal2Bw==$Hq+X0JFbz33ntuqnuKyH+AXM3ksLRwbBUoAx7QP9D9g="

COURSES = [
    ("CSE", "B.E. Computer Science and Engineering", "Computer Science"),
    ("ECE", "B.E. Electronics and Communication Engineering", "Electronics"),
    ("MECH", "B.E. Mechanical Engineering", "Mechanical"),
    ("AIDS", "B.Tech. Artificial Intelligence and Data Science", "Computer Science"),
    ("IT", "B.Tech. Information Technology", "Computer Science"),
    ("EEE", "B.E. Electrical and Electronics Engineering", "Electrical"),
]
# roll, name, email, phone, dob, gender, course code, year, address, status, files
STUDENTS = [
    ("24CSE101", "Aarthi Subramanian", "aarthi.s@example.edu", "9876500101", "2005-03-14", "female", "CSE", 2, "12 Gandhi Nagar, Coimbatore", "active", ["profile_image", "id_proof", "certificate"]),
    ("24CSE102", "Karthik Rajan", "karthik.r@example.edu", "9876500102", "2004-11-02", "male", "CSE", 2, "45 Lake View Road, Tiruppur", "active", ["profile_image", "id_proof"]),
    ("24ECE201", "Meenakshi Iyer", "meenakshi.i@example.edu", "9876500103", "2005-07-21", "female", "ECE", 2, "7 Temple Street, Erode", "active", ["profile_image", "certificate"]),
    ("23MEC301", "Vignesh Palanisamy", "vignesh.p@example.edu", "9876500104", "2004-01-30", "male", "MECH", 3, "88 Mill Road, Salem", "active", ["profile_image"]),
    ("25AID401", "Divya Lakshmi", "divya.l@example.edu", "9876500105", "2006-05-09", "female", "AIDS", 1, "3 Park Avenue, Coimbatore", "pending", ["profile_image", "id_proof"]),
    ("25IT501", "Mohammed Farhan", "farhan.m@example.edu", "9876500106", "2006-09-17", "male", "IT", 1, "21 Fort Road, Pollachi", "active", []),
    ("22EEE601", "Sangeetha Krishnan", "sangeetha.k@example.edu", "9876500107", "2003-12-25", "female", "EEE", 4, "9 Canal Bank Road, Karur", "inactive", ["profile_image", "certificate"]),
    ("24CSE103", "Rohit Venkatesh", "rohit.v@example.edu", "9876500108", "2005-02-11", "male", "CSE", 2, "60 Residency Road, Coimbatore", "active", ["profile_image", "id_proof", "certificate"]),
]
PALETTE = [(31, 76, 99), (15, 118, 110), (146, 64, 14), (91, 33, 182), (159, 18, 57), (22, 101, 52), (30, 64, 175), (120, 53, 15)]

def font(size):
    for p in ("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf"):
        if os.path.exists(p):
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()

def avatar(path, name, colour):
    img = Image.new("RGB", (400, 400), colour)
    d = ImageDraw.Draw(img)
    initials = "".join(w[0] for w in name.split()[:2]).upper()
    f = font(150)
    box = d.textbbox((0, 0), initials, font=f)
    d.text(((400 - (box[2] - box[0])) / 2 - box[0], (400 - (box[3] - box[1])) / 2 - box[1]), initials, fill="white", font=f)
    img.save(path, "PNG", optimize=True)

def document(path, title, name, roll, colour):
    img = Image.new("RGB", (794, 1123), "white")
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, 794, 140], fill=colour)
    d.text((48, 48), "SAMPLE DOCUMENT - NOT A REAL RECORD", fill="white", font=font(28))
    d.text((48, 220), title, fill=(20, 30, 50), font=font(40))
    d.text((48, 300), "Student: " + name, fill=(60, 60, 60), font=font(26))
    d.text((48, 345), "Roll number: " + roll, fill=(60, 60, 60), font=font(26))
    d.text((48, 430), "Generated for demonstration of the Student Admission", fill=(110, 110, 110), font=font(22))
    d.text((48, 465), "and Profile Management Portal.", fill=(110, 110, 110), font=font(22))
    img.save(path, "PDF", resolution=100.0)

def q(s):
    return "NULL" if s is None else "'" + str(s).replace("\\", "\\\\").replace("'", "''") + "'"

def main():
    os.makedirs(OUT_DIR, exist_ok=True)
    sql = ["-- Demonstration data. Every person, roll number and e-mail below is fictional.",
           "-- Run AFTER schema.sql:  mysql -u root -p student_portal < database/sample_data.sql", "USE student_portal;", ""]
    sql.append("-- DEMO staff accounts. Passwords (demo only, change them!): admin / Admin@12345 , staff / Staff@12345")
    sql.append("INSERT INTO users (username, full_name, password_hash, role) VALUES")
    sql.append("  ('admin', 'Demo Administrator', %s, 'admin')," % q(ADMIN_HASH))
    sql.append("  ('staff', 'Demo Staff Member', %s, 'staff');\n" % q(STAFF_HASH))
    sql.append("INSERT INTO courses (code, name, department) VALUES")
    sql.append(",\n".join("  (%s, %s, %s)" % (q(c), q(n), q(d)) for c, n, d in COURSES) + ";\n")
    code_to_id = {c[0]: i + 1 for i, c in enumerate(COURSES)}
    sql.append("INSERT INTO students (roll_number, full_name, email, phone, date_of_birth, gender, course_id, year_of_study, address, status) VALUES")
    sql.append(",\n".join("  (%s, %s, %s, %s, %s, %s, %d, %d, %s, %s)" % (q(s[0]), q(s[1]), q(s[2]), q(s[3]), q(s[4]), q(s[5]), code_to_id[s[6]], s[7], q(s[8]), q(s[9])) for s in STUDENTS) + ";\n")
    rows = []
    for idx, s in enumerate(STUDENTS, start=1):
        for cat in s[10]:
            ext = "png" if cat == "profile_image" else "pdf"
            stored = hashlib.md5(("sample-%d-%s" % (idx, cat)).encode()).hexdigest() + "." + ext
            folder = os.path.join(OUT_DIR, str(idx))
            os.makedirs(folder, exist_ok=True)
            path = os.path.join(folder, stored)
            if cat == "profile_image":
                avatar(path, s[1], PALETTE[idx - 1])
                original, mime = "profile_photo.png", "image/png"
            else:
                title = "Identity Proof" if cat == "id_proof" else "Merit Certificate"
                document(path, title, s[1], s[0], PALETTE[idx - 1])
                original, mime = ("id_proof_sample.pdf" if cat == "id_proof" else "certificate_sample.pdf"), "application/pdf"
            rows.append("  (%d, %s, %s, %s, %s, %s, %d)" % (idx, q(cat), q(original), q(stored), q("%d/%s" % (idx, stored)), q(mime), os.path.getsize(path)))
    sql.append("-- File metadata. The matching files are in storage/sample_uploads/ (copy them with php-admin/setup.php).")
    sql.append("INSERT INTO student_files (student_id, category, original_name, stored_name, relative_path, mime_type, file_size) VALUES")
    sql.append(",\n".join(rows) + ";\n")
    with open(os.path.join(ROOT, "database", "sample_data.sql"), "w") as fh:
        fh.write("\n".join(sql))
    print("students:", len(STUDENTS), "files:", len(rows))

main()
