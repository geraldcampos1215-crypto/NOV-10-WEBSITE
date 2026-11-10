# JobSeeker – Online Job Portal & Recruitment Management System
PHP 8+ · MySQL (PDO) · Bootstrap 5 · JavaScript · Chart.js

## Install on XAMPP (Windows / macOS / Linux)
1. Install XAMPP (PHP 8.0 or newer) and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the `jobseeker` folder into `C:\xampp\htdocs\` (so you have `C:\xampp\htdocs\jobseeker\index.php`).
3. Open http://localhost/phpmyadmin → **Import** tab → choose `database/jobseeker_db.sql` → **Go**.
   (This creates the `jobseeker_db` database, all 14 tables and the sample data.)
4. Open `config/config.php` and check:
   - `DB_USER` / `DB_PASS` (XAMPP default: `root` and an empty password)
   - `BASE_URL` (default `/jobseeker`; change it if you renamed the folder)
5. Make sure `assets/uploads/profiles` and `assets/uploads/resumes` are writable (default on Windows).
6. Visit **http://localhost/jobseeker/**
7. Optional: put a hero photo at `assets/images/hero.jpg` (a blue gradient is shown if it is missing).
8. Open the folder in VS Code to edit. Bootstrap, Bootstrap Icons and Chart.js load from CDN (internet needed).

## Demo accounts (password for all: `Password@123`)
| Role       | Login                          |
|------------|--------------------------------|
| Admin      | admin@jobseeker.com            |
| Staff      | maria@jobseeker.com (TechSolutions), paolo@jobseeker.com (Global Call Center) |
| Job Seeker | juan@example.com, ana@example.com, carlo@example.com |
You can log in with the email or the username (`admin`, `maria`, `juan` …).

## Portals
- **Public**: Home, Browse/Search/Filter jobs (AJAX), Job details, About, Contact, Login, Register, Forgot/Reset password
- **Job Seeker** `/seeker/`: dashboard, profile (picture, resume, completion %, change password), find jobs, apply, my applications (live status), saved jobs, notifications
- **Staff** `/staff/`: dashboard, job CRUD, applications (review, shortlist, approve/reject, download resume), interviews, announcements, reports (Chart.js), notifications
- **Admin** `/admin/`: dashboard + user-growth chart, users (activate/deactivate, role change, delete), staff, jobs (feature), applications, companies, categories, reports, activity logs, system settings, role matrix

Staff only see the jobs/applications of the company they are assigned to (set in Admin → Staff Management).

## Security implemented
PDO prepared statements everywhere · password_hash/password_verify · CSRF token on every POST · output escaping (XSS) ·
role-based access on every protected page · login lockout (5 failed attempts → 15 min) · session regeneration + 30-min inactivity timeout ·
upload validation (extension, MIME type, size, random file names) · resumes stored in a folder blocked by .htaccess and served only through `download.php` with permission checks.

## Notes
- Home-page statistics are live counts from the database (they grow as you add data).
- Email notifications use PHP `mail()`; plain XAMPP has no mail server, so they are silently skipped. In-app notifications always work.
- "Forgot password" shows the reset link on screen because XAMPP cannot send email locally. Remove that block in `forgot-password.php` in production.
- For production: set `display_errors` to 0 (already default), use HTTPS and change all demo passwords.
