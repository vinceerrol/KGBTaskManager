<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskBriefInterpreter;
use App\Support\WorkspaceAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaskDraftController extends Controller
{
    public function context(Request $request)
    {
        abort_if($request->user()->isEmployee(), 403, 'Only managers can create tasks.');
        $actor = $request->user();
        $teamIds = $actor->teams()->pluck('teams.id');
        $teams = Team::with('members:id')->when(! $actor->isCeo(), fn ($q) => $q->whereIn('id', $teamIds))->get();
        $people = User::with('teams:id,name')->when(! $actor->isCeo(), fn ($q) => $q->where(fn ($scope) => $scope->where('id', $actor->id)->orWhereHas('teams', fn ($t) => $t->whereIn('teams.id', $teamIds))))->get();

        return response()->json(['data' => [
            'people' => $people->map(fn ($person) => ['id' => $person->id, 'name' => $person->name, 'teams' => $person->teams->filter(fn ($t) => $actor->isCeo() || $teamIds->contains($t->id))->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()])->values(),
            'teams' => $teams->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'member_ids' => $team->members->pluck('id')->values()])->values(),
            'reference_at' => Carbon::now()->toIso8601String(),
            'timezone' => 'Asia/Manila',
            'ai_enabled' => TaskBriefInterpreter::enabled(),
            'ai_provider' => TaskBriefInterpreter::enabled() ? TaskBriefInterpreter::provider() : null,
        ]]);
    }

    public function interpret(Request $request, TaskBriefInterpreter $interpreter)
    {
        abort_if($request->user()->isEmployee(), 403, 'Only managers can create tasks.');
        $input = $request->validate([
            'brief' => 'required|string|max:6000',
            'revision' => 'required|integer|min:0|max:1000000',
            'reference_at' => 'required|date',
            'mentions' => 'present|array|max:50',
            'mentions.*.kind' => 'required|in:person,team',
            'mentions.*.id' => 'required|integer|min:1',
            'mentions.*.label' => 'required|string|max:255',
            'mentions.*.start' => 'required|integer|min:0|max:12000',
            'mentions.*.end' => 'required|integer|min:1|max:12000',
        ]);
        $reference = Carbon::parse($input['reference_at']);
        abort_if($reference->greaterThan(Carbon::now()->addMinutes(5)) || $reference->lessThan(Carbon::now()->subDays(7)), 422, 'Reopen this draft to refresh its date reference.');
        $mentions = [];
        $utf16 = mb_convert_encoding($input['brief'], 'UTF-16LE', 'UTF-8');
        foreach ($input['mentions'] as $mention) {
            $model = $mention['kind'] === 'person' ? User::findOrFail($mention['id']) : Team::findOrFail($mention['id']);
            WorkspaceAccess::assertAssignment($request->user(), $mention['kind'] === 'person' ? ['assignee_ids' => [$model->id]] : ['team_id' => $model->id]);
            $span = $mention['end'] - $mention['start'];
            $actual = $span > 0 ? mb_convert_encoding(substr($utf16, $mention['start'] * 2, $span * 2), 'UTF-8', 'UTF-16LE') : '';
            abort_unless($mention['label'] === $model->name && $actual === '@'.$model->name, 422, 'Select the updated person or team from the mention suggestions.');
            $mentions[] = $mention;
        }

        if (! TaskBriefInterpreter::enabled()) {
            return response()->json(['revision' => $input['revision'], 'status' => 'local', 'data' => null]);
        }
        $result = $interpreter->interpret($input['brief'], $mentions, $reference);

        return response()->json(['revision' => $input['revision'], 'status' => $result ? 'interpreted' : 'unavailable', 'data' => $result, 'retry_after' => $interpreter->retryAfter()]);
    }
}
