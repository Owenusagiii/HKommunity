# Transactions and Workflows

This guide explains how HKommunity's main records move from one role to another, what changes are saved, and which actions are restricted. Pages and roles are summarized in [PORTALS-AND-PAGES.md](./PORTALS-AND-PAGES.md); folder responsibilities are in [PROJECT-STRUCTURE.md](./PROJECT-STRUCTURE.md).

## Transaction flow at a glance

```text
Admin configures periods and requirements
  ├─ Imports registration records and reviews applications
  └─ Publishes announcements
        ↓
Coordinator assigns requirements and reviews scholar submissions
        ↓
Scholar submits requirement links, duty proof, and reports
        ↓
Coordinator updates renewal progress and eligibility
        ↓
Scholar sees statuses and notifications
```

Coordinators act only on scholars in their assigned year level. Scholar work is tied to a renewal period. A scholar may view earlier-period records, but may submit or change transactions only in the current active work period. Duty submissions are locked when that period requires zero duty hours.

## 1. Registration

### Spreadsheet import

1. An Admin uploads a CSV or XLSX file from [`public/admin/registration-import.php`](../public/admin/registration-import.php).
2. The importer validates the required columns and each row, then saves an import record and parsed rows. Invalid rows are retained with their validation errors.
3. The uploaded file is temporary: after processing, it is deleted from `uploads/registrations/`. The import history and parsed records remain in the database.
4. The Admin approves or rejects the import. Approval refreshes the matching of pending applications against registration records.

### Scholar application review

1. A Scholar submits a registration application from [`public/register.php`](../public/register.php).
2. An Admin can refresh verification, approve, or reject a pending application in [`public/admin/registrations.php`](../public/admin/registrations.php).
3. Approval is allowed only for a pending application with a matched registration record and an email address not already used by an account. Approval creates the user and scholar records together, marks the application approved, and notifies the new Scholar.
4. Rejection requires a reason. Only pending applications can be approved or rejected.

## 2. Renewal-period setup

Admins configure academic years and semesters, then create year-level renewal periods with a required duty-hours target. A period is available for transactions only when the renewal period, semester, and academic year are active. Setup pages are in [`public/admin/`](../public/admin/).

## 3. Requirement assignment and review

1. A Coordinator assigns active requirements to one or more scholars in their year level for the applicable work period using [`public/coordinator/requirements.php`](../public/coordinator/requirements.php).
2. Each scholar-requirement assignment begins as `assigned`; duplicate assignment of the same requirement to the same scholar and period is prevented.
3. The Scholar submits a Google Drive link through [`public/scholar/requirements.php`](../public/scholar/requirements.php). The assignment becomes `submitted`.
4. The Coordinator approves or rejects the submitted item. Rejection requires a reason; the Scholar can correct and resubmit a rejected item.
5. Status changes are recorded in requirement history, and notifications are sent for assignments, submissions, and reviews.

```text
assigned → submitted → approved
                     ↘ rejected → submitted
```

## 4. Duty submission and approval

1. The Scholar submits a duty date, type, claimed hours, and proof files from [`public/scholar/duty-submission.php`](../public/scholar/duty-submission.php).
2. Proof files are stored under `uploads/duty-submissions/`; file metadata and the pending submission are stored in the database.
3. While a submission is pending or rejected, the Scholar may edit or delete it. Editing returns it to `pending`. Approved submissions cannot be edited or deleted by the Scholar.
4. A Coordinator reviews pending submissions for their assigned year level in [`public/coordinator/duty-records.php`](../public/coordinator/duty-records.php). Approval creates the official duty record and changes the submission to `approved`; rejection requires a reason.
5. Only approved duty records count toward the required-hours total. The Scholar is notified of the review result.

```text
pending → approved (official duty record created)
        ↘ rejected → edit/resubmit → pending
```

The approval and official-record creation are saved together so an approved submission cannot be counted without its corresponding duty record.

## 5. Renewal review and promotion

1. The Coordinator reviews renewal status in [`public/coordinator/renewals.php`](../public/coordinator/renewals.php).
2. Renewal statuses are `in_progress`, `for_review`, `approved`, `rejected`, and `completed`. Changes are recorded in renewal history.
3. A renewal can be marked `completed` only after all assigned requirements are approved and the Scholar has met the period's required duty hours.
4. An `approved` renewal acts as an exemption for the next renewal cycle. Otherwise, promotion requires the previous cycle to be completed with its requirements and duty-hours target satisfied.
5. Promotion creates an `in_progress` renewal for the active period and notifies the Scholar. Scholars can view their status and history in [`public/scholar/renewal.php`](../public/scholar/renewal.php).

## 6. Reports

1. A Scholar submits a report from [`public/scholar/reports.php`](../public/scholar/reports.php). It is routed to an active Coordinator for the Scholar's year level and starts as `for_review`.
2. The Coordinator can respond and set the report to `for_review`, `resolved`, or `rejected`, or escalate it to an Admin.
3. The Scholar can delete their own report only while it is still `for_review`. Updates and escalation generate notifications.

## 7. Announcements and notifications

Admins create announcements for all users, Coordinators, or Scholars from [`public/admin/announcements.php`](../public/admin/announcements.php). Active announcements appear to their selected audience; creating an announcement also sends notifications to that audience. Notifications are also generated for important events such as registration decisions, requirement activity, duty reviews, and renewal changes.

## Data and history

These workflows primarily read and update database records through models in [`app/models/`](../app/models/) and controllers in [`app/controllers/`](../app/controllers/). Status/history tables preserve changes for requirements and renewals. Uploaded duty proof remains in file storage while its metadata and submission status are tracked in the database. Registration-import source files are the exception: they are removed after processing, while the import record and parsed rows are retained.
