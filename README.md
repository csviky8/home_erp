# NOVA Home ERP 3D

A full-stack Home Management ERP with a Vue 3 + Tailwind responsive control plane and a real Three.js spatial home view.

## Location

```text
C:\xampp\htdocs\home-erp
```

## Stack

- Laravel 12 / PHP 8.2 / REST API / Sanctum
- MariaDB 10.4 (`home_erp`)
- Vue 3 / Vue Router / Pinia / Tailwind CSS 4
- Three.js interactive home and animated AI mascot
- Chart.js / Vue Chart.js
- Laravel queues, notifications, policies, jobs, Excel and PDF exports

## Run locally

The project is configured for the local XAMPP installation. From PowerShell:

```powershell
Set-Location 'C:\xampp\htdocs\home-erp'
composer install
npm install
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8002
```

For frontend hot reload, use a second terminal:

```powershell
Set-Location 'C:\xampp\htdocs\home-erp'
npm run dev -- --host 127.0.0.1
```

Open **http://127.0.0.1:8002** for the built SPA. Vite runs on `http://127.0.0.1:5173` and proxies API requests to port `8002`.

Port `8000` is already occupied by another local application in this environment, so this project uses `8002`.

## Roles and permissions

Access is **permission-driven, not role-name driven**. `App\Support\AccessMap` is the single
source of truth that maps each area of the app to the permission that unlocks it. Both the
API guards and the Vue interface read it, so a panel can never be hidden in the UI while the
API allows it (or the reverse).

| Area | Ability | Permission |
|---|---|---|
| Dashboard | view | `dashboard.view` |
| Settings | view | `settings.view` |
| Settings | manage | `settings.manage` |
| People & roles | view | `users.view` |
| People & roles | create | `users.create` |
| People & roles | edit | `users.edit` |
| People & roles | delete | `users.delete` |
| Role manager | view | `roles.view` |
| Role manager | create | `roles.create` |
| Role manager | edit | `roles.edit` |
| Role manager | delete | `roles.delete` |
| Families | view / manage | `settings.manage` |
| Reports & exports | view / export | `reports.view` / `reports.export` |
| AI assistant | use | `ai.use` |
| Every module | view/create/edit/delete/export/approve | `<module>.<ability>` |

Rules:

- `super-admin` is a full bypass everywhere, matching the backend guards.
- Any other role sees exactly what its permissions allow. Grant a custom role `settings.manage`
  and it gains the same Settings, People & roles, and Families panels an Admin sees.
- **Only a super admin** may grant the `super-admin` role, or change the permissions of a system
  role (`admin`, `family-member`, `staff`). This prevents an admin from escalating itself or
  stripping its own management permissions and locking everyone out.
- Household isolation still applies to data: `settings.manage` lets an Admin work across
  families, but every other user is scoped to their own `household_id`.

The signed-in user payload carries a resolved `abilities` map plus the raw `areas` definition,
which is what `auth.canArea('users', 'manage')` in the frontend reads.

### Data isolation (whose data you can touch)

Permissions decide **what** you may do; the household rule decides **whose data** you may act on:

| Role | Family workspaces | Data scope |
|---|---|---|
| `super-admin` | sees and creates **all** families | every household |
| `admin` | sees only its **own** family card | its own household only |
| any other role | no family workspaces | its own household only |

An admin therefore has full management rights *inside* its own family, but every route is
locked to `household_id = user.household_id`. Reading another family's members, editing another
family's settings, or creating users in another family all return `403`, and `GET /api/households`
returns a single card. This is enforced in one place, `AccessMap::reachesHousehold()` /
`AccessMap::managesAllHouseholds()`, and combined with `HouseholdModel::scopeForUser()` for module data.

Note: the test suite's auth guard memoizes the resolved user between requests within a single
test method, so tests that switch identity use separate test methods or separate accounts.

## Demo accounts

All demo accounts use password `password`. These are **development/demo credentials only** —
change or remove them before exposing the app to a real network.

| Role | Email |
|---|---|
| Super admin | `root@homeerp.test` |
| Admin | `admin@homeerp.test` |
| Family member | `family@homeerp.test` |
| Staff / service user | `staff@homeerp.test` |

### Family workspaces

The seeder builds **three isolated families**, each with its own users, property, categories,
providers and records. A family only ever sees its own data.

| Family | Admin | Family member | Staff |
|---|---|---|---|
| Aurora Family Home (Bengaluru) | `admin@homeerp.test` | `family@homeerp.test` | `staff@homeerp.test` |
| Sharma Villa (Bengaluru) | `villa.admin@homeerp.test` | `villa.family@homeerp.test` | `villa.staff@homeerp.test` |
| Nair Cottage (Kochi) | `cottage.admin@homeerp.test` | `cottage.family@homeerp.test` | — |

`root@homeerp.test` is the only super admin and can switch between all three from
**Settings → Working family** (or the family cards). Everything they see and create is scoped to
the family they are working in.

## Implemented modules

The API uses a shared, validated module contract so every module has consistent search, filters, pagination, create/edit/view/delete, audit logging, and household scoping without duplicating CRUD code.

- Dashboard with live metrics, alerts, recent activity, and charts
- Expenses and category breakdowns
- Bills, due dates, payment history, status, and reminders
- Maintenance requests and service history
- Assets, warranty dates, documents, photos, and condition
- Tasks, priorities, recurrence, assignees, and due dates
- Inventory, low-stock and expiry data
- Family members
- Monthly/yearly and category budgets
- Subscriptions and renewals
- Insurance policies and renewals
- Vehicles, service dates, PUC/RC reminders
- Service providers
- Secure document upload, preview, and download
- Home security devices and visitor-ready schema
- Garden plants and watering reminders
- Pets and health records
- Multiple properties and rental fields
- Calendar events and recurrence schema
- Universal search
- Reports with Excel/PDF export
- In-app notifications and queued reminder job
- Activity audit log
- Profile, password, photo, and token-session management
- Role and permission administration
- Permission-scoped AI assistant with local data intents and optional OpenAI-compatible enhancement

## Architecture notes

- `config/home_modules_*.php` is the module registry. Add a module by adding its model, migration, permission prefix, fields, searchable columns, and relationships.
- `ModuleRequest`, `ModuleController`, `ModuleResource`, and `ModulePolicy` provide the shared validated API layer.
- `App\Support\ModuleRegistry` applies `forUser()` household scoping and eager-loads configured relationships.
- `App\Jobs\SendReminderNotifications` is scheduled daily at 08:00 and uses the database queue.
- `App\Services\HomeAiService` checks permissions before querying data. Optional external AI is disabled unless `OPENAI_API_KEY` is configured; the local intent engine works without an API key.
- Uploaded files use Laravel local storage and authorization checks. Documents are never served by an unscoped public path.
- The Three.js home is procedural, so it has no external model download requirement and remains usable offline.

## Verification

```powershell
npm run build
php artisan test --no-coverage
```

The feature suite covers authentication, dashboard data, module access, AI responses, role denial, and expense create/update/delete. The MySQL smoke test also verified every configured module schema/list endpoint, reports, Excel export, and the Vite API proxy.
