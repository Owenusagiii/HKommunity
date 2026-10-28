# HKommunity Project Structure

HKommunity is a PHP web application. Browser requests are handled by pages in `public/`; shared configuration, middleware, controllers, and models support those pages. Views render reusable interface templates, while the browser loads static assets from `public/assets/`. API endpoints provide JSON interactions.

The folder map below covers the application folders currently in the repository. `.git/` is Git's internal metadata, and the subfolders inside `vendor/` are installed and managed by Composer rather than maintained as application code.

## Folder map

```text
HKommunity/
├── api/                              JSON endpoints
│   ├── admin/                        Admin-specific endpoints
│   ├── coordinator/                  Coordinator-specific endpoints
│   └── scholar/                      Scholar-specific endpoints
├── app/                              Shared application code
│   ├── bootstrap/                    Request startup and error handling
│   ├── controllers/                  Feature workflows
│   ├── models/                       Data access and business rules
│   └── views/                        Reusable page templates
│       ├── admin/                    Admin page templates
│       ├── auth/                     Landing and login templates
│       ├── components/               Shared interface components
│       ├── coordinator/              Coordinator page templates
│       ├── errors/                   Error page templates
│       └── scholar/                  Scholar page templates
├── config/                           Application and database configuration
├── database/                         SQL schema and database backups
├── MD/                               Project documentation and reference files
├── middleware/                       Authentication and role-access checks
├── public/                           Web-facing pages and public files
│   ├── admin/                        Admin page handlers
│   ├── assets/                       Browser assets
│   │   ├── css/                      Stylesheets
│   │   ├── fontawesome/              Locally hosted Font Awesome files
│   │   │   ├── css/                  Font Awesome stylesheets
│   │   │   └── webfonts/             Font Awesome font files
│   │   ├── images/                   Interface images and logos
│   │   └── js/                       Browser-side scripts
│   ├── coordinator/                  Coordinator page handlers
│   └── scholar/                      Scholar page handlers
├── uploads/                          Runtime-uploaded or staged files
│   ├── duty-submissions/             Duty proof files
│   └── registrations/                Temporary registration-import files
└── vendor/                           Composer-installed PHP dependencies
```

## What the folders do

### Application and server-side code

| Folder | Purpose |
|---|---|
| `api/` | Shared and role-specific PHP endpoints that accept or return JSON for interactive features. |
| `api/admin/` | Admin endpoints for features such as users, scholars, registrations, academic periods, requirements, reports, and announcements. |
| `api/coordinator/` | Coordinator endpoints for scholar records, duty records, renewals, requirements, reports, and announcements. |
| `api/scholar/` | Scholar endpoints for duty records, renewals, requirements, reports, quick links, and announcements. |
| `app/bootstrap/` | Code needed early in a request, including application-wide error handling. |
| `app/controllers/` | Coordinates multi-step feature workflows and connects request handling to models. |
| `app/models/` | Encapsulates database operations and domain rules for users, scholars, academic periods, duty records, requirements, reports, notifications, and related features. |
| `app/views/` | PHP/HTML templates used to render pages and shared interface content. |
| `app/views/admin/` | Templates for admin dashboards and management pages. |
| `app/views/auth/` | Landing-page and login templates. |
| `app/views/components/` | Shared pieces such as the header, sidebar, footer, helper functions, and flash messages. |
| `app/views/coordinator/` | Templates for coordinator dashboards and workflows. |
| `app/views/errors/` | Templates for styled error pages. |
| `app/views/scholar/` | Templates for scholar dashboards and workflows. |
| `config/` | Application settings and the shared database connection. |
| `middleware/` | Authentication and authorization checks used to protect pages by role. |

### Public pages and browser assets

