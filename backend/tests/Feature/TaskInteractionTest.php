<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function task(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'title' => 'Review handoff', 'created_by' => User::factory()->create(['role' => 'ceo'])->id,
            'status' => 'SCHEDULED', 'priority' => 'normal',
        ], $overrides));
    }

    public function test_an_owner_can_start_complete_and_undo_without_duplicate_activity(): void
    {
        $member = User::factory()->create(['role' => 'employee']);
        $task = $this->task();
        $task->assignees()->attach($member);
        Sanctum::actingAs($member);
        $this->postJson("/api/tasks/{$task->id}/start")->assertOk()->assertJsonPath('data.status', 'IN PROGRESS');
        $this->postJson("/api/tasks/{$task->id}/start")->assertConflict();
        $this->postJson("/api/tasks/{$task->id}/complete", ['completion_note' => 'Ready to review'])->assertOk();
        $this->postJson("/api/tasks/{$task->id}/complete")->assertConflict();
        $this->postJson("/api/tasks/{$task->id}/reopen")->assertOk()->assertJsonPath('data.completion_note', null);
        $this->postJson("/api/tasks/{$task->id}/reopen")->assertConflict();
        $this->assertSame(3, $task->activities()->count());
        $this->assertSame(1, $task->fresh()->creator->notifications()->where('task_id', $task->id)->count());
    }

    public function test_members_cannot_change_unrelated_tasks_or_management_fields(): void
    {
        $task = $this->task();
        Sanctum::actingAs(User::factory()->create(['role' => 'employee']));
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();
        $this->postJson("/api/tasks/{$task->id}/start")->assertForbidden();
        $this->postJson("/api/tasks/{$task->id}/complete")->assertForbidden();
        $this->postJson("/api/tasks/{$task->id}/reopen")->assertForbidden();
        $this->postJson("/api/tasks/{$task->id}/attachments", [])->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Review handoff', 'status' => 'SCHEDULED']);
    }

    public function test_unassigned_team_work_is_actionable_by_team_members(): void
    {
        $team = Team::create(['name' => 'Design']);
        $member = User::factory()->create(['role' => 'employee']);
        $team->members()->attach($member);
        $task = $this->task(['team_id' => $team->id]);
        Sanctum::actingAs($member);
        $this->postJson("/api/tasks/{$task->id}/complete")->assertOk();
        $this->postJson("/api/tasks/{$task->id}/reopen")->assertOk();
    }

    public function test_upload_preserves_the_file_and_activity_and_rejects_oversize_files(): void
    {
        Storage::fake('local');
        $member = User::factory()->create(['role' => 'employee']);
        $task = $this->task(['assigned_to' => $member->id]);
        Sanctum::actingAs($member);
        $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('handoff.txt', 2, 'text/plain'),
        ])->assertCreated()->assertJsonPath('data.file_name', 'handoff.txt')->assertJsonMissingPath('data.file_path');
        $attachment = $task->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertSame(1, $task->activities()->where('action', 'attachment_added')->count());
        $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('too-large.txt', 20481),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_uploads_reject_executable_html_and_svg_files(): void
    {
        Storage::fake('local');
        $member = User::factory()->create(['role' => 'employee']);
        $task = $this->task(['assigned_to' => $member->id]);
        Sanctum::actingAs($member);
        foreach (['shell.php', 'page.html', 'image.svg', 'run.exe'] as $name) {
            $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => UploadedFile::fake()->create($name, 2)])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertSame(0, $task->attachments()->count());
    }

    public function test_attachments_download_only_through_a_valid_signed_link_as_a_download(): void
    {
        Storage::fake('local');
        $member = User::factory()->create(['role' => 'employee']);
        $task = $this->task(['assigned_to' => $member->id]);
        Sanctum::actingAs($member);
        $url = $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('handoff.txt', 2, 'text/plain'),
        ])->assertCreated()->json('data.url');
        $this->assertStringContainsString('signature=', $url);

        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('content-disposition', 'attachment; filename=handoff.txt');
        $attachment = $task->attachments()->firstOrFail();
        $this->get("/api/attachments/{$attachment->id}/download")->assertForbidden();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=forged', $url))->assertForbidden();
    }

    public function test_sign_in_is_rate_limited_per_email(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'email' => 'limit@example.com']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_signing_in_again_keeps_other_devices_signed_in(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'password' => 'secret-pass']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertOk();
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_the_user_list_hides_team_memberships_outside_the_viewers_teams(): void
    {
        $mine = Team::create(['name' => 'Design']);
        $other = Team::create(['name' => 'Finance']);
        $viewer = User::factory()->create(['role' => 'employee']);
        $colleague = User::factory()->create(['role' => 'employee']);
        $mine->members()->attach([$viewer->id, $colleague->id]);
        $other->members()->attach($colleague->id);
        Sanctum::actingAs($viewer);
        $row = collect($this->getJson('/api/users')->assertOk()->json('data'))->firstWhere('id', $colleague->id);
        $this->assertSame(['Design'], collect($row['teams'])->pluck('name')->all());
    }

    public function test_demo_login_is_unavailable_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->postJson('/api/auth/demo-login', ['role' => 'ceo'])->assertNotFound();
    }

    public function test_reassignment_updates_both_owner_relations_and_notifies_only_new_owners(): void
    {
        $oldOwner = User::factory()->create(['role' => 'employee']);
        $newOwner = User::factory()->create(['role' => 'employee']);
        $task = $this->task(['assigned_to' => $oldOwner->id]);
        $task->assignees()->attach($oldOwner);
        Sanctum::actingAs($task->creator);
        $payload = ['assigned_to' => $newOwner->id, 'assignee_ids' => [$newOwner->id]];
        $this->putJson("/api/tasks/{$task->id}", $payload)->assertOk()->assertJsonPath('data.assigned_to', $newOwner->id);
        $this->putJson("/api/tasks/{$task->id}", $payload)->assertOk();
        $this->assertFalse($task->fresh()->assignees->contains($oldOwner->id));
        $this->assertSame(1, $newOwner->notifications()->where('task_id', $task->id)->count());
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'action' => 'updated']);
    }

    public function test_offset_dates_round_trip_and_scheduled_work_starts_at_the_correct_instant(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30T19:00:00+08:00'));
        Sanctum::actingAs(User::factory()->create(['role' => 'ceo']));
        $response = $this->postJson('/api/tasks', [
            'title' => 'Morning review', 'priority' => 'normal', 'start_type' => 'scheduled',
            'scheduled_at' => '2026-10-01T09:00:00+08:00', 'deadline' => '2026-10-01T11:00:00+08:00',
        ])->assertCreated()->assertJsonPath('data.scheduled_at', '2026-10-01T01:00:00.000000Z');
        $id = $response->json('data.id');
        $this->assertDatabaseHas('tasks', ['id' => $id, 'scheduled_at' => '2026-10-01 01:00:00', 'deadline' => '2026-10-01 03:00:00']);
        Carbon::setTestNow(Carbon::parse('2026-10-01T08:59:00+08:00'));
        $this->artisan('tasks:check-scheduled')->assertSuccessful();
        $this->assertSame('SCHEDULED', Task::findOrFail($id)->status);
        Carbon::setTestNow(Carbon::parse('2026-10-01T09:00:00+08:00'));
        $this->artisan('tasks:check-scheduled')->assertSuccessful();
        $this->assertSame('IN PROGRESS', Task::findOrFail($id)->status);
        $this->assertSame('2026-10-01 01:00', Task::findOrFail($id)->started_at->format('Y-m-d H:i'));
    }

    public function test_today_filter_and_dashboard_use_the_workspace_day_across_utc_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30T20:00:00+08:00'));
        Sanctum::actingAs(User::factory()->create(['role' => 'ceo']));
        $today = $this->task(['deadline' => '2026-09-30T00:30:00+08:00']);
        $tomorrow = $this->task(['deadline' => '2026-10-01T00:00:00+08:00']);
        foreach ([$today, $tomorrow] as $task) $task->forceFill(['created_at' => '2026-09-20 00:00:00'])->save();
        $this->getJson('/api/tasks?date_filter=today')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $today->id);
        $this->getJson('/api/tasks?date_filter=tomorrow')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tomorrow->id);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('tasks_today', 1);
    }
}
