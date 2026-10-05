<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskTemplate;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Users
        $password = Hash::make('password');

        // CEO
        $ceo = User::create([
            'name' => 'Sophia Cruz (CEO)',
            'email' => 'ceo@kcg.com',
            'password' => $password,
            'role' => 'ceo',
        ]);

        // Team Leads
        $leadAnna = User::create([
            'name' => 'Anna Santos',
            'email' => 'anna@kcg.com',
            'password' => $password,
            'role' => 'team_lead',
        ]);

        $leadBen = User::create([
            'name' => 'Ben Reyes',
            'email' => 'ben@kcg.com',
            'password' => $password,
            'role' => 'team_lead',
        ]);

        // Employees
        $empCarlo = User::create([
            'name' => 'Carlo Mendoza',
            'email' => 'carlo@kcg.com',
            'password' => $password,
            'role' => 'employee',
        ]);

        $empMark = User::create([
            'name' => 'Mark Dela Cruz',
            'email' => 'mark@kcg.com',
            'password' => $password,
            'role' => 'employee',
        ]);

        $empJohn = User::create([
            'name' => 'John Ramos',
            'email' => 'john@kcg.com',
            'password' => $password,
            'role' => 'employee',
        ]);

        $empKyle = User::create([
            'name' => 'Kyle Garcia',
            'email' => 'kyle@kcg.com',
            'password' => $password,
            'role' => 'employee',
        ]);

        $empSarah = User::create([
            'name' => 'Sarah Lopez',
            'email' => 'sarah@kcg.com',
            'password' => $password,
            'role' => 'employee',
        ]);

        // 2. Create Teams
        $teamVideo = Team::create([
            'name' => 'AI Video Editors',
            'description' => 'Prompting, generation, scriptwriting, and editing of video creatives.',
        ]);

        $teamMarketing = Team::create([
            'name' => 'Platform Marketing',
            'description' => 'Meta, TikTok, and Google ad creatives, campaign operations.',
        ]);

        $teamAutomation = Team::create([
            'name' => 'Automation',
            'description' => 'Workflows, webhooks, CRM sync, and internal scripts.',
        ]);

        $teamDev = Team::create([
            'name' => 'Development',
            'description' => 'Internal portals, APIs, landing page tech stack, and hosting.',
        ]);

        $teamDesign = Team::create([
            'name' => 'Design & Graphics',
            'description' => 'Brand assets, thumbnails, product packages, and banners.',
        ]);

        // 3. Attach Team Members
        $teamVideo->members()->attach([$leadAnna->id, $empCarlo->id, $empMark->id, $empJohn->id]);
        $teamMarketing->members()->attach([$leadAnna->id, $empKyle->id]);
        $teamAutomation->members()->attach([$leadBen->id, $empCarlo->id]);
        $teamDev->members()->attach([$leadBen->id, $empJohn->id]);
        $teamDesign->members()->attach([$empSarah->id]);

        // 4. Create Tasks
        $now = Carbon::now('Asia/Manila');

        // Task 1: Urgent In-Progress Task (Assigned to both Carlo and Mark)
        $task1 = Task::create([
            'title' => 'Create 3 product videos for herbal supplement campaign',
            'description' => 'Produce 3 TikTok-format video variations focusing on the morning energy hook. Use CapCut and ElevenLabs voiceover.',
            'created_by' => $ceo->id,
            'assigned_to' => $empCarlo->id,
            'team_id' => $teamVideo->id,
            'status' => 'IN PROGRESS',
            'priority' => 'urgent',
            'started_at' => $now->copy()->subHours(2),
            'deadline' => $now->copy()->endOfDay(),
        ]);
        $task1->assignees()->sync([$empCarlo->id, $empMark->id]);

        TaskActivity::create([
            'task_id' => $task1->id,
            'user_id' => $ceo->id,
            'action' => 'created',
            'metadata' => ['note' => 'Created immediate task assigned to Carlo Mendoza and Mark Dela Cruz'],
        ]);
        TaskActivity::create([
            'task_id' => $task1->id,
            'user_id' => $empCarlo->id,
            'action' => 'started',
            'metadata' => ['note' => 'Carlo Mendoza started working on this task'],
        ]);

        // Task 2: Scheduled Task
        $task2 = Task::create([
            'title' => 'Prepare campaign creatives for Friday flash sale',
            'description' => 'Set up ad banners and discount tags for Meta and TikTok.',
            'created_by' => $leadAnna->id,
            'assigned_to' => $empKyle->id,
            'team_id' => $teamMarketing->id,
            'status' => 'SCHEDULED',
            'priority' => 'high',
            'scheduled_at' => $now->copy()->addDay()->setHour(9)->setMinute(0),
            'deadline' => $now->copy()->addDay()->setHour(17)->setMinute(0),
        ]);
        $task2->assignees()->sync([$empKyle->id]);

        TaskActivity::create([
            'task_id' => $task2->id,
            'user_id' => $leadAnna->id,
            'action' => 'created',
            'metadata' => ['note' => 'Scheduled for tomorrow at 9:00 AM'],
        ]);

        // Task 3: Overdue Task
        $task3 = Task::create([
            'title' => 'Fix webhook automation endpoint timeout',
            'description' => 'The order webhook is failing after 30 seconds when sending data to the spreadsheet.',
            'created_by' => $leadBen->id,
            'assigned_to' => $empCarlo->id,
            'team_id' => $teamAutomation->id,
            'status' => 'IN PROGRESS',
            'priority' => 'high',
            'started_at' => $now->copy()->subDays(1),
            'deadline' => $now->copy()->subHours(3),
        ]);
        $task3->assignees()->sync([$empCarlo->id]);

        TaskActivity::create([
            'task_id' => $task3->id,
            'user_id' => $leadBen->id,
            'action' => 'created',
        ]);

        // Task 4: Completed Task
        $task4 = Task::create([
            'title' => 'TikTok competitor research & angle breakdown',
            'description' => 'Analyze top 5 competing accounts in the beauty niche. Record hook formulas.',
            'created_by' => $ceo->id,
            'assigned_to' => $empMark->id,
            'team_id' => $teamVideo->id,
            'status' => 'DONE',
            'priority' => 'normal',
            'started_at' => $now->copy()->subHours(6),
            'completed_at' => $now->copy()->subHours(1),
            'completion_note' => 'Finished breakdown document. Added 12 winning hooks to the team Notion page.',
            'deadline' => $now->copy()->subHours(2),
        ]);
        $task4->assignees()->sync([$empMark->id]);

        TaskActivity::create([
            'task_id' => $task4->id,
            'user_id' => $ceo->id,
            'action' => 'created',
        ]);
        TaskActivity::create([
            'task_id' => $task4->id,
            'user_id' => $empMark->id,
            'action' => 'completed',
            'metadata' => ['completion_note' => 'Finished breakdown document. Added 12 winning hooks.'],
        ]);

        // Task 5: In Progress task for Development
        $task5 = Task::create([
            'title' => 'Optimize database queries for inventory sync',
            'description' => 'Add indexes and reduce n+1 queries during hourly stock pull.',
            'created_by' => $leadBen->id,
            'assigned_to' => $empJohn->id,
            'team_id' => $teamDev->id,
            'status' => 'IN PROGRESS',
            'priority' => 'normal',
            'started_at' => $now->copy()->subHours(3),
            'deadline' => $now->copy()->addHours(4),
        ]);
        $task5->assignees()->sync([$empJohn->id]);

        TaskActivity::create([
            'task_id' => $task5->id,
            'user_id' => $leadBen->id,
            'action' => 'created',
        ]);

        // Task 6: Multi-assignee task for Platform Marketing
        $task6 = Task::create([
            'title' => 'Q4 Holiday sales promotional campaign launch',
            'description' => 'Coordinate promotional calendar, ad budgets, and creative rollouts.',
            'created_by' => $ceo->id,
            'assigned_to' => $leadAnna->id,
            'team_id' => $teamMarketing->id,
            'status' => 'IN PROGRESS',
            'priority' => 'high',
            'started_at' => $now->copy()->subHours(1),
            'deadline' => $now->copy()->addDays(2),
        ]);
        $task6->assignees()->sync([$leadAnna->id, $empKyle->id]);

        TaskActivity::create([
            'task_id' => $task6->id,
            'user_id' => $ceo->id,
            'action' => 'created',
            'metadata' => ['note' => 'Multi-assigned to Anna Santos and Kyle Garcia'],
        ]);

        // 5. Create Templates (Section 24)
        TaskTemplate::create([
            'title' => 'Product Promotional Video (3-Pack)',
            'description' => 'Standard operating procedure for new product launches.',
            'team_id' => $teamVideo->id,
            'priority' => 'high',
            'default_instructions' => "1. Generate script with AI hook formula\n2. Assemble b-roll footage\n3. Render 3 aspect ratios (9:16, 1:1, 16:9)\n4. Upload to shared asset drive",
            'created_by' => $ceo->id,
        ]);

        TaskTemplate::create([
            'title' => 'Weekly Ad Performance Review',
            'description' => 'Pull CPA, ROAS, and creative fatigue metrics.',
            'team_id' => $teamMarketing->id,
            'priority' => 'normal',
            'default_instructions' => "1. Export Meta and TikTok Ad Manager spend data\n2. Highlight top 3 winning ad sets\n3. Flag underperforming creatives",
            'created_by' => $leadAnna->id,
        ]);

        // 6. Create Recurring Tasks (Section 23)
        RecurringTask::create([
            'title' => 'Daily server health & webhook check',
            'description' => 'Verify all third-party syncs and payment gateway logs.',
            'team_id' => $teamAutomation->id,
            'assigned_to' => $empCarlo->id,
            'priority' => 'normal',
            'frequency' => 'daily',
            'scheduled_time' => '09:00',
            'is_active' => true,
            'created_by' => $leadBen->id,
        ]);

        // 7. Create App Notifications (Section 15)
        AppNotification::create([
            'user_id' => $empCarlo->id,
            'title' => '📋 New Urgent Task Assigned',
            'message' => 'Sophia Cruz (CEO) assigned you "Create 3 product videos for herbal supplement campaign". Due today at 11:59 PM.',
            'task_id' => $task1->id,
            'read_at' => null,
            'created_at' => $now->copy()->subHours(2),
        ]);

        AppNotification::create([
            'user_id' => $ceo->id,
            'title' => '✅ Task Completed',
            'message' => 'Mark Dela Cruz marked "TikTok competitor research & angle breakdown" as DONE.',
            'task_id' => $task4->id,
            'read_at' => null,
            'created_at' => $now->copy()->subHours(1),
        ]);

        AppNotification::create([
            'user_id' => $ceo->id,
            'title' => '🔴 Task Overdue',
            'message' => '"Fix webhook automation endpoint timeout" is overdue (Carlo Mendoza).',
            'task_id' => $task3->id,
            'read_at' => null,
            'created_at' => $now->copy()->subHours(3),
        ]);
    }
}
