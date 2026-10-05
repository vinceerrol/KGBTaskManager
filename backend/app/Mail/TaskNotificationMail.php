<?php

namespace App\Mail;

use App\Models\AppNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TaskNotificationMail extends Mailable
{
    public function __construct(public AppNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->title);
    }

    public function content(): Content
    {
        $frontend = trim((string) config('task_notifications.frontend_url'));

        return new Content(
            htmlString: '<p>'.e($this->notification->message).'</p>'
                .($frontend && $this->notification->task_id ? '<p><a href="'.e(rtrim($frontend, '/').'/tasks/'.$this->notification->task_id).'">Open the task</a></p>' : ''),
        );
    }
}
