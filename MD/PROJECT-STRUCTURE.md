# Project Structure

HKommunity is a PHP application. A browser request enters through `public/`, uses shared configuration and role checks, reads or changes data through models and controllers, and returns a rendered page. Browser JavaScript and API endpoints support interactive features.

## Folder map

```text
HKommunity/
├── api/                 JSON endpoints for admin, coordinator, and scholar features
├── app/
│   ├── bootstrap/       Shared startup and error handling
│   ├── controllers/     Feature workflows and request-level operations
│   ├── models/          Database queries and domain rules
│   └── views/           Shared templates and role-specific page fragments
├── config/              App settings and database connection
├── database/            SQL schema and database backups
├── MD/                  Project and feature documentation
├── middleware/          Login and role access checks
├── public/
│   ├── admin/            Admin page handlers
│   ├── assets/           CSS, JavaScript, images, and local fonts
│   ├── coordinator/      Coordinator page handlers
│   └── scholar/          Scholar page handlers
├── uploads/              Runtime files uploaded or used by the app
│   ├── duty-submissions/ Duty proof files retained for authorized review
│   └── registrations/    Temporary registration-import files
├── vendor/              Composer-installed PHP dependencies
├── .htaccess             Apache rewrite and access rules
├── composer.json         PHP dependency declarations
├── composer.lock         Resolved dependency versions
├── hk.vued.erd           Database relationship diagram
├── index.php             Redirects to the public landing page
└── ScholarsList_2025-2027.xlsx  Workbook used by registration workflows
```

## What each folder does

- **`api/`** — PHP endpoints that return or accept JSON. Endpoints are grouped by role; shared endpoints handle cross-role features such as notifications. Page scripts in `public/assets/js/` call these when an interaction needs data without a full page response.
- **`app/bootstrap/`** — Code shared early in the request lifecycle, such as error handling.
- **`app/controllers/`** — Coordinates multi-step feature operations, such as authentication, imports, renewals, reports, and requirement submissions. Controllers commonly use models to access or change data.
- **`app/models/`** — Encapsulates database queries and business rules for users, scholars, academic periods, registrations, duty records, requirements, reports, and notifications.
- **`app/views/`** — Reusable HTML templates. `components/` holds shared interface pieces such as the header, navigation, and flash messages; other subfolders group fragments by role or purpose.
- **`config/`** — `app.php` defines application settings such as the base URL and timezone. `database.php` creates the shared PDO database connection.
- **`database/`** — Contains SQL schema and backup files. The current folder has SQL dumps; it does not contain a `migrations/` subfolder.
- **`MD/`** — Guides and notes about project structure, portals, testing, exports, and implemented features.
- **`middleware/`** — Checks authentication and role permissions before protected pages run. Role-specific files protect admin, coordinator, and scholar routes.
- **`public/`** — Web-facing PHP pages and static assets. Subfolders separate admin, coordinator, and scholar pages; shared pages such as login and registration are at the `public/` root.
- **`public/assets/css/`** — Shared and feature-specific styling, loaded by page templates.
- **`public/assets/js/`** — Browser-side interactions such as search, dialogs, confirmation flows, and table behavior.
- **`public/assets/images/` and `public/assets/fontawesome/`** — Images and locally hosted icon styles/fonts used by the interface.
- **`uploads/`** — Runtime files, not application source. Duty proof files are retained for authorized review. Registration import files are staged here while they are processed, then deleted; their parsed records and import history remain in the database.
- **`vendor/`** — Third-party libraries installed by Composer. Do not edit package contents directly; manage dependencies using Composer and the project manifests.

## How a request moves through the project

1. Apache routes the URL to a PHP page in `public/`. The root `index.php` redirects to `public/index.php`.
2. The page loads the relevant `config/` files and role middleware. Requests from an unauthorized role are denied or redirected.
3. The page validates the request and coordinates the feature. Complex workflows may be delegated to a controller in `app/controllers/`.
4. Controllers and page handlers use models in `app/models/` to read or update MySQL through the shared PDO connection.
5. The PHP page renders HTML using shared or role-specific templates from `app/views/`.
6. The browser loads CSS, images, and JavaScript from `public/assets/`. JavaScript may call `api/` endpoints for interactive data.
7. Uploaded files are stored under `uploads/` when the workflow requires them; database rows store related metadata and application state.

## Example: importing registrations

1. [`public/admin/registration-import.php`](../public/admin/registration-import.php) checks admin access and accepts a CSV or XLSX upload.
2. It temporarily stores the file in `uploads/registrations/` and passes its path to [`app/controllers/RegistrationImportController.php`](../app/controllers/RegistrationImportController.php) for parsing and validation.
3. The controller and [`app/models/RegistrationImport.php`](../app/models/RegistrationImport.php) save the import and parsed registration rows to the database.
4. The temporary source file is removed after processing. Import history and parsed rows remain available through the database and admin page.

## Where to make changes

| Change needed | Start here |
|---|---|
| Page markup or form handling | Matching page in `public/admin/`, `public/coordinator/`, or `public/scholar/` |
| Shared header, sidebar, or message | `app/views/components/` |
| Database query or domain rule | Matching class in `app/models/` |
| Multi-step feature workflow | Matching class in `app/controllers/` |
| Role access rule | `middleware/` |
| Page styling | Relevant file in `public/assets/css/` |
| Browser interaction | Relevant file in `public/assets/js/` |
| JSON endpoint | Matching endpoint in `api/` |
| Database setup or schema reference | `config/database.php` or SQL files in `database/` |
| Uploaded file handling | Relevant page/controller and folder under `uploads/` |
