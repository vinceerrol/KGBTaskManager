<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now('Asia/Manila');

        $teams = Team::with(['members', 'tasks'])->when(!$request->user()->isCeo(), fn ($query) => $query->whereHas('members', fn ($members) => $members->where('users.id', $request->user()->id)))->get()->map(function ($team) use ($now) {
            $tasks = $team->tasks;
            $activeTasks = $tasks->where('status', 'IN PROGRESS');
            $dueToday = $tasks->filter(function ($t) use ($now) {
                return $t->status !== 'DONE' && $t->deadline && $t->deadline->copy()->timezone('Asia/Manila')->isToday();
            });
            $overdue = $tasks->filter(function ($t) use ($now) {
                return $t->status !== 'DONE' && $t->deadline && Carbon::parse($t->deadline)->isPast();
            });

            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members_count' => $team->members->count(),
                'active_tasks_count' => $activeTasks->count(),
                'due_today_count' => $dueToday->count(),
                'overdue_count' => $overdue->count(),
                'created_at' => $team->created_at,
            ];
        });

        return response()->json([
            'data' => $teams,
        ]);
    }

    public function show(Request $request, $id)
    {
        $team = Team::with(['members'])->findOrFail($id);
        abort_unless($request->user()->isCeo() || $team->members()->where('users.id', $request->user()->id)->exists(), 403, 'This team is outside your workspace access.');
        $now = Carbon::now('Asia/Manila');

        // Enhance members with their individual workloads (Section 7 & Section 10)
        // One query for the whole team instead of one per member.
        $teamTasks = Task::where('team_id', $team->id)->with('assignees:id')->get();
        $membersWithWorkload = $team->members->map(function ($user) use ($teamTasks, $now) {
            $userTasks = $teamTasks->filter(fn ($task) => $task->assigned_to === $user->id || $task->assignees->contains('id', $user->id));
            $activeCount = $userTasks->where('status', 'IN PROGRESS')->count();
            $dueTodayCount = $userTasks->filter(function ($t) use ($now) {
                return $t->status !== 'DONE' && $t->deadline && $t->deadline->copy()->timezone('Asia/Manila')->isToday();
            })->count();
            $overdueCount = $userTasks->filter(function ($t) use ($now) {
                return $t->status !== 'DONE' && $t->deadline && Carbon::parse($t->deadline)->isPast();
            })->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'active_tasks_count' => $activeCount,
                'due_today_count' => $dueTodayCount,
                'overdue_count' => $overdueCount,
            ];
        });

        $tasks = $team->tasks()->with(['creator', 'assignee', 'assignees'])->latest()->get();

        return response()->json([
            'data' => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members' => $membersWithWorkload,
                'tasks' => $tasks,
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isCeo(), 403, 'Only the workspace administrator can create teams.');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['member_ids'])) {
            $team->members()->sync($validated['member_ids']);
        }

        return response()->json([
            'data' => $team->load('members'),
            'message' => 'Team created',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        abort_unless($request->user()->isCeo(), 403, 'Only the workspace administrator can edit teams.');
        $team = Team::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'member_ids' => 'sometimes|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $team->fill(collect($validated)->only(['name', 'description'])->all())->save();
        if (array_key_exists('member_ids', $validated)) {
            $team->members()->sync($validated['member_ids']);
        }

        return response()->json(['data' => $team->load('members'), 'message' => 'Team updated']);
    }

    public function destroy(Request $request, $id)
    {
        abort_unless($request->user()->isCeo(), 403, 'Only the workspace administrator can delete teams.');
        $team = Team::findOrFail($id);
        abort_if($team->tasks()->where('status', '!=', 'DONE')->exists(), 409, 'Finish or reassign this team\'s open tasks before deleting it.');
        $team->delete();

        return response()->json(['message' => 'Team deleted']);
    }
}
