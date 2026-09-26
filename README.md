<<<<<<< HEAD
# home_erp
=======
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

## Demo accounts

All demo accounts use password `password`.

| Role | Email |
|---|---|
| Admin | `admin@homeerp.test` |
| Family member | `family@homeerp.test` |
| Staff / service user | `staff@homeerp.test` |

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
>>>>>>> master
