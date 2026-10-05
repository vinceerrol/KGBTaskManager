<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now('Asia/Manila');
        $today = $now->toDateString();

        $allTasks = Task::visibleTo($request->user())->with(['creator', 'assignee', 'assignees', 'team'])->get();

        // Tasks today (due today, created today, or scheduled today)
        $tasksTodayCount = $allTasks->filter(function ($t) use ($today) {
            return ($t->deadline && $t->deadline->copy()->timezone('Asia/Manila')->toDateString() === $today)
                || ($t->scheduled_at && $t->scheduled_at->copy()->timezone('Asia/Manila')->toDateString() === $today)
                || ($t->created_at->copy()->timezone('Asia/Manila')->toDateString() === $today);
        })->count();

        $inProgressCount = $allTasks->where('status', 'IN PROGRESS')->count();
        $scheduledCount = $allTasks->where('status', 'SCHEDULED')->count();
        $completedCount = $allTasks->where('status', 'DONE')->count();

        // Overdue calculation (Section 14)
        $overdueTasks = $allTasks->filter(function ($t) use ($now) {
            return $t->status !== 'DONE' && $t->deadline && Carbon::parse($t->deadline)->lessThan($now);
        })->values();

        // Attention Needed Section (Section 21)
        // 1. Overdue
        // 2. Starting Soon (scheduled within next 24h)
        $startingSoon = $allTasks->filter(function ($t) use ($now) {
            if ($t->status !== 'SCHEDULED' || !$t->scheduled_at) return false;
            $sched = Carbon::parse($t->scheduled_at);
            return $sched->isFuture() && $sched->diffInHours($now, true) <= 24;
        })->values();

        // 3. Due Today (not done yet)
        $dueToday = $allTasks->filter(function ($t) use ($today) {
            return $t->status !== 'DONE' && $t->deadline && $t->deadline->copy()->timezone('Asia/Manila')->toDateString() === $today;
        })->values();

        return response()->json([
            'tasks_today' => $tasksTodayCount,
            'in_progress' => $inProgressCount,
            'scheduled' => $scheduledCount,
            'overdue' => $overdueTasks->count(),
            'completed' => $completedCount,
            'attention_needed' => [
                'overdue_tasks' => $overdueTasks->take(5),
                'starting_soon' => $startingSoon->take(5),
                'due_today' => $dueToday->take(5),
            ],
        ]);
    }
}
