<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecurringTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Support\WorkspaceAccess;

class RecurringTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tasks = RecurringTask::with(['team', 'assignee', 'creator'])->when(!$user->isCeo(), fn ($query) => $query->where(fn ($scope) => $scope->where('created_by', $user->id)->orWhere('assigned_to', $user->id)->orWhereIn('team_id', $user->teams()->pluck('teams.id'))))->latest()->get();
        return response()->json(['data' => $tasks]);
    }

    public function store(Request $request)
    {
        abort_if($request->user()->isEmployee(), 403, 'Only managers can create recurring tasks.');
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'required|in:normal,high,urgent',
            'frequency' => 'required|in:daily,weekly,monthly',
            'scheduled_time' => 'required|date_format:H:i',
        ]);
        WorkspaceAccess::assertAssignment($request->user(), $validated);

        $task = RecurringTask::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'team_id' => $validated['team_id'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'priority' => $validated['priority'],
            'frequency' => $validated['frequency'],
            'scheduled_time' => $validated['scheduled_time'],
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $task->update(['next_run_at' => $task->nextOccurrence(Carbon::now('Asia/Manila'))]);

        return response()->json([
            'data' => $task->load(['team', 'assignee', 'creator']),
            'message' => 'Recurring task created',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $task = $this->manageable($request, $id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'team_id' => 'sometimes|nullable|exists:teams,id',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'priority' => 'sometimes|required|in:normal,high,urgent',
            'frequency' => 'sometimes|required|in:daily,weekly,monthly',
            'scheduled_time' => 'sometimes|required|date_format:H:i',
        ]);
        WorkspaceAccess::assertAssignment($request->user(), $validated);
        $task->update($validated);
        if ($task->is_active && (isset($validated['frequency']) || isset($validated['scheduled_time']))) {
            $task->update(['next_run_at' => $task->nextOccurrence(Carbon::now('Asia/Manila'))]);
        }

        return response()->json(['data' => $task->load(['team', 'assignee', 'creator']), 'message' => 'Recurring task updated']);
    }

    public function destroy(Request $request, $id)
    {
        $this->manageable($request, $id)->delete();

        return response()->json(['message' => 'Recurring task deleted']);
    }

    private function manageable(Request $request, $id): RecurringTask
    {
        $user = $request->user();
        abort_if($user->isEmployee(), 403, 'Only managers can change recurring schedules.');
        $task = RecurringTask::findOrFail($id);
        abort_unless($user->isCeo() || $task->created_by === $user->id || $user->teams()->where('teams.id', $task->team_id)->exists(), 403, 'You can manage recurring workflows in your own teams.');

        return $task;
    }

    public function toggle(Request $request, $id)
    {
        $task = $this->manageable($request, $id);
        $active = !$task->is_active;
        $task->update([
            'is_active' => $active,
            'next_run_at' => $active ? $task->nextOccurrence(Carbon::now('Asia/Manila')) : $task->next_run_at,
        ]);

        return response()->json([
            'data' => $task->load(['team', 'assignee', 'creator']),
            'message' => 'Status updated',
        ]);
    }
}
