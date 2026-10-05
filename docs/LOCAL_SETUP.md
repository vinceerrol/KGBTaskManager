# Running locally for the team

How to run KCG Task Manager on one office PC so teammates on the same Wi-Fi can use it, with free Gmail notifications and Messenger sharing. Written 5 October 2026.

## 1. Start the app (three terminals)

```powershell
# Terminal 1: backend
cd backend
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2: frontend, reachable from other devices on the Wi-Fi
cd frontend
npm run dev -- --host 0.0.0.0

# Terminal 3: scheduler (scheduled starts, recurring tasks, deadline and overdue alerts)
cd backend
php artisan schedule:work
```

Without terminal 3, nothing time-based happens: scheduled tasks never start and nobody is told about deadlines.

## 2. Let teammates open it

1. Find this PC's address: run `ipconfig` and read the **IPv4 Address** under Wi-Fi. When this guide was written it was `192.168.0.58`. It can change after a router restart; setting a fixed (reserved) address in the router stops that.
2. Teammates open `http://192.168.0.58:5173` in a browser on the same Wi-Fi. The first time, Windows may ask whether Node.js can use the network: allow it on **Private networks**.
3. So that shared links and email links use that address instead of `localhost`:
   - `frontend/.env.local`: `VITE_PUBLIC_APP_URL=http://192.168.0.58:5173` (restart `npm run dev` afterwards)
   - `backend/.env`: `FRONTEND_URL=http://192.168.0.58:5173` (then `php artisan config:clear`)

Links only work while this PC is on and the app is running, and only for people on the same network. For access from anywhere, follow [DEPLOYMENT.md](DEPLOYMENT.md).

## 3. Free email notifications with Gmail

Gmail's own mail server is free and needs no domain. A personal Gmail account can send about 500 emails a day, which is plenty for a small team.

1. Pick the Gmail account that will **send** the emails (a shared one such as `kcg.tasks@gmail.com` is best).
2. Turn on **2-Step Verification** for it.
3. Create an **App Password** at <https://myaccount.google.com/apppasswords>. Google shows 16 characters.
4. In `backend/.env`, find the Gmail block and set:

   ```dotenv
   MAIL_MAILER=smtp
   MAIL_USERNAME=kcg.tasks@gmail.com
   MAIL_PASSWORD=abcdefghijklmnop   # the App Password, without spaces
   NOTIFY_BY_EMAIL=true
   ```

5. Run `php artisan config:clear`, then `php artisan notifications:test-email your.name@gmail.com`. You should receive "Test email from KCG Task Manager" within a minute. Check Spam the first time and mark it "Not spam".

From then on, every in-app notification (assignment, comment, deadline approaching, overdue, completed) is also emailed to the person's account email. Make sure each account in **People & teams** has their real Gmail address.

Never put the App Password in the chat, a document or git. If it leaks, delete it on the same Google page and create a new one.

## 4. Sending a task to a Messenger group chat

Open a task (or press the share icon on a card) and choose **Copy & open Messenger**.

- **On a phone:** the message is copied, and Messenger's **Send to** list opens with the task link. Choose the group chat, then press and hold in the message box, **Paste**, and send. If the phone offers **Share…**, that opens the phone's share sheet with the full message, and you can choose Messenger from there.
- **On a computer:** the message is copied and Messenger opens in a new tab. Choose the group chat, press **Ctrl+V** and send.

The message contains the title, the instructions, the owners, team, priority, status, the deadline and the link to the task. Whoever opens the link signs in and lands on that task.

Picking the chat automatically, or sending without anyone pressing Send, needs a Meta app and a Facebook Page. That is planned for later.
