# October 2026 update

Written 5 October 2026 after a security, frontend and spec audit.

## Verification status (5 October 2026)

- Backend: `php artisan test` passes, 56 tests and 367 assertions, including the new `ManagementTest` and extra `TaskInteractionTest` cases.
- Frontend: `npm test` passes 28 of 28, and `npm run build` (type-check plus production build) succeeds.
- The two pending migrations (`add_brief_creation_to_tasks` and `add_management_features`) were applied to the local MySQL development database, and `php artisan attachments:make-private` found no old public files to move.
- Browser checks (headless Chrome against the running app on a throwaway seeded SQLite database, not the real data): 28 of 28 passed with no unexpected console or server errors. They covered creating and deactivating an account, creating and deleting a team, the 60-card cap and "Show more" on 136 tasks, posting a comment, editing a template, the change-password validation, an employee being kept out of `/people`, uploading an allowed file, the signed download link (200 as an attachment; tampered, unsigned and removed links refused), an HTML upload being rejected, removing a file, and phone-width (390px) layouts of People, Workflow library, All tasks, task detail and the New account dialog.
- The phone check found and fixed a real bug: the visually hidden "Actions" column header in the People table stretched the page 200px wider than a phone. `.table-wrap` now clips it.
- The email path was checked with the log mailer: right recipient and subject, HTML-escaped message and a working "Open the task" link built from `FRONTEND_URL`.
- **Not yet verified:** a real host, real SMTP credentials, the overdue alerts over real time, and tablet widths. Treat the first deployment as a rehearsal.

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

- **Server-side paging in the interface.** `GET /api/tasks` pages when asked (`?per_page=50&page=2`, capped at 100, response includes `meta`) and still returns everything otherwise. Task lists and board columns now draw 60 cards at a time with a "Show more" button, which removes the browser freeze. The frontend still downloads the whole list and filters it in the browser. That is fine for hundreds of tasks; revisit it when you pass about 2,000, because focus mode, saved views, the dashboard counts and search all read that list and would need to move to the server together.
- **Messenger sending.** Sharing is still copy and paste; nothing posts to Messenger. Email is the supported automatic channel.
- **Browser token storage.** Tokens remain in local storage. Moving to HTTP-only cookies would change how the frontend and backend deploy together.
- **Form requests, API resources and policies** are not introduced; permission checks are still written in each controller.
- **Employee-completion alerts to team leads**, per-task edit notifications and "every weekday" recurrence.
