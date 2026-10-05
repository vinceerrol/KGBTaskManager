<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\TaskActivity;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-30T19:00:00+08:00'));
        config(['task_brief.ai_enabled' => false, 'task_brief.provider' => 'openai', 'task_brief.api_key' => 'test-only-key', 'task_brief.model' => 'gpt-4.1-mini', 'task_brief.groq_api_key' => 'test-only-groq-key', 'task_brief.groq_model' => 'openai/gpt-oss-120b']);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function manager(): User
    {
        $user = User::factory()->create(['role' => 'ceo']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function interpretation(string $brief, array $mentions = []): array
    {
        return ['brief' => $brief, 'mentions' => $mentions, 'revision' => 3, 'reference_at' => Carbon::now()->toIso8601String()];
    }

    private function mention(string $text, User|Team $model): array
    {
        $prefix = strstr($text, '@'.$model->name, true);
        $start = strlen(mb_convert_encoding($prefix, 'UTF-16LE', 'UTF-8')) / 2;

        return ['kind' => $model instanceof User ? 'person' : 'team', 'id' => $model->id, 'label' => $model->name, 'start' => (int) $start, 'end' => (int) ($start + strlen(mb_convert_encoding('@'.$model->name, 'UTF-16LE', 'UTF-8')) / 2)];
    }

    private function aiResult(array $overrides = []): array
    {
        return array_merge(['title' => 'Create video', 'description' => 'Use the brand guide.', 'team_id' => null, 'assignee_ids' => [], 'ownership' => 'unassigned', 'priority' => 'normal', 'start_type' => 'now', 'scheduled_at' => null, 'deadline' => null, 'date_only' => false, 'issues' => []], $overrides);
    }

    private function provider(array $result): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [
            ['type' => 'reasoning', 'summary' => []],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($result)]]],
        ]])]);
    }

    private function groqProvider(array $result, array $choiceOverrides = []): void
    {
        Http::fake(['api.groq.com/openai/v1/chat/completions' => Http::response(['choices' => [array_merge(['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($result)]], $choiceOverrides)]])]);
    }

    private function creation(array $overrides = []): array
    {
        return array_merge(['title' => 'Create video', 'description' => 'Use the brand guide.', 'priority' => 'normal', 'start_type' => 'now', 'team_id' => null, 'assigned_to' => null, 'assignee_ids' => [], 'scheduled_at' => null, 'deadline' => '2026-10-02T17:00:00+08:00', 'creation_method' => 'brief', 'source_brief' => 'Gumawa ng video, due Friday at 5 PM.', 'creation_key' => (string) Str::uuid(), 'assignment_scope' => 'unassigned'], $overrides);
    }

    public function test_context_is_scoped_and_contains_no_emails_or_unrelated_team_names(): void
    {
        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'employee']);
        $outsider = User::factory()->create(['role' => 'employee']);
        $own = Team::create(['name' => 'Design']);
        $other = Team::create(['name' => 'Private team']);
        $own->members()->attach([$lead->id, $member->id]);
        $other->members()->attach([$member->id, $outsider->id]);
        Sanctum::actingAs($lead);
        $response = $this->getJson('/api/task-drafts/context')->assertOk()->assertJsonCount(1, 'data.teams')->assertJsonCount(2, 'data.people')->assertJsonPath('data.ai_enabled', false)->assertJsonPath('data.timezone', 'Asia/Manila');
        $this->assertStringNotContainsString('email', $response->getContent());
        $this->assertStringNotContainsString('Private team', $response->getContent());
        Sanctum::actingAs($member);
        $this->getJson('/api/task-drafts/context')->assertForbidden();
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertForbidden();
    }

    public function test_disabled_ai_accepts_utf16_mentions_and_never_calls_a_provider_or_creates_work(): void
    {
        $this->manager();
        $member = User::factory()->create(['name' => 'Carlo']);
        $text = '🎬 Gumawa ng video kay @Carlo.';
        $this->postJson('/api/task-drafts/interpret', $this->interpretation($text, [$this->mention($text, $member)]))->assertOk()->assertJsonPath('status', 'local')->assertJsonPath('revision', 3);
        Http::assertNothingSent();
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_mention_identity_and_permissions_are_checked_before_provider_access(): void
    {
        config(['task_brief.ai_enabled' => true]);
        $lead = User::factory()->create(['role' => 'team_lead']);
        $outsider = User::factory()->create(['name' => 'Carlo']);
        Sanctum::actingAs($lead);
        $text = 'Create a video with @Carlo';
        $this->postJson('/api/task-drafts/interpret', $this->interpretation($text, [$this->mention($text, $outsider)]))->assertForbidden();
        $this->manager();
        $mention = $this->mention($text, $outsider);
        $mention['start']++;
        $this->postJson('/api/task-drafts/interpret', $this->interpretation($text, [$mention]))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_ai_uses_strict_structured_output_and_only_selected_identity_data(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true]);
        $member = User::factory()->create(['name' => 'Carlo']);
        User::factory()->create(['name' => 'Unrelated person']);
        $text = 'Create a video with @Carlo, due bukas at 5 PM.';
        $this->provider($this->aiResult(['assignee_ids' => [$member->id], 'ownership' => 'people', 'deadline' => '2026-10-01T17:00']));
        $this->postJson('/api/task-drafts/interpret', $this->interpretation($text, [$this->mention($text, $member)]))->assertOk()->assertJsonPath('status', 'interpreted')->assertJsonPath('data.assignee_ids.0', $member->id)->assertJsonPath('data.deadline', '2026-10-01T17:00');
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return $request['store'] === false && $request['text']['format']['strict'] === true && $request['text']['format']['type'] === 'json_schema' && count($input['selected_mentions']) === 1 && ! str_contains($request['input'], 'email') && ! str_contains($request['input'], 'Unrelated person');
        });
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_invented_owner_ids_and_malformed_ai_data_fall_back_safely(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true]);
        $this->provider($this->aiResult(['assignee_ids' => [99999], 'ownership' => 'people']));
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertOk()->assertJsonPath('status', 'unavailable')->assertJsonPath('data', null);
        $this->provider($this->aiResult(['priority' => 'invented-priority']));
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertOk()->assertJsonPath('status', 'unavailable');
    }

    public function test_provider_refusal_and_network_failure_do_not_destroy_the_local_draft(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true]);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'refusal', 'refusal' => 'Refused']]]]])]);
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertOk()->assertJsonPath('status', 'unavailable');
        Http::fake(['api.openai.com/v1/responses' => Http::failedConnection()]);
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertOk()->assertJsonPath('status', 'unavailable');
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_ai_date_inconsistencies_require_review_and_old_references_are_rejected(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true]);
        $this->provider($this->aiResult(['start_type' => 'scheduled', 'scheduled_at' => '2026-10-02T10:00', 'deadline' => '2026-10-01T17:00']));
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create video starting Friday.'))->assertOk()->assertJsonPath('data.issues.0.field', 'deadline')->assertJsonPath('data.issues.0.blocking', true);
        $input = $this->interpretation('Create video.');
        $input['reference_at'] = Carbon::now()->subDays(8)->toIso8601String();
        $this->postJson('/api/task-drafts/interpret', $input)->assertUnprocessable();
    }

    public function test_creation_retries_produce_one_task_activity_and_notification(): void
    {
        $this->manager();
        $member = User::factory()->create(['role' => 'employee']);
        $payload = $this->creation(['assignee_ids' => [$member->id], 'assigned_to' => $member->id, 'assignment_scope' => 'people']);
        $first = $this->postJson('/api/tasks', $payload)->assertCreated()->assertJsonPath('data.creation_method', 'brief')->assertJsonPath('data.source_brief', $payload['source_brief']);
        $id = $first->json('data.id');
        Carbon::setTestNow(Carbon::now()->addDays(10));
        $this->postJson('/api/tasks', $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('tasks', 1);
        $this->assertSame(1, TaskActivity::where('task_id', $id)->count());
        $this->assertSame(1, AppNotification::where('task_id', $id)->count());
        $this->assertStringNotContainsString('creation_payload_hash', $first->getContent());
        $this->assertStringNotContainsString('creation_key', $first->getContent());
        $payload['title'] = 'Different task';
        $this->postJson('/api/tasks', $payload)->assertConflict();
    }

    public function test_creation_keys_are_scoped_to_the_actor(): void
    {
        $this->manager();
        $payload = $this->creation();
        $this->postJson('/api/tasks', $payload)->assertCreated();
        $this->manager();
        $this->postJson('/api/tasks', $payload)->assertCreated();
        $this->assertDatabaseCount('tasks', 2);
    }

    public function test_brief_creation_requires_explicit_scope_and_consistent_primary_owner(): void
    {
        $this->manager();
        $member = User::factory()->create(['role' => 'employee']);
        $team = Team::create(['name' => 'Design']);
        $this->postJson('/api/tasks', $this->creation(['team_id' => $team->id]))->assertUnprocessable()->assertJsonValidationErrors('assignment_scope');
        $this->postJson('/api/tasks', $this->creation(['team_id' => $team->id, 'assignment_scope' => 'team', 'assigned_to' => $member->id]))->assertUnprocessable()->assertJsonValidationErrors('assignee_ids');
        $this->postJson('/api/tasks', $this->creation(['assignment_scope' => 'people']))->assertUnprocessable()->assertJsonValidationErrors('assignment_scope');
        $team->members()->attach($member);
        $this->postJson('/api/tasks', $this->creation(['team_id' => $team->id, 'assignment_scope' => 'team']))->assertCreated();
        $this->assertSame(1, $member->notifications()->count());
    }

    public function test_brief_creation_rejects_past_dates_and_deadlines_before_start(): void
    {
        $this->manager();
        $this->postJson('/api/tasks', $this->creation(['deadline' => '2026-09-29T17:00:00+08:00']))->assertUnprocessable()->assertJsonValidationErrors('deadline');
        $this->postJson('/api/tasks', $this->creation(['start_type' => 'scheduled']))->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->postJson('/api/tasks', $this->creation(['start_type' => 'scheduled', 'scheduled_at' => '2026-10-03T09:00:00+08:00']))->assertUnprocessable()->assertJsonValidationErrors('deadline');
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_task_and_assignment_write_roll_back_if_notification_fails(): void
    {
        $this->manager();
        $member = User::factory()->create(['role' => 'employee']);
        AppNotification::creating(fn () => throw new \RuntimeException('Simulated notification failure'));
        try {
            $this->postJson('/api/tasks', $this->creation(['assignee_ids' => [$member->id], 'assigned_to' => $member->id, 'assignment_scope' => 'people']))->assertServerError();
            $this->assertDatabaseCount('tasks', 0);
            $this->assertDatabaseCount('task_user', 0);
            $this->assertDatabaseCount('task_activities', 0);
        } finally {
            AppNotification::flushEventListeners();
        }
    }

    public function test_regular_creation_and_existing_task_edits_keep_their_existing_contract(): void
    {
        $this->manager();
        $response = $this->postJson('/api/tasks', ['title' => 'Normal form task', 'priority' => 'normal', 'start_type' => 'now'])->assertCreated()->assertJsonPath('data.creation_method', 'fields')->assertJsonPath('data.source_brief', null);
        $this->putJson('/api/tasks/'.$response->json('data.id'), ['title' => 'Regular edit'])->assertOk()->assertJsonPath('data.title', 'Regular edit');
    }

    public function test_groq_context_requires_its_own_server_key_and_a_known_provider(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true, 'task_brief.provider' => 'groq', 'task_brief.groq_api_key' => '']);
        $this->getJson('/api/task-drafts/context')->assertOk()->assertJsonPath('data.ai_enabled', false)->assertJsonPath('data.ai_provider', null);
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertJsonPath('status', 'local');
        config(['task_brief.groq_api_key' => 'test-only-groq-key']);
        $this->getJson('/api/task-drafts/context')->assertOk()->assertJsonPath('data.ai_enabled', true)->assertJsonPath('data.ai_provider', 'groq');
        config(['task_brief.provider' => 'unknown-provider']);
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertJsonPath('status', 'local');
        Http::assertNothingSent();
    }

    public function test_groq_uses_strict_chat_schema_and_accepts_the_multiline_schedule_example(): void
    {
        $this->manager();
        Carbon::setTestNow(Carbon::parse('2026-10-01T19:00:00+08:00'));
        config(['task_brief.ai_enabled' => true, 'task_brief.provider' => 'groq']);
        $member = User::factory()->create(['name' => 'Carlo Mendoza']);
        $team = Team::create(['name' => 'AI Video Editors']);
        $text = "@AI Video Editors\n@Carlo Mendoza\ngumawa kayo ngayon ng ai video na highly edited for our FB Meta ADS bukas. start na bukas ng 10:32pm at ang deadline 11pm";
        $this->groqProvider($this->aiResult(['title' => 'Create a highly edited AI video for FB Meta ads', 'assignee_ids' => [$member->id], 'team_id' => $team->id, 'ownership' => 'people', 'start_type' => 'scheduled', 'scheduled_at' => '2026-10-02T22:32', 'deadline' => '2026-10-02T23:00']));
        $this->postJson('/api/task-drafts/interpret', $this->interpretation($text, [$this->mention($text, $member), $this->mention($text, $team)]))->assertOk()->assertJsonPath('status', 'interpreted')->assertJsonPath('data.title', 'Create a highly edited AI video for FB Meta ads')->assertJsonPath('data.priority', 'normal')->assertJsonPath('data.scheduled_at', '2026-10-02T22:32')->assertJsonPath('data.deadline', '2026-10-02T23:00')->assertJsonPath('retry_after', 0);
        Http::assertSent(function ($request) {
            $input = json_decode($request['messages'][1]['content'], true);
            $schema = $request['response_format']['json_schema'];

            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-only-groq-key')
                && $request['model'] === 'openai/gpt-oss-120b' && $request['reasoning_effort'] === 'low' && $request['include_reasoning'] === false
                && $request['response_format']['type'] === 'json_schema' && $schema['strict'] === true && $schema['schema']['additionalProperties'] === false
                && count($input['selected_mentions']) === 2 && $input['timezone'] === 'Asia/Manila'
                && ! str_contains($request['messages'][1]['content'], 'email');
        });
        Http::assertSentCount(1);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_groq_rejects_invented_identities_and_malformed_field_values(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true, 'task_brief.provider' => 'groq']);
        $invalidValues = [['assignee_ids' => [99999], 'ownership' => 'people'], ['team_id' => 99999, 'ownership' => 'team'], ['priority' => 'super-high'], ['deadline' => 'Friday night']];
        $responses = Http::fakeSequence('api.groq.com/openai/v1/chat/completions');
        foreach ($invalidValues as $invalid) {
            $responses->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($this->aiResult($invalid))]]]]);
        }
        foreach ($invalidValues as $invalid) {
            $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertOk()->assertJsonPath('status', 'unavailable')->assertJsonPath('data', null);
        }
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_groq_incomplete_refused_and_network_responses_preserve_local_interpretation(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true, 'task_brief.provider' => 'groq']);
        $responses = Http::fakeSequence('api.groq.com/openai/v1/chat/completions');
        foreach ([['finish_reason' => 'length'], ['message' => ['content' => '{}', 'refusal' => 'Refused']], ['message' => ['content' => '<think>not JSON</think>']]] as $invalid) {
            $responses->push(['choices' => [array_merge(['finish_reason' => 'stop', 'message' => ['content' => json_encode($this->aiResult())]], $invalid)]]);
        }
        $responses->pushFailedConnection();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertOk()->assertJsonPath('status', 'unavailable')->assertJsonPath('data', null);
        }
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_groq_rate_limit_cooldown_prevents_repeated_calls_and_expires(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true, 'task_brief.provider' => 'groq']);
        Http::fakeSequence('api.groq.com/openai/v1/chat/completions')->push(['error' => ['message' => 'Limit reached']], 429, ['Retry-After' => '30'])->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($this->aiResult())]]]]);
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertOk()->assertJsonPath('status', 'unavailable')->assertJsonPath('retry_after', 30);
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a different video.'))->assertOk()->assertJsonPath('retry_after', 30);
        Http::assertSentCount(1);
        Carbon::setTestNow(Carbon::now()->addSeconds(31));
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create a video.'))->assertOk()->assertJsonPath('status', 'interpreted')->assertJsonPath('retry_after', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_groq_timing_conflicts_still_require_preview_review(): void
    {
        $this->manager();
        config(['task_brief.ai_enabled' => true, 'task_brief.provider' => 'groq']);
        $this->groqProvider($this->aiResult(['start_type' => 'scheduled', 'scheduled_at' => '2026-10-01T22:32', 'deadline' => '2026-10-01T22:00']));
        $this->postJson('/api/task-drafts/interpret', $this->interpretation('Create video. Start bukas 10:32 PM, deadline 10 PM.'))->assertOk()->assertJsonPath('status', 'interpreted')->assertJsonPath('data.issues.0.field', 'deadline')->assertJsonPath('data.issues.0.blocking', true);
    }
}
