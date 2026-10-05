<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now('Asia/Manila');
        $viewer = $request->user();
        $viewerTeamIds = $viewer->isCeo() ? collect() : $viewer->teams()->pluck('teams.id');

        $users = User::with(['teams', 'assignedTasks'])->when(!$viewer->isCeo(), fn ($query) => $query->where(fn ($scope) => $scope->where('id', $viewer->id)->orWhereHas('teams', fn ($teams) => $teams->whereIn('teams.id', $viewerTeamIds))))->get()->map(function ($user) use ($now, $viewer, $viewerTeamIds) {
            $assigned = $user->assignedTasks;
            $activeCount = $assigned->where('status', 'IN PROGRESS')->count();
            $dueTodayCount = $assigned->filter(function ($t) use ($now) {
                return $t->status !== 'DONE' && $t->deadline && $t->deadline->copy()->timezone('Asia/Manila')->isToday();
            })->count();
            $overdueCount = $assigned->filter(function ($t) use ($now) {
                return $t->status !== 'DONE' && $t->deadline && Carbon::parse($t->deadline)->isPast();
            })->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                // Non-administrators only see team memberships inside their own teams.
                'teams' => $viewer->isCeo() ? $user->teams : $user->teams->whereIn('id', $viewerTeamIds)->values(),
                'active_tasks_count' => $activeCount,
                'due_today_count' => $dueTodayCount,
                'overdue_count' => $overdueCount,
            ];
        });

        return response()->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $this->assertAdministrator($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => 'required|in:ceo,team_lead,employee',
            'password' => 'required|string|min:10',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'integer|exists:teams,id',
        ]);

        $user = User::create(collect($validated)->only(['name', 'email', 'role', 'password'])->all());
        $user->teams()->sync($validated['team_ids'] ?? []);

        return response()->json(['data' => $user->load('teams'), 'message' => 'Account created'], 201);
    }

    public function update(Request $request, $id)
    {
        $this->assertAdministrator($request);
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => 'sometimes|required|in:ceo,team_lead,employee',
            'is_active' => 'sometimes|boolean',
            'password' => 'sometimes|nullable|string|min:10',
            'team_ids' => 'sometimes|array',
            'team_ids.*' => 'integer|exists:teams,id',
        ]);

        $demotes = isset($validated['role']) && $validated['role'] !== 'ceo' && $user->isCeo();
        $deactivates = array_key_exists('is_active', $validated) && ! $validated['is_active'] && $user->is_active;
        if ($user->id === $request->user()->id && ($demotes || $deactivates)) {
            abort(422, 'You cannot remove your own administrator access or deactivate your own account.');
        }
        if (($demotes || ($deactivates && $user->isCeo())) && User::where('role', 'ceo')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist()) {
            abort(422, 'Keep at least one active administrator.');
        }

        $user->fill(collect($validated)->only(['name', 'email', 'role', 'is_active'])->all());
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();
        if (array_key_exists('team_ids', $validated)) {
            $user->teams()->sync($validated['team_ids']);
        }
        // A deactivated account or a changed password signs the person out everywhere.
        if ($deactivates || ! empty($validated['password'])) {
            $user->tokens()->delete();
        }

        return response()->json(['data' => $user->load('teams'), 'message' => 'Account updated']);
    }

    private function assertAdministrator(Request $request): void
    {
        abort_unless($request->user()->isCeo(), 403, 'Only the workspace administrator can manage accounts.');
    }
}
