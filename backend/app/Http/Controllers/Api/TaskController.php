<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Team;
use App\Support\WorkspaceAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::visibleTo($request->user())->with(['creator', 'assignee', 'assignees', 'team'])->latest();

        // Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'OVERDUE') {
                $query->where('status', '!=', 'DONE')
                    ->whereNotNull('deadline')
                    ->where('deadline', '<', Carbon::now());
            } else {
                $query->where('status', $request->status);
            }
        }

        // Team Filter
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        // Assignee Filter
        if ($request->filled('assigned_to')) {
            $userId = $request->assigned_to;
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)
                  ->orWhereHas('assignees', fn($sub) => $sub->where('users.id', $userId));
            });
        }

        // Priority Filter
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Date Filter
        if ($request->filled('date_filter')) {
            $now = Carbon::now('Asia/Manila');
            if (in_array($request->date_filter, ['today', 'tomorrow'])) {
                $start = $now->copy()->startOfDay();
                if ($request->date_filter === 'tomorrow') $start->addDay();
                $end = $start->copy()->addDay()->utc();
                $start->utc();
                $columns = $request->date_filter === 'today' ? ['deadline', 'scheduled_at', 'created_at'] : ['deadline', 'scheduled_at'];
                $query->where(function ($q) use ($start, $end, $columns) {
                    foreach ($columns as $column) $q->orWhere(fn ($range) => $range->where($column, '>=', $start)->where($column, '<', $end));
                });
            } elseif ($request->date_filter === 'week') {
                $startOfWeek = $now->copy()->startOfWeek();
                $endOfWeek = $startOfWeek->copy()->addWeek()->utc();
                $query->where('deadline', '>=', $startOfWeek->utc())->where('deadline', '<', $endOfWeek);
            }
        }

        // Global Search (Section 18)
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('description', 'like', $search)
                  ->orWhereHas('assignee', function ($sub) use ($search) {
                      $sub->where('name', 'like', $search);
                  })
                  ->orWhereHas('assignees', function ($sub) use ($search) {
                      $sub->where('name', 'like', $search);
                  })
                  ->orWhereHas('team', function ($sub) use ($search) {
                      $sub->where('name', 'like', $search);
                  });
            });
        }

        // Opt-in paging: clients that send per_page get one page plus totals; everything else still gets the full list.
        if ($request->filled('per_page')) {
            $page = $query->orderByDesc('id')->paginate(min(100, max(1, (int) $request->per_page)));

            return response()->json([
                'data' => $page->items(),
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            ]);
        }

        $tasks = $query->get();

        return response()->json([
            'data' => $tasks,
        ]);
    }

    public function myTasks(Request $request)
    {
        $user = $request->user();
        $userTeamIds = $user->teams()->pluck('teams.id');

        $tasks = Task::with(['creator', 'assignee', 'assignees', 'team'])
            ->where(function ($q) use ($user, $userTeamIds) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('assignees', function ($sub) use ($user) {
                      $sub->where('users.id', $user->id);
                  })
                  ->orWhere(function ($sub) use ($userTeamIds) {
                      $sub->whereNull('assigned_to')
                          ->whereDoesntHave('assignees')
                          ->whereIn('team_id', $userTeamIds);
                  });
            })
            ->latest()
            ->get();

        return response()->json([
            'data' => $tasks,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->isEmployee()) {
            return response()->json([
                'message' => 'Employees cannot create tasks. Only CEO and Team Leads have task creation permissions.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
            'assigned_to' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
            'priority' => 'required|in:normal,high,urgent',
            'start_type' => 'required|in:now,scheduled',
            'scheduled_at' => 'nullable|date',
            'deadline' => 'nullable|date',
            'creation_method' => 'nullable|in:fields,brief',
            'source_brief' => 'required_if:creation_method,brief|nullable|string|max:6000',
            'creation_key' => 'required_if:creation_method,brief|nullable|uuid',
            'assignment_scope' => 'required_if:creation_method,brief|nullable|in:people,team,unassigned',
        ]);

        $this->authorizeAssignment($request, $validated);

        $payloadHash = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR));
        if (!empty($validated['creation_key'])) {
            $existing = Task::where('created_by', $request->user()->id)->where('creation_key', $validated['creation_key'])->first();
            if ($existing) return $this->creationReplay($existing, $payloadHash);
        }
        if (($validated['creation_method'] ?? '') === 'brief') {
            $errors = [];
            $owners = $validated['assignee_ids'] ?? [];
            if (!empty($validated['assigned_to']) && !in_array($validated['assigned_to'], $owners)) $errors['assignee_ids'] = 'The primary owner must be one of the selected individual owners.';
            $scope = $validated['assignment_scope'];
            if ($scope === 'people' && !$owners || $scope === 'team' && (empty($validated['team_id']) || $owners) || $scope === 'unassigned' && (!empty($validated['team_id']) || $owners)) $errors['assignment_scope'] = 'Choose individual owners, an entire team, or a general unassigned task.';
            if ($validated['start_type'] === 'scheduled' && (empty($validated['scheduled_at']) || Carbon::parse($validated['scheduled_at'])->lessThanOrEqualTo(Carbon::now()))) $errors['scheduled_at'] = 'Choose a start date and time in the future.';
            if (!empty($validated['deadline']) && Carbon::parse($validated['deadline'])->lessThanOrEqualTo(Carbon::now())) $errors['deadline'] = 'Choose a deadline in the future.';
            if ($validated['start_type'] === 'scheduled' && !empty($validated['scheduled_at']) && !empty($validated['deadline']) && Carbon::parse($validated['deadline'])->lessThan(Carbon::parse($validated['scheduled_at']))) $errors['deadline'] = 'The deadline must come after the scheduled start.';
            if ($errors) throw ValidationException::withMessages($errors);
        }

        try {
        return DB::transaction(function () use ($request, $validated, $payloadHash) {

        $now = Carbon::now('Asia/Manila');
        $status = 'IN PROGRESS';
        $startedAt = $now;
        $scheduledAt = null;

        if ($validated['start_type'] === 'scheduled' && !empty($validated['scheduled_at'])) {
            $parsedScheduled = Carbon::parse($validated['scheduled_at']);
            if ($parsedScheduled->isFuture()) {
                $status = 'SCHEDULED';
                $startedAt = null;
                $scheduledAt = $parsedScheduled;
            }
        }

        $assigneeIds = $request->input('assignee_ids', []);
        if (empty($assigneeIds) && !empty($validated['assigned_to'])) {
            $assigneeIds = [(int)$validated['assigned_to']];
        }
        $primaryAssignee = !empty($assigneeIds) ? $assigneeIds[0] : ($validated['assigned_to'] ?? null);

        $task = Task::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
            'assigned_to' => $primaryAssignee,
            'team_id' => $validated['team_id'] ?? null,
            'status' => $status,
            'priority' => $validated['priority'],
            'scheduled_at' => $scheduledAt,
            'deadline' => !empty($validated['deadline']) ? Carbon::parse($validated['deadline']) : null,
            'started_at' => $startedAt,
            'creation_method' => $validated['creation_method'] ?? 'fields',
            'source_brief' => $validated['source_brief'] ?? null,
            'creation_key' => $validated['creation_key'] ?? null,
            'creation_payload_hash' => !empty($validated['creation_key']) ? $payloadHash : null,
        ]);

        if (!empty($assigneeIds)) {
            $task->assignees()->sync($assigneeIds);
        }

        // Record Activity
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'created',
            'metadata' => [
                'status' => $status,
                'priority' => $task->priority,
                'assignee_ids' => $assigneeIds,
            ],
        ]);

        // Notify Assignees (Section 15)
        $notifyUserIds = collect($assigneeIds);
        if ($notifyUserIds->isEmpty() && $task->team_id) $notifyUserIds = Team::findOrFail($task->team_id)->members()->pluck('users.id');
        if ($task->assigned_to && !$notifyUserIds->contains($task->assigned_to)) {
            $notifyUserIds->push($task->assigned_to);
        }

        foreach ($notifyUserIds as $assigneeId) {
            if ($assigneeId != $request->user()->id) {
                AppNotification::create([
                    'user_id' => $assigneeId,
                    'title' => '📋 New Task Assigned',
                    'message' => "{$request->user()->name} assigned you: \"{$task->title}\"",
                    'task_id' => $task->id,
                ]);
            }
        }

        return response()->json([
            'data' => $task->load(['creator', 'assignee', 'assignees', 'team']),
            'message' => 'Task created successfully',
        ], 201);
        });
        } catch (QueryException $error) {
            // Concurrent retries collide on the actor/key unique index; the first commit wins.
            if (!empty($validated['creation_key'])) {
                $existing = Task::where('created_by', $request->user()->id)->where('creation_key', $validated['creation_key'])->first();
                if ($existing) return $this->creationReplay($existing, $payloadHash);
            }
            throw $error;
        }
    }

    private function creationReplay(Task $task, string $payloadHash)
    {
        abort_unless(hash_equals($task->creation_payload_hash ?? '', $payloadHash), 409, 'This draft was already created with different details. Open the created task before trying again.');
        return response()->json(['data' => $task->load(['creator', 'assignee', 'assignees', 'team']), 'message' => 'Task already created', 'replayed' => true]);
    }

    public function show(Request $request, $id)
    {
        $task = Task::visibleTo($request->user())->with([
            'creator',
            'assignee',
            'assignees',
            'team',
            'attachments.uploader',
            'activities.user',
            'comments.user',
        ])->findOrFail($id);

        return response()->json([
            'data' => $task,
        ]);
    }

    public function update(Request $request, $id)
    {
        abort_if($request->user()->isEmployee(), 403, 'Only managers can edit task details.');
        $task = Task::findOrFail($id);
        $this->authorizeManagement($request, $task);
        $previousOwners = $task->assignees()->pluck('users.id');
        if ($task->assigned_to) $previousOwners->push($task->assigned_to);
        if ($previousOwners->isEmpty() && $task->team) $previousOwners = $task->team->members()->pluck('users.id');
        $previousTeamId = $task->team_id;

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
            'assigned_to' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
            'priority' => 'sometimes|required|in:normal,high,urgent',
            'deadline' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
        ]);

        $this->authorizeAssignment($request, $validated);

        $task->update(collect($validated)->except('assignee_ids')->all());

        // Clearing a scheduled start would otherwise leave the task SCHEDULED forever, since the scheduler skips tasks without a time.
        if ($task->status === 'SCHEDULED' && ! $task->scheduled_at) {
            $task->update(['status' => 'IN PROGRESS', 'started_at' => Carbon::now('Asia/Manila')]);
        }

        if ($request->has('assignee_ids') || $request->has('assigned_to')) {
            $assigneeIds = $request->has('assignee_ids') ? $request->input('assignee_ids', []) : ($request->assigned_to ? [(int) $request->assigned_to] : []);
            $task->assignees()->sync($assigneeIds);
            if (!empty($assigneeIds)) {
                $task->assigned_to = $assigneeIds[0];
            } else {
                $task->assigned_to = null;
            }
            $task->save();
        }

        $task->unsetRelation('team');
        $newOwners = $task->assignees()->pluck('users.id');
        if ($task->assigned_to) $newOwners->push($task->assigned_to);
        if ($newOwners->isEmpty() && $task->team) $newOwners = $task->team->members()->pluck('users.id');
        foreach ($newOwners->unique()->diff($previousOwners) as $ownerId) {
            if ($ownerId !== $request->user()->id) AppNotification::create([
                'user_id' => $ownerId, 'task_id' => $task->id,
                'title' => 'Task assignment updated',
                'message' => $request->user()->name . ' assigned you: ' . $task->title,
            ]);
        }

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'updated',
            'metadata' => ['assignment_changed' => $previousTeamId !== $task->team_id || $previousOwners->diff($newOwners)->isNotEmpty() || $newOwners->diff($previousOwners)->isNotEmpty()],
        ]);

        return response()->json([
            'data' => $task->load(['creator', 'assignee', 'assignees', 'team']),
            'message' => 'Task updated',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        abort_unless($request->user()->isCeo(), 403, 'Only the workspace administrator can delete tasks.');
        $task = Task::findOrFail($id);
        $task->delete();

        return response()->json([
            'message' => 'Task deleted',
        ]);
    }

    public function start(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $this->authorizeTaskAction($request, $task);
        abort_unless($task->status === 'SCHEDULED', 409, 'Only scheduled tasks can be started. Refresh to see the latest status.');
        $task->update([
            'status' => 'IN PROGRESS',
            'started_at' => Carbon::now('Asia/Manila'),
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'started',
        ]);

        return response()->json([
            'data' => $task->load(['creator', 'assignee', 'team']),
            'message' => 'Task started',
        ]);
    }

    public function complete(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $this->authorizeTaskAction($request, $task);
        abort_if($task->status === 'DONE', 409, 'This task is already completed. Refresh to see the latest status.');
        $validated = $request->validate([
            'completion_note' => 'nullable|string',
        ]);

        $task->update([
            'status' => 'DONE',
            'completed_at' => Carbon::now('Asia/Manila'),
            'completion_note' => $validated['completion_note'] ?? null,
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'completed',
            'metadata' => [
                'completion_note' => $validated['completion_note'] ?? null,
            ],
        ]);

        // Notify Creator / Lead (Section 13 & 15)
        if ($task->created_by && $task->created_by !== $request->user()->id) {
            AppNotification::create([
                'user_id' => $task->created_by,
                'title' => '✅ Task Completed',
                'message' => "{$request->user()->name} completed: \"{$task->title}\"",
                'task_id' => $task->id,
            ]);
        }

        return response()->json([
            'data' => $task->load(['creator', 'assignee', 'team']),
            'message' => 'Task completed',
        ]);
    }

    public function reopen(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $this->authorizeTaskAction($request, $task);
        abort_unless($task->status === 'DONE', 409, 'This task is already active. Refresh to see the latest status.');
        $task->update([
            'status' => 'IN PROGRESS',
            'completed_at' => null,
            'completion_note' => null,
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'reopened',
        ]);

        return response()->json([
            'data' => $task->load(['creator', 'assignee', 'team']),
            'message' => 'Task reopened',
        ]);
    }

    public function uploadAttachment(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $this->authorizeTaskAction($request, $task);

        $request->validate([
            // 20MB max. Executable, HTML and SVG uploads are deliberately not allowed.
            'file' => 'required|file|max:20480|mimes:pdf,png,jpg,jpeg,gif,webp,mp4,mov,webm,mp3,wav,txt,csv,md,doc,docx,xls,xlsx,ppt,pptx,zip',
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('attachments', TaskAttachment::DISK);

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'uploaded_by' => $request->user()->id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'attachment_added',
            'metadata' => ['file_name' => $fileName],
        ]);

        return response()->json([
            'data' => $attachment->load('uploader'),
            'message' => 'Attachment uploaded',
        ], 201);
    }

    public function deleteAttachment(Request $request, $id)
    {
        $attachment = TaskAttachment::findOrFail($id);
        $task = Task::visibleTo($request->user())->findOrFail($attachment->task_id);
        if ($attachment->uploaded_by !== $request->user()->id) {
            $this->authorizeManagement($request, $task);
        }

        $path = (string) $attachment->file_path;
        if (str_starts_with($path, 'attachments/')) {
            Storage::disk(TaskAttachment::DISK)->delete($path);
        } else {
            Storage::disk('public')->delete(ltrim((string) preg_replace('#^.*?/storage/#', '', $path), '/'));
        }
        $attachment->delete();
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'attachment_removed',
            'metadata' => ['file_name' => $attachment->file_name],
        ]);

        return response()->json(['message' => 'Attachment removed']);
    }

    public function addComment(Request $request, $id)
    {
        $task = Task::visibleTo($request->user())->findOrFail($id);
        $validated = $request->validate(['body' => 'required|string|max:2000']);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        // Tell everyone involved except the author.
        $recipients = $task->assignees->pluck('id')->merge([$task->assigned_to, $task->created_by])->filter()->unique()->reject(fn ($userId) => $userId === $request->user()->id);
        foreach ($recipients as $userId) {
            AppNotification::create([
                'user_id' => $userId,
                'task_id' => $task->id,
                'title' => '💬 New comment',
                'message' => "{$request->user()->name} commented on \"{$task->title}\": ".Str::limit($comment->body, 120),
            ]);
        }

        return response()->json(['data' => $comment->load('user'), 'message' => 'Comment added'], 201);
    }

    /** Reached only through a signed, expiring URL issued to someone who could already see the task. */
    public function downloadAttachment(TaskAttachment $attachment)
    {
        abort_unless(str_starts_with((string) $attachment->file_path, 'attachments/') && Storage::disk(TaskAttachment::DISK)->exists($attachment->file_path), 404);

        return Storage::disk(TaskAttachment::DISK)->download($attachment->file_path, $attachment->file_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeTaskAction(Request $request, Task $task): void
    {
        $user = $request->user();
        if ($user->isCeo()) return;
        if ($user->isTeamLead() && ($task->created_by === $user->id || $user->teams()->where('teams.id', $task->team_id)->exists())) return;
        $ownsTask = $task->assigned_to === $user->id
            || $task->assignees()->where('users.id', $user->id)->exists();
        $ownsTeamTask = !$task->assigned_to && !$task->assignees()->exists()
            && $user->teams()->where('teams.id', $task->team_id)->exists();
        abort_unless($ownsTask || $ownsTeamTask, 403, 'You can update tasks assigned to you or your whole team.');
    }

    private function authorizeManagement(Request $request, Task $task): void
    {
        $user = $request->user();
        abort_unless($user->isCeo() || ($user->isTeamLead() && ($task->created_by === $user->id || $user->teams()->where('teams.id', $task->team_id)->exists())), 403, 'You can manage tasks in your own teams.');
    }

    private function authorizeAssignment(Request $request, array $values): void
    {
        WorkspaceAccess::assertAssignment($request->user(), $values);
    }
}
