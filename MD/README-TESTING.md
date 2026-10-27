# HKommunity Functional Test Guide

## 1. Setup

1. Extract this folder to `C:\xampp\htdocs\HKommunity`.
2. Start Apache and MySQL in XAMPP.
3. Confirm `config/database.php` uses `hkscratch_db`.
4. Make sure the database contains the HKommunity schema. For an existing database, back it up and apply `database/migrations/20261019_duty_submissions.sql` once if it does not already contain the duty submission schema.
5. Apply `database/migrations/20261024_create_user_login_activities.sql` once, then apply `database/migrations/20261024_rename_user_login_activities_to_user_logs.sql` once to enable successful sign-in activity logs in the Admin Dashboard's `user_logs` table.
6. Apply `database/migrations/20261024_standardize_report_statuses.sql` once to consolidate reports into `for_review`, `resolved`, and `rejected` statuses.
7. Open `http://localhost/HKommunity/public/login.php`.

## 2. Admin login

Use the existing Admin account in your database. The original project test credentials were:

- Email: `admin@phinmaed.com`
- Password: `Admin123!`

If the database has a different Admin password, use the current database value.

## 3. Recommended order

1. Login / Logout
2. Users / Create Account
3. Coordinators / Assigned Year Level
4. Registration Import
5. Registration Requests
6. Academic Years
7. Semesters
8. Renewal Periods
9. Requirements
10. Scholar assignment and requirement submission
11. Duty Records
12. Renewals
13. Announcements
14. Reports / escalation
15. Quick Links
16. Notifications
17. Profile

## 4. Registration test

1. Import the official XLSX template from the project root.
2. Keep the import `pending` until reviewed.
3. Approve the import.
4. Register a Scholar using a matching Student ID and matching official details.
5. Open Admin > Registration.
6. Confirm the request is `matched` and `pending`.
7. Approve it.
8. Confirm the new Scholar account appears in Users and Scholars.
9. Log in as the new Scholar.

For a mismatch or unknown Student ID, the request must remain pending and can be rejected by Admin with a reason.

## 5. Coordinator test

Create or use a Coordinator and assign a year level. Confirm that the Coordinator only sees Scholars belonging to that Assigned Year Level.

Test:

- requirement assignment
- requirement review
- review Scholar duty submissions and proof files
- approve a pending duty submission and confirm exactly one linked official duty record is created
- reject a pending duty submission with a reason and confirm it does not add duty hours
- confirm a Coordinator cannot view submissions or proof for another year level
- update legacy, unlinked duty records
- renewal status updates
- Scholar reports
- escalation to Admin

## 6. Scholar test

Test:

- profile viewing/editing of permitted name fields
- requirement submission using a Google Drive URL
- rejected requirement resubmission
- open Duty Hours, use Submit Weekly Duty to reach the submission page, then submit PDF/JPG/PNG proof and confirm its status appears as pending
- confirm pending duty hours are not counted toward the official total
- view the Coordinator's approval or rejection and rejection reason
- view approved duty hours in the official duty records
- renewal start restrictions
- report submission
- announcements
- quick links
- notifications

## 7. Notification test

Notifications are displayed on the Notifications page. The whole notification is clickable and expands in place. Opening an unread notification marks it as read.

Notifications are generated for registration, requirement, duty, renewal, report, and announcement events.

## 8. Code organization

- PHP: `.php`
- JavaScript: `public/assets/js/*.js`
- CSS: `public/assets/css/style.css`
- No JavaScript logic is embedded inside PHP files.

## 9. If a page fails

Enable PHP error display temporarily in XAMPP/PHP only while debugging, then report the exact error and page URL. Do not change database tables unless the error identifies a schema mismatch.
