# Deployment guide

The frontend and backend deploy separately. Written 5 October 2026 and not yet exercised on a real host, so treat the first deployment as a rehearsal.

## Before you start

- Use PHP 8.3+, MySQL 8+ and Composer on the backend host.
- Use a **private** GitHub repository. Never commit `backend/.env`.
- Choose a host that allows a cron job **every minute**. Scheduled starts, recurring tasks and deadline reminders depend on it. Many shared plans only allow 5 to 15 minutes; a small VPS is safer.

## Backend

1. Upload or clone the repository. Point the web root at `backend/public`.
2. Install dependencies: `composer install --no-dev --optimize-autoloader`.
3. Copy `backend/.env.production.example` to `backend/.env` and fill it in. `APP_DEBUG` must be `false`.
4. Generate the key once: `php artisan key:generate`.
5. Create the tables: `php artisan migrate --force`.
6. Create real accounts. The seeder creates demo users whose password is `password`; do not run it in production. Use the admin screens or `php artisan tinker`.
7. Cache configuration: `php artisan config:cache && php artisan route:cache`.
8. Make `storage/` and `bootstrap/cache/` writable by the web user.
9. Add the scheduler cron job, replacing the path:

   ```cron
   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
   ```

Attachments are stored on the **private** disk (`storage/app/private`) and served through signed, 30 minute links, so `php artisan storage:link` is not needed for new files. Files uploaded before 5 October 2026 live in `storage/app/public` and stay publicly reachable; move them with `php artisan attachments:make-private` once that command is run on the server.

## Frontend

1. Copy `frontend/.env.example` to `frontend/.env.production` and set `VITE_API_URL` to the backend address including `/api`.
2. Build: `npm ci && npm run build`.
3. Vercel: set the root directory to `frontend` and add `VITE_API_URL` as a project environment variable. `frontend/vercel.json` rewrites every path to `index.html` so links such as `/tasks/123` survive a refresh. On other hosts add the same rule.
4. Add the frontend's address to `CORS_ALLOWED_ORIGINS` in the backend `.env`, then run `php artisan config:cache` again.

## Backups

- Dump MySQL daily, for example `mysqldump --single-transaction DB_NAME | gzip > backup-$(date +%F).sql.gz`, and keep copies off the server.
- Back up `backend/storage/app/private` (attachments) the same way.
- Test a restore at least once.

## Operations

- Logs rotate daily in `backend/storage/logs`. Consider an error tracker such as Sentry.
- Sign-in tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default one week); expired tokens are pruned daily.
- After pulling an update run `php artisan migrate --force && php artisan config:cache`.
