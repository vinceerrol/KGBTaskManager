# KCG Task & Workflow Management System
A Vue and Laravel workspace for tasks, team workloads, reusable templates and recurring work. The task record is the source of truth; Messenger sharing is a user-controlled handoff.

## UI/UX update
The September 2026 audit covers every existing screen and shared workflow. Read [the audit](docs/UI_UX_AUDIT_2026.md) and [the design system](design-system/kcg-workspace/MASTER.md).

Features include:
- Overview with actionable metrics and prioritized attention.
- Personal work views and optional focus mode.
- Search/command palette, URL filters, cards/list/board and saved views.
- Multi-owner creation, account-scoped drafts, editing/reassignment and assignment notifications.
- [Write a task](docs/BRIEF_TO_TASK.md): English/Taglish paragraph creation, exact @mentions, editable live preview, protected corrections and optional server-side AI.
- Completion notes, Undo, confirmed transitions, files and activity history.
- Templates, schedule creation, pause/resume and next-run feedback.
- Light/dark appearance, density preferences, reduced motion and keyboard support.
- Team/assignment access enforced by the API. Development demo login is blocked in production.
- Administrator screen for accounts, roles, deactivation and teams; task comments; file removal; editable templates and recurring workflows; password change. See [the October 2026 update](docs/OCTOBER_2026_UPDATE.md).
- Private, signed file downloads, sign-in rate limiting, token expiry and overdue alerts. Deployment steps are in [the deployment guide](docs/DEPLOYMENT.md).

## Stack
Frontend: Vue 3, TypeScript, Vite 8, Tailwind 4, semantic CSS tokens, Pinia, Vue Router, Lucide and Axios.
Backend: Laravel 12, Sanctum and MySQL. New task/schedule dates are stored in UTC; the UI and recurrence rules use Asia/Manila (UTC+8).

## Local development
Requirements: PHP with the selected database PDO driver, Composer, a Node version supported by Vite 8, npm, and the configured database.

Install dependencies and configure backend/.env for your environment. Keep credentials out of committed files.
Run migrations against the intended database; seed only a development database.

```powershell
Set-Location backend
composer install
php artisan migrate
php artisan storage:link
php artisan serve --no-reload --host=127.0.0.1 --port=8000
```

In another terminal:
```powershell
Set-Location frontend
npm install
npm run dev -- --host 127.0.0.1
```

Open http://127.0.0.1:5173. Vite proxies /api and /storage to the local backend. For separate hosting, set VITE_API_URL to the backend API endpoint and configure the backend CORS, APP_URL and storage settings.

Development seed accounts include Sophia (CEO), Anna (team lead) and Carlo (member). The one-click demo choices appear only in the development frontend; the server allows the endpoint only in local/testing environments.

## Isolated UI preview
The final review preview uses backend/storage/app/uiux-preview.sqlite. To reuse that existing fixture on separate ports:
```powershell
Set-Location backend
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE='C:\KCGTaskManager\backend\storage\app\uiux-preview.sqlite'
$env:CACHE_STORE='file'
$env:SESSION_DRIVER='file'
php artisan serve --no-reload --host=127.0.0.1 --port=8001
```
In another terminal:
```powershell
Set-Location frontend
$env:KCG_API_PROXY_TARGET='http://127.0.0.1:8001'
npm run dev -- --host 127.0.0.1 --port 5174
```
Open http://127.0.0.1:5174. These process variables apply to those terminals. Laravel's --no-reload option is necessary to preserve process database overrides. Do not run reset or fresh-migration commands against an existing database. The audit records an initial development-database QA run and the scoped cleanup of its generated records.

## Scheduler
Laravel registers tasks:check-scheduled every minute with overlap prevention. A hosted scheduler process is still required:
```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```
Use php artisan schedule:work for a local scheduler. Recurrences support daily, weekly and monthly schedules. Weekly/monthly rules are anchored to creation day; short months use their final day. A late scheduler creates one current task and advances to a future run, skipping older missed occurrences.

## Validation
```powershell
Set-Location frontend
npm run build
npm test
```
```powershell
Set-Location backend
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
php artisan test
```
The composer update passes 28 frontend tests and the full backend suite of 38 tests with 251 assertions. Browser checks, migration and optional Groq/OpenAI configuration are documented in [Write a task](docs/BRIEF_TO_TASK.md). The composer supports inline blue mention pills, automatic titles after mention headers, and explicit start-date inheritance for time-only deadlines. The audit records the earlier UI validation. Before deploying, configure the scheduler, review legacy timestamp consistency and decide whether attachments need authenticated private downloads.
