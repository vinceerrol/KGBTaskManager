<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Mail\TaskNotificationMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'task_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Optional email copy, sent after the response so a mail failure never breaks the request.
        static::created(function (AppNotification $notification) {
            if (! config('task_notifications.email_enabled')) {
                return;
            }
            dispatch(function () use ($notification) {
                try {
                    $recipient = $notification->user;
                    if ($recipient?->is_active) {
                        Mail::to($recipient->email)->send(new TaskNotificationMail($notification));
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            })->afterResponse();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
