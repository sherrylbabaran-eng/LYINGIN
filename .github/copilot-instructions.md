# Copilot / Agent Instructions for LYINGIN (concise)

## Quick summary ✅
- PHP + static front-end project served via Apache (XAMPP common on Windows).
- APIs live under `auth/api/*.php` and most endpoints return JSON `{status, message, ...}`.
- Security helpers centralised in `auth/api/security.php` (CSRF, sanitization, rate-limiter, file upload helpers).
- Front-end uses Bootstrap 5, Leaflet (OpenStreetMap), and face-api (client-side face descriptors).

## Local dev & setup (must-do) 🔧
1. Run LAMP/XAMPP (Apache + MySQL). Import `lyingin_db.sql` into your local MySQL DB.
2. Copy `.env.example` → `.env` and set DB creds (or adjust `auth/setup-security.php` defaults).
3. Run the security setup checks: `php auth/setup-security.php` (ensures `uploads/`, `logs/`, PHP extensions, and CSRF work).
4. Start Apache and browse to project root (e.g., `http://localhost/LYINGIN/`).

## Build & asset workflows ⚙️
- Some subfolders use tooling:
  - `clinic/staff/` uses Laravel Mix (npm scripts in `package.json`). Use `npm install` then `npm run dev` or `npm run prod`.
  - Several areas include `gulpfile.js` (e.g., root-ish `admin/`, `patient/`, `superadmin/`) — run `gulp` inside those folders if you change their SCSS/JS.
- CSS/JS vendor usage: many pages load CDNs (Bootstrap, Leaflet, face-api). For local offline dev, either vendor files are in `assets/vendor` or update references.

## Important APIs & patterns 📡
- CSRF: `auth/api/get-csrf-token.php` + `auth/api/security.php` (use `generateCSRFToken()` & `validateCSRF()`). Some client code fetches token on load (e.g., `index.html`).
- Auth & session checks: include `require_once __DIR__ . '/security.php'` at top of API scripts.
- OTP flow: `auth/api/send-otp.php` and `auth/api/verify-otp.php` (client-side flows in `register-patient.html` enable Next button after verification).
- File uploads: stored under `uploads/patients/` and `uploads/clinics/`. Server code validates MIME using `finfo` (`validateFileUpload()` in `security.php`). Make sure upload dirs are writable.

## Project-specific conventions & gotchas ⚠️
- JSON response convention: always check `status` and `message` keys (most endpoints follow this). Example: `register-patient.php` returns `{status: 'success'|'error', message: '...'}`.
- Client performs face verification (face-api): `register-patient.html` computes descriptors and compares client-side (threshold 0.5). Server reads a `face_verified` flag in `auth/api/register-patient.php` (client must set this after explicit confirm). If you need server-side verification, add descriptor uploads and a server-side matching step. Note: The registration UI now uses camera-first face capture (no face-upload). The server will auto-generate a `username` from the email local-part if a username is not supplied.
- Database migration: the `regpatient` table now includes a `face_verified` TINYINT column. If you're running an existing database, run the migration at `database/migrations/20260203_add_face_verified_to_regpatient.sql` to add the column and set defaults.
- Reverse geocoding: Client pages should use the server-side proxy at `auth/api/reverse-geocode.php` (avoids CORS and centralizes User-Agent/email per Nominatim policy). If adding new client code, call the proxy (`?lat=...&lon=...`) and respect Nominatim rate limits — cache or mock responses during tests to avoid being blocked.
- Email sending: `auth/api/register-patient.php` prefers an API (SendGrid) if `SENDGRID_API_KEY` is configured, else falls back to SMTP configuration from `.env` (see `SENDGRID_API_KEY`, `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SMTP_SECURE`, `MAIL_FROM`). On any failure a copy of the verification email is saved in `auth/logs/emails/` and SMTP/API errors are written to server logs. To enable in development, either set environment variables in Apache/PHP or copy `.env.example` → `.env` and restart Apache so the `.env` loader picks values.
- Security is centralized: prefer using functions in `auth/api/security.php` (sanitization, CSRF, rate-limiter) instead of rolling your own.

## Where to look (key files & examples) 🔎
- Security & CSRF: `auth/api/security.php`, `auth/api/get-csrf-token.php`, `auth/setup-security.php`, `SECURITY_IMPLEMENTATION.md`
- Patient registration flow: `register-patient.html` (client) and `auth/api/register-patient.php` (server)
- OTP/email flows: `auth/api/send-otp.php`, `auth/api/verify-otp.php`, `auth/api/send-verification-email.php`
- Uploads: `uploads/patients/` and `uploads/clinics/`
- Front-end build: `clinic/staff/package.json`, `gulpfile.js` at `admin/`, `patient/`, `superadmin/` folders

## Tests / Debugging tips 🐞
- Use browser devtools to trace fetch requests; backend returns JSON and logs to `auth/logs/` when errors occur.
- Run `php auth/setup-security.php` to quickly validate environment issues (missing extensions, permissions, or DB tables).
- For heavy external services (Nominatim / SMTP), stub or mock responses when writing automated tests.

## Quick PR guidelines for contributors ✍️
- Keep APIs JSON-first and include proper HTTP status codes (current pattern: 200 + JSON `{status, message}` or 403 on CSRF).
- Reuse `security.php` helpers for sanitization and file validation. Add unit-like checks to `auth/setup-security.php` when adding new infra requirements.
- When changing face verification behavior, annotate whether verification occurs client-side or server-side and update `register-patient.php` logic accordingly.

---
If you'd like, I can merge this into an existing instructions file or expand specific sections (e.g., how to add server-side face verification or mock Nominatim). Any part you want clarified or expanded? 🔧