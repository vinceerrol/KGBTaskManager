<?php

return [
    // Also email each in-app notification. Needs working MAIL_* settings; off by default.
    'email_enabled' => (bool) env('NOTIFY_BY_EMAIL', false),
    // Used for the "Open the task" link in emails, e.g. https://tasks.example.com
    'frontend_url' => env('FRONTEND_URL', ''),
];
