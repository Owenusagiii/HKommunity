# Portals and Pages

HKommunity has three role-based portals. Admins configure the system, Coordinators manage a year-level cohort, and Scholars submit work and track progress.

## How the portals work together

```text
Admin sets up periods, accounts, and requirements
        ↓
Coordinator assigns requirements and reviews submissions
        ↓
Scholar submits requirement links and weekly duty proof
        ↓
Coordinator approves or returns submissions
        ↓
Scholar tracks progress, renewal status, and notifications
```

Scholar transactions are available only for the applicable open work period. Earlier periods can be viewed as read-only history.

## Admin

Pages are in [`public/admin/`](../public/admin/).

| Page | Purpose |
|---|---|
| [`dashboard.php`](../public/admin/dashboard.php) | System overview and activity. |
| [`users.php`](../public/admin/users.php), [`coordinators.php`](../public/admin/coordinators.php), [`create-account.php`](../public/admin/create-account.php) | Manage users, create accounts, and assign coordinator year levels. |
| [`registrations.php`](../public/admin/registrations.php), [`registration-import.php`](../public/admin/registration-import.php) | Review scholar registrations and import CSV/XLSX data. |
| [`academic-years.php`](../public/admin/academic-years.php), [`semesters.php`](../public/admin/semesters.php), [`renewal-periods.php`](../public/admin/renewal-periods.php) | Configure academic periods and required duty hours. |
| [`requirements.php`](../public/admin/requirements.php) | Manage assignable requirements. |
| [`scholar-progress.php`](../public/admin/scholar-progress.php), [`scholar-export.php`](../public/admin/scholar-export.php) | Review scholar progress and export records. |
| [`announcements.php`](../public/admin/announcements.php), [`quick-links.php`](../public/admin/quick-links.php) | Publish announcements and manage scholar resources. |
| [`reports.php`](../public/admin/reports.php), [`notifications.php`](../public/admin/notifications.php) | Review reports and view notifications. |

## Coordinator

Pages are in [`public/coordinator/`](../public/coordinator/). Coordinators work with scholars in their assigned year level.

| Page | Purpose |
|---|---|
| [`dashboard.php`](../public/coordinator/dashboard.php) | Cohort progress and work needing attention. |
| [`scholars.php`](../public/coordinator/scholars.php) | Find scholars and view their records. |
| [`requirements.php`](../public/coordinator/requirements.php) | Assign requirements and review submitted links. |
| [`duty-records.php`](../public/coordinator/duty-records.php) | Review duty proof and manage rendered duty records. |
| [`renewals.php`](../public/coordinator/renewals.php) | Review eligibility, promote scholars, and manage renewal status. |
| [`reports.php`](../public/coordinator/reports.php) | Review and respond to scholar reports. |
| [`announcements.php`](../public/coordinator/announcements.php), [`notifications.php`](../public/coordinator/notifications.php) | Read announcements and notifications. |

## Scholar

Pages are in [`public/scholar/`](../public/scholar/). Scholars can access their own information and submit items for review.

| Page | Purpose |
|---|---|
| [`dashboard.php`](../public/scholar/dashboard.php) | Overview of progress, renewal, updates, and links. |
| [`requirements.php`](../public/scholar/requirements.php) | View assignments and submit or resubmit requirement links. |
| [`duty-submission.php`](../public/scholar/duty-submission.php), [`duty-records.php`](../public/scholar/duty-records.php) | Submit weekly duty proof and view approved hours. |
| [`renewal.php`](../public/scholar/renewal.php) | Track renewal status and history. |
| [`reports.php`](../public/scholar/reports.php) | Submit reports and view responses. |
| [`announcements.php`](../public/scholar/announcements.php), [`quick-links.php`](../public/scholar/quick-links.php), [`notifications.php`](../public/scholar/notifications.php) | Read updates, open resources, and view notifications. |

## Shared pages and supporting code

- [`public/login.php`](../public/login.php) signs in users and routes them to their role's dashboard. [`public/register.php`](../public/register.php) starts scholar registration.
- [`public/profile.php`](../public/profile.php) and [`public/logout.php`](../public/logout.php) handle shared account actions.
- [`public/notifications.php`](../public/notifications.php) and endpoints in [`api/`](../api/) support shared and browser-driven requests.
- [`middleware/`](../middleware/) protects pages by role. Page handlers in `public/` use models and controllers in [`app/`](../app/) to process data; shared templates are in [`app/views/`](../app/views/).
- Styles and browser interactions are in [`public/assets/css/`](../public/assets/css/) and [`public/assets/js/`](../public/assets/js/).

For folder responsibilities and request flow, see [PROJECT-STRUCTURE.md](./PROJECT-STRUCTURE.md).
