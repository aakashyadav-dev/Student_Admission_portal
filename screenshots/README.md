# Screenshots

## Already here (real, taken with headless Chromium by `tools/take_frontend_screenshots.js`)

These show the **registration form (Question 1) running in demo mode**, so no backend was needed. They are genuine output of the page in `frontend/`.

| File | Shows |
|---|---|
| `01_registration_form_desktop.png` | empty form, desktop |
| `02_validation_errors_desktop.png` | required-field errors after pressing Submit |
| `03_validation_mixed_errors.png` | invalid e-mail, weak password, mismatched passwords, wrong file type |
| `04_valid_form_with_previews.png` | valid input, strength meter, photo and document previews |
| `05_success_demo_mode.png` | success message (demo mode: nothing is saved) |
| `06_registration_mobile.png`, `07_validation_errors_mobile.png` | 390 px phone layout |
| `08_registration_tablet.png` | 820 px tablet layout |

## You still need to capture (the PHP and Servlet parts could not be run when this project was built)

Do the smoke test in SETUP_GUIDE.md section 6 and take a browser screenshot of each. Suggested file names:

| File name | Page |
|---|---|
| `10_php_login.png` | http://localhost:8080/login.php |
| `11_php_dashboard.png` | http://localhost:8080/ |
| `12_php_student_list.png` | `students/index.php` |
| `13_php_add_student.png` | `students/add.php` (ideally with a validation error visible) |
| `14_php_student_profile_files.png` | `students/view.php?id=1` showing uploaded files |
| `15_php_upload_rejected.png` | a rejected upload (wrong type or too large) |
| `16_php_delete_confirmation.png` | `students/delete.php?id=...` |
| `17_registration_saved_pending.png` | the form submitted for real, then the student showing as *pending* in the admin list |
| `20_java_login.png` | http://localhost:8081/student-portal/login |
| `21_java_student_list.png` | `/portal/students` with photos |
| `22_java_student_profile.png` | `/portal/student?id=1` with image and document links |
| `23_java_redirect_when_logged_out.png` | visiting a `/portal/...` URL signed out lands on the login page |
| `24_java_not_found.png` | `/portal/file?id=9999` error page |

Only submit screenshots of the real running application.
