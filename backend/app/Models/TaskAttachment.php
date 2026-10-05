<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class TaskAttachment extends Model
{
    use HasFactory;

    public const DISK = 'local';

    protected $fillable = [
        'task_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected $hidden = ['file_path'];

    protected $appends = ['url'];

    /**
     * New files live on the private disk and are served through a short-lived signed route.
     * Rows created before that change still hold their original public URL.
     */
    public function getUrlAttribute(): string
    {
        $path = (string) $this->file_path;
        if (! str_starts_with($path, 'attachments/')) {
            return $path;
        }

        return URL::temporarySignedRoute('attachments.download', now()->addMinutes(30), ['attachment' => $this->id]);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
