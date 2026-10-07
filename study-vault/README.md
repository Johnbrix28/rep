# Study Vault: Thesis and Research System

A digital research repository for **Information Technology** students and alumni.
Anyone approved can search the archive and read abstracts for free. Reading a **full
manuscript** needs an active paid access plan, and it opens only inside a
**protected, watermarked viewer**.

PHP 8+ · MySQL/MariaDB · runs on XAMPP.

> **Payment is a PROTOTYPE.** No payment gateway is connected and no real money moves.
> Checkout generates a demo reference code and activates the plan for demonstration.
> A real provider must be integrated before production use (see section 9).

---

## 1. Roles

| Role | Can do |
|---|---|
| **Student / Alumni** | Register, log in (after approval), browse and search *approved* research, read abstracts, view Access Plans, activate a demo plan, read full manuscripts with an active paid plan. |
| **Administrator** | Approve/reject registrations, manage students, upload / edit / delete / approve / reject research, manage categories, manage plan prices and durations, view subscriptions and activity logs, view dashboard statistics. |

## 2. Email-based login and IT-only registration

- Users log in with **Email + Password**. Student ID is *not* a login credential; it is
  kept on the profile so administrators can verify identity.
- Registration fields: Full Name, Email, Student ID, Program, User Type
  (*Enrolled IT Student* or *IT Alumni*), Password, Confirm Password.
- The email is validated on the server and must be unique. Passwords are stored with
  `password_hash()` (bcrypt); minimum 8 characters with a letter and a number.
- **IT-only:** the chosen program must be flagged `is_it_program = 1` in the `programs`
  table, otherwise registration is refused. This is a self-declaration, so the
  administrator should compare the Student ID against school records before approving.

## 3. Account approval

New accounts start as **Pending**. Admin → Students has Approve / Reject / Delete
buttons and status tabs. Pending and rejected users cannot log in. Status is re-checked
in the database on every request, so rejecting a signed-in user takes effect at once.

## 4. Access plans

| Plan | Price | Full manuscripts |
|---|---|---|
| Free Access | ₱0 | No (browse, search, abstracts) |
| Research Access | ₱99 / 30 days | Yes |
| Research Access Plus | ₱249 / 90 days | Yes (longer period) |

Admins edit names, prices, durations and visibility at **Admin → Subscriptions → Access
plans**. Existing subscriptions keep their original dates when a plan changes.
Expired subscriptions are marked `expired` automatically, and access is decided by
comparing dates, never by trusting a stored flag. Buying a plan while one is active
adds the new period after the current one.

## 5. Manuscript workflow

`Upload → Pending → Admin review → Approved → visible in the archive`
(or `→ Rejected → hidden`). Every upload starts **Pending**. Students only ever see
**Approved** records (search, details, suggestions, API). The research table already has
submitter, submitted/reviewed dates and review-note columns, so a future
"student submits thesis before clearance" module can be added without schema changes.

## 6. Protected viewing and watermark

- PDFs are stored in `storage/manuscripts/`, which Apache refuses to serve. There is no
  public URL to a PDF.
- `secure-document.php?id=N` re-checks **every** rule on **every** request: logged in →
  account approved → research approved → active, unexpired paid plan. Then it streams the
  PDF with `Content-Type: application/pdf`, `Content-Disposition: inline`,
  `Cache-Control: no-store`. Admins may open any manuscript.
- `view-manuscript.php?id=N` is the viewer. It renders pages with pdf.js and shows
  **STUDY VAULT · Authorized User: email · Student ID · CONFIDENTIAL** tiled across the
  document. The watermark is drawn into the page pixels *and* as an overlay.
- No download button is provided. **Limits, stated honestly:** a watermark discourages
  and helps trace sharing; it cannot stop screenshots or photographing a screen. Browser
  developer tools can still save a response the browser has received. pdf.js loads from
  `cdnjs.cloudflare.com`; if offline, the viewer falls back to the browser's built-in PDF
  viewer (the DOM watermark overlay still shows).

## 7. Setup on XAMPP

1. Extract this folder to `C:\xampp\htdocs\study-vault`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open <http://localhost/phpmyadmin>:
   - **New install:** Import `database/study_vault.sql` (creates the database).
   - **Existing Study Vault database:** back it up, select `study_vault`, and import
     `database/upgrade.sql` **once**. Existing research stays Approved; existing students
     stay Approved but need a login email (Admin → Students → "Set login email").
4. Upgrading? Existing PDFs may still sit in `assets/uploads/`; copy them to
   `storage/manuscripts/` (the app finds either folder, and both are blocked from the web).
5. Open <http://localhost/study-vault/>. If your folder name or MySQL password differs,
   edit `config/database.php`.
