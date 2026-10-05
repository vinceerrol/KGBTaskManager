<?php

use App\Mail\TaskNotificationMail;
use App\Models\AppNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// The 10 minute lock expiry stops a crashed run from blocking the scheduler for the default 24 hours.
Schedule::command('tasks:check-scheduled')->everyMinute()->withoutOverlapping(10);
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Artisan::command('notifications:test-email {email}', function (string $email) {
    $notification = new AppNotification(['title' => 'Test email from KCG Task Manager', 'message' => 'If you can read this, email notifications are set up correctly.']);
    try {
        Mail::to($email)->send(new TaskNotificationMail($notification));
    } catch (\Throwable $e) {
        $this->error('Sending failed: '.$e->getMessage());
        $this->line('Check MAIL_USERNAME, MAIL_PASSWORD (a Gmail App Password, not your normal password) and run php artisan config:clear.');

        return 1;
    }
    $this->info("Sent a test email to {$email} using the '".config('mail.default')."' mailer.");
    if (config('mail.default') === 'log') {
        $this->warn('The mailer is "log", so nothing was really sent: it was written to storage/logs/laravel.log. Set MAIL_MAILER=smtp to send real email.');
    }

    return 0;
})->purpose('Send one test notification email to check the mail settings');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