| Folder | Purpose |
|---|---|
| `public/` | Web-accessible PHP pages shared across roles, including login, registration, profile, notifications, and error handling. |
| `public/admin/` | Admin-facing page handlers, including management, imports, exports, and reports. |
| `public/coordinator/` | Coordinator-facing page handlers for scholar management and coordinator workflows. |
| `public/scholar/` | Scholar-facing page handlers for records, submissions, requirements, renewals, and reports. |
| `public/assets/` | Static files loaded by the browser. |
| `public/assets/css/` | Shared and feature-specific stylesheets. |
| `public/assets/fontawesome/` | Locally hosted Font Awesome resources used for interface icons. |
| `public/assets/fontawesome/css/` | Font Awesome CSS files. |
| `public/assets/fontawesome/webfonts/` | Font Awesome font files used by its stylesheets. |
| `public/assets/images/` | Logos and other interface images. |
| `public/assets/js/` | JavaScript for menus, search, filters, confirmations, dialogs, tables, and other browser interactions. |

### Data, documentation, and runtime files

| Folder | Purpose |
|---|---|
| `database/` | SQL schema and backup files. |
| `MD/` | Project documentation, portal and feature references, testing notes, and related project materials. |
| `uploads/` | Runtime file storage; not application source code. |
| `uploads/duty-submissions/` | Duty proof files associated with duty submissions and available to authorized workflows. |
| `uploads/registrations/` | Temporary staging area for registration-import files; import records are stored in the database, and source files are removed after processing. |
| `vendor/` | Third-party PHP packages installed from `composer.json` and `composer.lock`. Manage these with Composer; do not edit their files directly. |

## Important files at the project root

| File | Purpose |
|---|---|
| `.htaccess` | Apache rewrite and access rules. |
| `composer.json` | Declares PHP dependencies and Composer configuration. |
| `composer.lock` | Records the resolved dependency versions. |
| `hk.vued.erd` | Database entity-relationship diagram. |
| `hkommunity.sql` | SQL database file. |
| `hkommunity_backup.sql` | Database backup SQL file. |
| `index.php` | Root entry point that forwards visitors into the public application. |

## How a request moves through the project

1. Apache receives a request and serves or routes it to a PHP entry point in `public/`.
2. The page loads configuration and the appropriate middleware to establish the database connection and check access.
3. The page validates the request and handles the page-level work. Multi-step workflows may be delegated to a controller in `app/controllers/`.
4. Controllers and page handlers use models in `app/models/` to read or update application data.
5. PHP renders the response using templates in `app/views/`.
6. The browser loads styles, images, fonts, and scripts from `public/assets/`. Scripts may call endpoints in `api/` for interactive data.
7. Workflows that accept files may use `uploads/` for runtime storage or temporary staging.

## Example: importing registrations

1. [`public/admin/registration-import.php`](../public/admin/registration-import.php) checks admin access and accepts a CSV or XLSX upload.
2. The source file is temporarily staged in `uploads/registrations/` and passed to [`app/controllers/RegistrationImportController.php`](../app/controllers/RegistrationImportController.php) for parsing and validation.
3. The controller and [`app/models/RegistrationImport.php`](../app/models/RegistrationImport.php) save the import and parsed registration rows to the database.
4. The temporary source file is removed after processing; import history and parsed rows remain available through the database and admin page.

## Where to make changes

| Change needed | Start here |
|---|---|
| Page markup or form handling | Matching page in `public/admin/`, `public/coordinator/`, `public/scholar/`, or `public/` |
| Shared header, sidebar, or message | `app/views/components/` |
| Database query or domain rule | Matching class in `app/models/` |
| Multi-step feature workflow | Matching class in `app/controllers/` |
| Role access rule | `middleware/` |
| Page styling | Relevant file in `public/assets/css/` |
| Browser interaction | Relevant file in `public/assets/js/` |
| JSON endpoint | Matching endpoint in `api/` |
| Database setup or schema reference | `config/database.php` or SQL files in `database/` |
| Uploaded file handling | Relevant page/controller and folder under `uploads/` |
