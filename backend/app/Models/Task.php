<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\StoresUtcDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Task extends Model
{
    use HasFactory, StoresUtcDates;

    protected $fillable = [
        'title',
        'description',
        'created_by',
        'assigned_to',
        'team_id',
        'status', // SCHEDULED, IN PROGRESS, DONE
        'priority', // normal, high, urgent
        'scheduled_at',
        'deadline',
        'started_at',
        'completed_at',
        'completion_note',
        'creation_method',
        'source_brief',
        'creation_key',
        'creation_payload_hash',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'deadline' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = ['creation_key', 'creation_payload_hash'];

    protected $appends = [
        'is_overdue',
    ];

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'DONE' || !$this->deadline) {
            return false;
        }

        return Carbon::now()->greaterThan($this->deadline);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isCeo()) return $query;
        $teamIds = $user->teams()->pluck('teams.id');
        return $query->where(function ($scope) use ($user, $teamIds) {
            $scope->where('created_by', $user->id)->orWhere('assigned_to', $user->id)
                ->orWhereIn('team_id', $teamIds)
                ->orWhereHas('assignees', fn ($owners) => $owners->where('users.id', $user->id));
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_user')->withTimestamps();
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at')->orderBy('id');
    }

    public function activities()
    {
        return $this->hasMany(TaskActivity::class)->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }
}
