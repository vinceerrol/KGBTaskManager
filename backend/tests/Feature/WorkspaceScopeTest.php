<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_reads_and_dashboard_are_scoped_to_teams_and_assignments(): void
    {
        $ceo = User::factory()->create(['role' => 'ceo']);
        $member = User::factory()->create(['role' => 'employee']);
        $ownTeam = Team::create(['name' => 'Design']);
        $otherTeam = Team::create(['name' => 'Marketing']);
        $ownTeam->members()->attach($member);
        $ownTask = Task::create(['title' => 'Shared work', 'created_by' => $ceo->id, 'team_id' => $ownTeam->id, 'status' => 'IN PROGRESS', 'priority' => 'normal']);
        $foreign = Task::create(['title' => 'Other team', 'created_by' => $ceo->id, 'team_id' => $otherTeam->id, 'status' => 'IN PROGRESS', 'priority' => 'normal']);
        Sanctum::actingAs($member);
        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownTask->id);
        $this->getJson("/api/tasks/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('in_progress', 1);
        $this->getJson('/api/teams')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/teams/{$otherTeam->id}")->assertForbidden();
        Sanctum::actingAs($ceo);
        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('in_progress', 2);
    }

    public function test_leads_cannot_assign_or_manage_work_outside_their_teams(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $outsider = User::factory()->create(['role' => 'employee']);
        $ownTeam = Team::create(['name' => 'Design']);
        $otherTeam = Team::create(['name' => 'Marketing']);
        $ownTeam->members()->attach($lead);
        $otherTeam->members()->attach($outsider);
        $foreign = Task::create(['title' => 'External work', 'created_by' => $outsider->id, 'team_id' => $otherTeam->id, 'status' => 'IN PROGRESS', 'priority' => 'normal']);
        $schedule = RecurringTask::create(['title' => 'External schedule', 'created_by' => $outsider->id, 'team_id' => $otherTeam->id, 'priority' => 'normal', 'frequency' => 'daily', 'scheduled_time' => '09:00', 'is_active' => true]);
        Sanctum::actingAs($lead);
        $this->getJson('/api/users')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/tasks/{$foreign->id}", ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/tasks/{$foreign->id}")->assertForbidden();
        $this->postJson('/api/tasks', ['title' => 'Wrong team', 'team_id' => $otherTeam->id, 'priority' => 'normal', 'start_type' => 'now'])->assertForbidden();
        $this->postJson('/api/tasks', ['title' => 'Wrong owner', 'team_id' => $ownTeam->id, 'assignee_ids' => [$outsider->id], 'priority' => 'normal', 'start_type' => 'now'])->assertForbidden();
        $this->postJson('/api/templates', ['title' => 'Wrong team', 'team_id' => $otherTeam->id, 'priority' => 'normal'])->assertForbidden();
        $this->postJson('/api/recurring-tasks', ['title' => 'Wrong team', 'team_id' => $otherTeam->id, 'priority' => 'normal', 'frequency' => 'daily', 'scheduled_time' => '09:00'])->assertForbidden();
        $this->postJson("/api/recurring-tasks/{$schedule->id}/toggle")->assertForbidden();
        $this->getJson('/api/recurring-tasks')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/tasks', ['title' => 'Own work', 'team_id' => $ownTeam->id, 'priority' => 'normal', 'start_type' => 'now'])->assertCreated();
    }

    public function test_whole_team_assignments_notify_members_and_appear_in_personal_work(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'employee']);
        $team = Team::create(['name' => 'Design']);
        $team->members()->attach([$lead->id, $member->id]);
        Sanctum::actingAs($lead);
        $response = $this->postJson('/api/tasks', ['title' => 'Team review', 'team_id' => $team->id, 'priority' => 'normal', 'start_type' => 'now'])->assertCreated();
        $id = $response->json('data.id');
        $this->assertSame(1, AppNotification::where('user_id', $member->id)->where('task_id', $id)->count());
        Sanctum::actingAs($member);
        $this->getJson('/api/tasks/my-tasks')->assertOk()->assertJsonPath('data.0.id', $id);
    }
}
