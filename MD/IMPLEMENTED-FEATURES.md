# Implemented Features

HKommunity is a role-based scholarship portal for administrators, coordinators, and scholars. This document summarizes the major workflows available in the application.

## Authentication and access

- Login and account registration with role-aware routing.
- Access checks for admin, coordinator, and scholar pages.
- Admin-managed account activation and deactivation.
- Coordinator accounts are assigned to a year level. Multiple coordinators may be assigned to the same level and work with the same cohort.
- Shared profile and notification interfaces.

Related entry points include [`public/login.php`](../public/login.php), [`public/register.php`](../public/register.php), and the role middleware in [`middleware/`](../middleware/).

## Administrator portal

### Dashboards and scholar records

- Admin dashboard with organization-wide statistics and year-level progress.
- Scholar and coordinator account search, filtering, creation, and status management.
- Coordinator year-level assignment.
- Scholar record and progress views.
- Scholar data export to supported spreadsheet and CSV formats.

### Academic periods

- Create and manage academic years and semesters.
- Activate or deactivate academic periods.
- Configure renewal periods by semester and year level.
- Set required duty hours for each renewal period. A zero-hour requirement locks duty transactions for that period.
- Prevent deletion of academic periods or renewal periods that have linked records.

### Registration management

- Import registration records from CSV and XLSX workbooks.
- Validate imported rows and identify invalid or mismatched records.
- Review import history and approve, reject, or delete eligible imports.
- Review individual scholar registrations, reverify records, and approve or reject requests.

### Requirements, announcements, and quick links

- Create, edit, activate, deactivate, and manage requirements.
- Publish announcements to all users, coordinators, or scholars.
- Manage quick links displayed in the portals.

### Reports

- Review scholar reports, send responses, and manage report status.
- Export report and scholar data where available.

## Coordinator portal

### Scholar and cohort management

- View scholars assigned to the coordinator's year level.
- Search and filter scholar records and view cohort progress.
- Review renewal history and scholar requirement details.

### Requirement assignments and reviews

- Assign active requirements to eligible scholars for the applicable work period.
- Select scholars and requirements individually or with select-all controls.
- Prevent reassignment when a scholar already has every active requirement for the applicable period.
- Review scholar-submitted Google Drive links and approve or reject requirements.
- Record review history and notify scholars about assignment and review updates.

### Duty submissions and records

- Review pending scholar duty submissions and attached proof.
- Update pending submission details, approve submissions into duty records, or reject them with a reason.
- View duty records and rendered-hour totals for the assigned year level.
- Confirm review actions before they are applied.

### Renewals

- Review eligible scholars and promote selected scholars into the active renewal cycle.
- Filter promotion candidates by student ID and eligibility.
- Confirm bulk promotion before submission; notify scholars when promoted.
- Review renewal status changes and scholar reports.

## Scholar portal

- View a dashboard with renewal-cycle and progress information.
- View requirements for the selected period, submit Google Drive links, and review requirement status.
- Submit weekly duty hours with proof files, then view, edit, or delete eligible pending submissions.
- View approved duty records and rendered-hour progress.
- View renewal status and renewal history.
- Submit and track reports.
- Read announcements and notifications.
- Access coordinator-provided quick links.

## Shared interaction and presentation

- Designed confirmation dialogs for important actions such as approvals, rejections, deletions, submissions, and status changes.
- Search and status filters on record-heavy pages.
- Responsive layouts and feature-specific CSS modules.
- Locally hosted Font Awesome assets to avoid a third-party icon stylesheet dependency.
- Accessible form labels, status indicators, dialog titles, and action feedback.

## Synthetic test data

The current local database includes synthetic records used to exercise registration, duty, requirement, notification, and announcement workflows. Synthetic duty and requirement entries are marked as test data and use dummy proof or placeholder links. These records are for local testing only and must not be interpreted as verified scholar evidence or production data.
