<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role = 'employee', array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $attributes));
    }

    private function task(User $creator, array $overrides = []): Task
    {
        return Task::create(array_merge(['title' => 'Handoff', 'created_by' => $creator->id, 'status' => 'IN PROGRESS', 'priority' => 'normal'], $overrides));
    }

    public function test_only_the_administrator_manages_accounts(): void
    {
        $payload = ['name' => 'New Person', 'email' => 'new@example.com', 'role' => 'employee', 'password' => 'a-long-password'];
        Sanctum::actingAs($this->user('team_lead'));
        $this->postJson('/api/users', $payload)->assertForbidden();
        $this->putJson('/api/users/'.$this->user()->id, ['role' => 'ceo'])->assertForbidden();

        Sanctum::actingAs($this->user('ceo'));
        $team = Team::create(['name' => 'Design']);
        $this->postJson('/api/users', $payload + ['team_ids' => [$team->id]])->assertCreated();
        $this->postJson('/api/users', $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
        $created = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($team->members()->where('users.id', $created->id)->exists());
        $this->postJson('/api/auth/login', ['email' => 'new@example.com', 'password' => 'a-long-password'])->assertOk();
    }

    public function test_a_short_password_is_rejected_for_new_accounts(): void
    {
        Sanctum::actingAs($this->user('ceo'));
        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'employee', 'password' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_deactivating_an_account_blocks_sign_in_and_revokes_tokens(): void
    {
        $admin = $this->user('ceo');
        $person = $this->user('employee', ['password' => 'a-long-password']);
        $person->createToken('device');
        Sanctum::actingAs($admin);
        $this->putJson('/api/users/'.$person->id, ['is_active' => false])->assertOk();
        $this->assertSame(0, $person->tokens()->count());
        $this->postJson('/api/auth/login', ['email' => $person->email, 'password' => 'a-long-password'])->assertUnprocessable();
        $this->putJson('/api/users/'.$person->id, ['is_active' => true, 'role' => 'team_lead'])->assertOk()->assertJsonPath('data.role', 'team_lead');
        $this->postJson('/api/auth/login', ['email' => $person->email, 'password' => 'a-long-password'])->assertOk();
    }

    public function test_the_last_administrator_cannot_be_removed_or_removed_by_themselves(): void
    {
        $admin = $this->user('ceo');
        Sanctum::actingAs($admin);
        $this->putJson('/api/users/'.$admin->id, ['role' => 'employee'])->assertUnprocessable();
        $this->putJson('/api/users/'.$admin->id, ['is_active' => false])->assertUnprocessable();
        $this->assertSame('ceo', $admin->fresh()->role);
    }

    public function test_changing_password_signs_out_other_devices_only(): void
    {
        $user = $this->user('employee', ['password' => 'old-password-123']);
        $current = $user->createToken('current')->plainTextToken;
        $user->createToken('other');
        $this->withToken($current)->postJson('/api/auth/change-password', ['current_password' => 'wrong', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->withToken($current)->postJson('/api/auth/change-password', ['current_password' => 'old-password-123', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertOk();
        $this->assertSame(1, $user->tokens()->count());
        $this->assertTrue(Hash::check('brand-new-pass-1', $user->fresh()->password));
    }

    public function test_teams_can_be_renamed_and_deleted_only_when_they_have_no_open_work(): void
    {
        $admin = $this->user('ceo');
        $member = $this->user();
        $team = Team::create(['name' => 'Design']);
        Sanctum::actingAs($member);
        $this->putJson('/api/teams/'.$team->id, ['name' => 'Hijack'])->assertForbidden();
        $this->deleteJson('/api/teams/'.$team->id)->assertForbidden();

        Sanctum::actingAs($admin);
        $this->putJson('/api/teams/'.$team->id, ['name' => 'Creative', 'member_ids' => [$member->id]])->assertOk()->assertJsonPath('data.name', 'Creative');
        $this->assertTrue($team->members()->where('users.id', $member->id)->exists());
        $open = $this->task($admin, ['team_id' => $team->id]);
        $this->deleteJson('/api/teams/'.$team->id)->assertConflict();
        $open->update(['status' => 'DONE']);
        $this->deleteJson('/api/teams/'.$team->id)->assertOk();
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_templates_and_recurring_workflows_can_be_edited_and_deleted_by_their_managers_only(): void
    {
        $lead = $this->user('team_lead');
        $otherLead = $this->user('team_lead');
        $template = TaskTemplate::create(['title' => 'Weekly review', 'priority' => 'normal', 'created_by' => $lead->id]);
        $recurring = RecurringTask::create(['title' => 'Standup', 'priority' => 'normal', 'frequency' => 'daily', 'scheduled_time' => '09:00', 'is_active' => true, 'created_by' => $lead->id]);

        Sanctum::actingAs($this->user('employee'));
        $this->putJson('/api/templates/'.$template->id, ['title' => 'x'])->assertForbidden();
        $this->deleteJson('/api/recurring-tasks/'.$recurring->id)->assertForbidden();
        Sanctum::actingAs($otherLead);
        $this->putJson('/api/templates/'.$template->id, ['title' => 'x'])->assertForbidden();
        $this->deleteJson('/api/recurring-tasks/'.$recurring->id)->assertForbidden();

        Sanctum::actingAs($lead);
        $this->putJson('/api/templates/'.$template->id, ['title' => 'Monthly review', 'priority' => 'high'])->assertOk()->assertJsonPath('data.title', 'Monthly review');
        $this->putJson('/api/recurring-tasks/'.$recurring->id, ['frequency' => 'weekly', 'scheduled_time' => '10:30'])->assertOk()->assertJsonPath('data.frequency', 'weekly');
        $this->assertNotNull($recurring->fresh()->next_run_at);
        $this->deleteJson('/api/templates/'.$template->id)->assertOk();
        $this->deleteJson('/api/recurring-tasks/'.$recurring->id)->assertOk();
        $this->assertDatabaseCount('task_templates', 0);
        $this->assertDatabaseCount('recurring_tasks', 0);
    }

    public function test_people_on_a_task_can_comment_and_others_are_notified(): void
    {
        $creator = $this->user('ceo');
        $assignee = $this->user();
        $outsider = $this->user();
        $task = $this->task($creator, ['assigned_to' => $assignee->id]);
        $task->assignees()->attach($assignee);

        Sanctum::actingAs($outsider);
        $this->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Let me in'])->assertNotFound();

        Sanctum::actingAs($assignee);
        $this->postJson("/api/tasks/{$task->id}/comments", ['body' => ''])->assertUnprocessable();
        $this->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Need the brand guide'])->assertCreated()->assertJsonPath('data.user.id', $assignee->id);
        $this->assertSame(1, $creator->notifications()->where('task_id', $task->id)->count());
        $this->assertSame(0, $assignee->notifications()->where('task_id', $task->id)->count());

        $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('data.comments.0.body', 'Need the brand guide');
    }

    public function test_attachments_can_be_removed_by_the_uploader_or_a_manager_but_not_other_members(): void
    {
        Storage::fake('local');
        $creator = $this->user('ceo');
        $uploader = $this->user();
        $teammate = $this->user();
        $team = Team::create(['name' => 'Design']);
        $team->members()->attach([$uploader->id, $teammate->id]);
        $task = $this->task($creator, ['team_id' => $team->id]);

        Sanctum::actingAs($uploader);
        $id = $this->postJson("/api/tasks/{$task->id}/attachments", ['file' => UploadedFile::fake()->create('a.txt', 2, 'text/plain')])->assertCreated()->json('data.id');
        $path = $task->attachments()->firstOrFail()->file_path;

        Sanctum::actingAs($teammate);
        $this->deleteJson("/api/attachments/{$id}")->assertForbidden();
        Storage::disk('local')->assertExists($path);

        Sanctum::actingAs($uploader);
        $this->deleteJson("/api/attachments/{$id}")->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('task_attachments', ['id' => $id]);
    }

    public function test_overdue_tasks_alert_owners_and_the_creator_once(): void
    {
        $creator = $this->user('ceo');
        $owner = $this->user();
        $task = $this->task($creator, ['assigned_to' => $owner->id, 'deadline' => Carbon::now()->subHour()]);
        $task->assignees()->attach($owner);

        $this->artisan('tasks:check-scheduled')->assertSuccessful();
        $this->artisan('tasks:check-scheduled')->assertSuccessful();

        foreach ([$owner, $creator] as $person) {
            $this->assertSame(1, AppNotification::where('user_id', $person->id)->where('task_id', $task->id)->where('title', '🚨 Task Overdue')->count());
        }

        $task->update(['status' => 'DONE']);
        $other = $this->task($creator, ['assigned_to' => $owner->id, 'status' => 'DONE', 'deadline' => Carbon::now()->subHour()]);
        $this->artisan('tasks:check-scheduled')->assertSuccessful();
        $this->assertSame(0, AppNotification::where('task_id', $other->id)->count());
    }

    public function test_the_inbox_reports_the_true_unread_count_beyond_the_page_limit(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 35; $i++) {
            AppNotification::create(['user_id' => $user->id, 'title' => 'n'.$i, 'message' => 'm']);
        }
        Sanctum::actingAs($user);
        $response = $this->getJson('/api/notifications')->assertOk();
        $this->assertCount(30, $response->json('data'));
        $response->assertJsonPath('unread_count', 35);
    }

    public function test_the_task_list_pages_only_when_asked_and_otherwise_returns_everything(): void
    {
        $admin = $this->user('ceo');
        for ($i = 1; $i <= 5; $i++) {
            $this->task($admin, ['title' => "Task {$i}"]);
        }
        Sanctum::actingAs($admin);

        $full = $this->getJson('/api/tasks')->assertOk();
        $this->assertCount(5, $full->json('data'));
        $full->assertJsonMissingPath('meta');

        $page = $this->getJson('/api/tasks?per_page=2&page=3')->assertOk();
        $this->assertCount(1, $page->json('data'));
        $page->assertJsonPath('meta.total', 5)->assertJsonPath('meta.last_page', 3)->assertJsonPath('meta.current_page', 3);

        $this->assertCount(5, $this->getJson('/api/tasks?per_page=1000')->json('data'));
    }

    public function test_clearing_the_scheduled_start_starts_the_task_instead_of_stranding_it(): void
    {
        $admin = $this->user('ceo');
        $task = $this->task($admin, ['status' => 'SCHEDULED', 'scheduled_at' => Carbon::now()->addDay()]);
        Sanctum::actingAs($admin);
        $this->putJson("/api/tasks/{$task->id}", ['scheduled_at' => null])->assertOk()->assertJsonPath('data.status', 'IN PROGRESS');
        $this->assertNotNull($task->fresh()->started_at);
    }
}
