<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskTemplate;
use Illuminate\Http\Request;
use App\Support\WorkspaceAccess;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $templates = TaskTemplate::with(['team', 'creator'])->when(!$user->isCeo(), fn ($query) => $query->where(fn ($scope) => $scope->whereNull('team_id')->orWhere('created_by', $user->id)->orWhereIn('team_id', $user->teams()->pluck('teams.id'))))->latest()->get();
        return response()->json(['data' => $templates]);
    }

    public function store(Request $request)
    {
        abort_if($request->user()->isEmployee(), 403, 'Only managers can create workflow templates.');
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
            'priority' => 'required|in:normal,high,urgent',
            'default_instructions' => 'nullable|string',
        ]);
        WorkspaceAccess::assertAssignment($request->user(), $validated);

        $template = TaskTemplate::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'team_id' => $validated['team_id'] ?? null,
            'priority' => $validated['priority'],
            'default_instructions' => $validated['default_instructions'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $template->load(['team', 'creator']),
            'message' => 'Template created',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $template = $this->manageable($request, $id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'team_id' => 'sometimes|nullable|exists:teams,id',
            'priority' => 'sometimes|required|in:normal,high,urgent',
            'default_instructions' => 'sometimes|nullable|string',
        ]);
        WorkspaceAccess::assertAssignment($request->user(), $validated);
        $template->update($validated);

        return response()->json(['data' => $template->load(['team', 'creator']), 'message' => 'Template updated']);
    }

    public function destroy(Request $request, $id)
    {
        $this->manageable($request, $id)->delete();

        return response()->json(['message' => 'Template deleted']);
    }

    private function manageable(Request $request, $id): TaskTemplate
    {
        $user = $request->user();
        abort_if($user->isEmployee(), 403, 'Only managers can change workflow templates.');
        $template = TaskTemplate::findOrFail($id);
        abort_unless($user->isCeo() || $template->created_by === $user->id || ($template->team_id && $user->teams()->where('teams.id', $template->team_id)->exists()), 403, 'You can manage templates you created or that belong to your teams.');

        return $template;
    }
}
