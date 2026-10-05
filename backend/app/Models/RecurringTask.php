<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Models\Concerns\StoresUtcDates;

class RecurringTask extends Model
{
    use HasFactory, StoresUtcDates;

    protected $fillable = [
        'title',
        'description',
        'team_id',
        'assigned_to',
        'priority',
        'frequency',
        'scheduled_time',
        'is_active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /** Next future occurrence, anchored to the creation weekday or day of month. */
    public function nextOccurrence(CarbonInterface $after): Carbon
    {
        $candidate = Carbon::instance($after)->timezone('Asia/Manila')->setTimeFromTimeString($this->scheduled_time);
        $anchor = $this->created_at ? $this->created_at->copy()->timezone('Asia/Manila') : $candidate;

        if ($this->frequency === 'weekly') {
            $candidate->addDays(($anchor->dayOfWeek - $candidate->dayOfWeek + 7) % 7);
            if ($candidate->lessThanOrEqualTo($after)) $candidate->addWeek();
        } elseif ($this->frequency === 'monthly') {
            $candidate->day(min($anchor->day, $candidate->daysInMonth));
            if ($candidate->lessThanOrEqualTo($after)) {
                $candidate->startOfMonth()->addMonth()->day(min($anchor->day, $candidate->daysInMonth))->setTimeFromTimeString($this->scheduled_time);
            }
        } elseif ($candidate->lessThanOrEqualTo($after)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