6. In `C:\xampp\apache\conf\httpd.conf` make sure `AllowOverride All` applies to
   `htdocs`, so the `.htaccess` files protecting `storage/`, `config/`, `includes/` and
   `database/` are honoured (XAMPP's default allows it).

### Demo accounts (development only; remove before real use)

| Email | Password | State |
|---|---|---|
| `admin` (username, at `/admin/login.php`) | your existing admin password | Administrator |
| `free@studyvault.test` | `Student123!` | Approved, Free Access |
| `paid@studyvault.test` | `Student123!` | Approved, active Research Access |
| `expired@studyvault.test` | `Student123!` | Approved, subscription expired |
| `pending@studyvault.test` | `Student123!` | Pending: cannot log in |
| `rejected@studyvault.test` | `Student123!` | Rejected: cannot log in |

Sample research: records 1–3 are Approved (record 1 has a PDF), record 4 is Pending,
record 5 is Rejected, to demonstrate visibility rules.

## 8. Security summary

PDO prepared statements everywhere · bcrypt hashing · session regeneration on login ·
server-side authorization on every page, API and document request · CSRF tokens on all
POST forms · output escaping via `e()` · PDF extension + MIME + `%PDF-` magic check, size
limit and random filenames · manuscript path-traversal guard · login throttling (5
failures / 15 min per account) · admin session timeout 30 min, student 60 min · API
requires a signed-in approved session, returns approved research only, never returns
`file_path`, passwords or other users' data, and no longer sends `Access-Control-Allow-Origin: *`.

API endpoints (`api/`): `login.php` and `register.php` (POST: `email`, `password`, …),
`research-list.php`, `research-detail.php`, `suggestions.php` (need the session cookie from
`login.php`). Manuscript links in API results appear only for users allowed to read them.

## 9. Before real deployment

- [ ] Integrate a real payment provider (replace `create_demo_subscription()` and `checkout.php`, confirm payment server-side via the provider's webhook) and set `PAYMENT_MODE`.
- [ ] Delete demo students, sample research and `database/make-password-hash.php`.
- [ ] Change the administrator password (Admin → Settings).
- [ ] Set `DEV_MODE` to `false`; serve over HTTPS.
- [ ] Confirm sharing/redistribution policy with your institution; consider hosting pdf.js locally if offline use is required.

## 10. Changes from the previous version

- Student ID login → **email login**; registration now collects full name, email, user type and program.
- Account approval workflow; research approval workflow (uploads were previously auto-approved).
- New: Access Plans, subscriptions, demo checkout, profile, protected viewer + watermark, secure document route, categories page, login throttling, student activity in logs, admin Settings.
- Removed: direct PDF links/"Download PDF"; `admin/about.php` (it was broken and unused); `admin/student-delete.php` (merged into `student-action.php`).
- The old hard-coded 2022–2025 year limit is replaced by real years from the data.
- The legacy `students.access_level`, `first_name`, `last_name` columns remain but are unused.

## 11. Test checklist

Registration: valid student · valid alumni · duplicate email · invalid email · non-IT program · lands as Pending.
Login: correct · wrong password · unknown email · pending · rejected · approved.
Research: approved visible · pending/rejected hidden · search by title, author, abstract, year, keyword, category.
Access: free user locked (no PDF URL in the page source) · paid user can read · expired user locked · admin can open.
Watermark: user email + Student ID appear in the viewer.
Security: open `/study-vault/secure-document.php?id=1` logged out or as `free@…` → refused; open `/study-vault/storage/manuscripts/<file>.pdf` → 403.

## Student ID verification (email + 6-digit code)

Registration is now: **Student ID -> code emailed to the school's email on file -> create password**.

1. In phpMyAdmin (database `study_vault`) import, in this order:
   `database/school-db.sql`, then `database/verification-codes.sql` (safe to run again).
2. Copy `config/mail.local.example.php` to `config/mail.local.php`, then add your Gmail address and 16-character Gmail **App Password** (Google Account > Security > 2-Step Verification > App passwords). `mail.local.php` is ignored by Git. Alternatively, set `STUDY_VAULT_MAIL_USERNAME` and `STUDY_VAULT_MAIL_PASSWORD` in the PHP environment. Never commit real mail credentials.
3. Open `login.php` > **Verify your Student ID** (`verify.php`).

- Eligible: programs listed in `VERIFY_ALLOWED_PROGRAMS` (`includes/verification.php`) with school
  status `enrolled` or `alumni`. `inactive` and non-IT programs are refused.
- Codes: 6 random digits, valid 10 minutes, 5 wrong tries per code, a new code voids older ones,
  60 s between sends, 5 sends per Student ID per hour.
- The email always comes from `school_student_records`, never from user input.
- Accounts are created as `approved`, `access_level = free`, `verification_status = verified`,
  linked through `students.school_record_id`. Returning users just log in with email + password.
- An older admin-created account with the same Student ID and no email is *claimed* by this flow
  (it gets the verified email and the new password) instead of creating a duplicate.
- `register.php` redirects to `verify.php`; `api/register.php` is closed (HTTP 410) because it
  would bypass verification. `register_student()` is kept in `includes/functions.php`, unused.
- Admin > **School Records** shows the simulated school database (read-only).
- Troubleshooting: set `MAIL_SMTP_DEBUG` / `MAIL_DEV_LOG_CODE` in your local `config/mail.local.php` and read the Apache/PHP error log (`xampp/apache/logs/error.log`). PHPMailer 6.9.3 is bundled in `lib/PHPMailer/`.
