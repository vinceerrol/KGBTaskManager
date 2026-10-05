# October 2026 update

Written 5 October 2026 after a security, frontend and spec audit.

## Verification status (5 October 2026)

- Backend: `php artisan test` passes, 56 tests and 367 assertions, including the new `ManagementTest` and extra `TaskInteractionTest` cases.
- Frontend: `npm test` passes 28 of 28, and `npm run build` (type-check plus production build) succeeds.
- The two pending migrations (`add_brief_creation_to_tasks` and `add_management_features`) were applied to the local MySQL development database, and `php artisan attachments:make-private` found no old public files to move.
- **Not yet verified:** nothing has been exercised in a browser against the running app, on a real host, or with real mail settings. Treat the first deployment as a rehearsal.

To repeat the checks:

```powershell
Set-Location backend
php artisan migrate          # adds users.is_active and task_comments
php artisan test
Set-Location ..\frontend
npm test
npm run build
```

## Security

- **Uploads** accept only PDF, images, video, audio, Office documents, text, CSV, Markdown and ZIP. HTML, SVG, PHP and executables are rejected.
- **Attachments are private.** New files are stored on the private disk and opened through signed links that expire after 30 minutes (`GET /api/attachments/{id}/download`). Downloads are forced as attachments with `X-Content-Type-Options: nosniff`.
- Files uploaded before this change remain in `storage/app/public` and stay publicly reachable until you run `php artisan attachments:make-private`.
- **Sign-in is rate limited** to five attempts a minute per email and address (`throttle:login`), including password change.
- **CORS** now reads `CORS_ALLOWED_ORIGINS` and no longer allows every origin. Credentialed cross-origin requests are off by default.
- **Tokens expire** after `SANCTUM_TOKEN_EXPIRATION` minutes (default one week) and expired ones are pruned daily. Signing in on one device no longer signs out the others.
- The **users list** hides team memberships outside the viewer's own teams.
- The scheduler lock expires after 10 minutes, so a crashed run cannot block it for a day.
- Clearing a scheduled task's start time now starts the task instead of leaving it stuck.

## New features

- **People & teams** (administrator only, `/people`): create accounts, change roles, reset passwords, deactivate leavers, create, rename and delete teams, and change membership. The last active administrator cannot be removed.
- **Change password** from the profile menu. Other devices are signed out.
- **Comments** on tasks for everyone who can see the task; other people on the task are notified.
- **Files** can be removed by the uploader or a manager.
- **Templates and recurring workflows** can be edited and deleted by their creator, a CEO, or a team lead in the same team.
- **Overdue alerts** go once to each owner and the creator when a deadline passes (the scheduler runs this).
- **Notification bell** refreshes every minute while the tab is visible and shows the true unread count.
- **Email copies** of notifications are available but off by default: set `NOTIFY_BY_EMAIL=true`, `FRONTEND_URL` and the `MAIL_*` values.

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md), `backend/.env.production.example`, `frontend/.env.example` and `frontend/vercel.json`. A GitHub Actions workflow in `.github/workflows/ci.yml` runs the backend tests and the frontend tests and build on every push.

## Not done

- **Pagination in the interface.** `GET /api/tasks` now pages when asked (`?per_page=50&page=2`, capped at 100, response includes `meta`) and still returns everything otherwise. The frontend does not use it yet: it still loads the whole list and filters in the browser, which is fine for hundreds of tasks and slow at thousands. Switching the task store and every list view to server-side paging and filtering should be done with the app running.
- **Messenger sending.** Sharing is still copy and paste; nothing posts to Messenger. Email is the supported automatic channel.
- **Browser token storage.** Tokens remain in local storage. Moving to HTTP-only cookies would change how the frontend and backend deploy together.
- **Form requests, API resources and policies** are not introduced; permission checks are still written in each controller.
- **Employee-completion alerts to team leads**, per-task edit notifications and "every weekday" recurrence.
