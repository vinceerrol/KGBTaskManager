<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'team_lead']);
    }

    public function test_a_schedule_has_a_future_next_run_and_pause_resume_skips_missed_runs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 08:00:00', 'Asia/Manila'));
        Sanctum::actingAs($this->manager());
        $response = $this->postJson('/api/recurring-tasks', [
            'title' => 'Daily handoff', 'priority' => 'normal',
            'frequency' => 'daily', 'scheduled_time' => '09:00',
        ])->assertCreated();
        $id = $response->json('data.id');
        $this->assertSame('2026-09-30 09:00', RecurringTask::findOrFail($id)->next_run_at->timezone('Asia/Manila')->format('Y-m-d H:i'));
        $this->postJson("/api/recurring-tasks/{$id}/toggle")->assertOk()->assertJsonPath('data.is_active', false);
        Carbon::setTestNow(Carbon::parse('2026-10-02 10:00:00', 'Asia/Manila'));
        $this->postJson("/api/recurring-tasks/{$id}/toggle")->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertSame('2026-10-03 09:00', RecurringTask::findOrFail($id)->next_run_at->timezone('Asia/Manila')->format('Y-m-d H:i'));
    }

    public function test_a_due_schedule_generates_one_task_with_owner_activity_and_notification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 09:01:00', 'Asia/Manila'));
        $manager = $this->manager();
        $member = User::factory()->create(['role' => 'employee']);
        $schedule = RecurringTask::create([
            'title' => 'Daily check', 'description' => 'Check the queue',
            'created_by' => $manager->id, 'assigned_to' => $member->id,
            'priority' => 'high', 'frequency' => 'daily', 'scheduled_time' => '09:00',
            'is_active' => true, 'next_run_at' => Carbon::now()->subMinute(),
        ]);
        $this->artisan('tasks:check-scheduled')->assertSuccessful();
        $this->artisan('tasks:check-scheduled')->assertSuccessful();
        $this->assertSame(1, Task::count());
        $task = Task::firstOrFail();
        $this->assertSame('IN PROGRESS', $task->status);
        $this->assertSame($member->id, $task->assigned_to);
        $this->assertTrue($task->assignees->contains($member->id));
        $this->assertSame(1, TaskActivity::where('task_id', $task->id)->count());
        $this->assertSame(1, AppNotification::where('task_id', $task->id)->where('user_id', $member->id)->count());
        $this->assertTrue($schedule->fresh()->next_run_at->isFuture());
    }

    public function test_paused_or_uninitialized_schedules_do_not_generate_backlogs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 12:00:00', 'Asia/Manila'));
        $manager = $this->manager();
        foreach ([false, true] as $active) {
            RecurringTask::create([
                'title' => 'Legacy schedule', 'created_by' => $manager->id,
                'priority' => 'normal', 'frequency' => 'daily', 'scheduled_time' => '09:00',
                'is_active' => $active, 'next_run_at' => $active ? null : Carbon::now()->subDays(3),
            ]);
        }
        $this->artisan('tasks:check-scheduled')->assertSuccessful();
        $this->assertSame(0, Task::count());
        $this->assertTrue(RecurringTask::where('is_active', true)->firstOrFail()->next_run_at->isFuture());
    }

    public function test_monthly_schedules_keep_their_original_day_after_short_months(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-31 08:00:00', 'Asia/Manila'));
        $schedule = RecurringTask::create([
            'title' => 'Month end', 'created_by' => $this->manager()->id,
            'priority' => 'normal', 'frequency' => 'monthly', 'scheduled_time' => '09:00', 'is_active' => true,
        ]);
        $february = $schedule->nextOccurrence(Carbon::parse('2026-01-31 09:01:00', 'Asia/Manila'));
        $this->assertSame('2026-02-28 09:00', $february->format('Y-m-d H:i'));
        $march = $schedule->nextOccurrence($february);
        $this->assertSame('2026-03-31 09:00', $march->format('Y-m-d H:i'));
    }

    public function test_weekly_schedules_keep_the_creation_weekday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 08:00:00', 'Asia/Manila'));
        $schedule = RecurringTask::create([
            'title' => 'Weekly review', 'created_by' => $this->manager()->id,
            'priority' => 'normal', 'frequency' => 'weekly', 'scheduled_time' => '09:00', 'is_active' => true,
        ]);
        $next = $schedule->nextOccurrence(Carbon::parse('2026-10-01 08:00:00', 'Asia/Manila'));
        $this->assertSame('2026-10-07 09:00', $next->format('Y-m-d H:i'));
    }

    public function test_invalid_times_and_employee_workflow_mutations_are_rejected(): void
    {
        Sanctum::actingAs($this->manager());
        $this->postJson('/api/recurring-tasks', [
            'title' => 'Invalid time', 'priority' => 'normal',
            'frequency' => 'daily', 'scheduled_time' => '99:99',
        ])->assertUnprocessable()->assertJsonValidationErrors('scheduled_time');
        Sanctum::actingAs(User::factory()->create(['role' => 'employee']));
        $this->postJson('/api/recurring-tasks', [])->assertForbidden();
        $this->postJson('/api/templates', [])->assertForbidden();
        $this->postJson('/api/recurring-tasks/1/toggle')->assertForbidden();
    }
}
