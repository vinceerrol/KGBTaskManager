# Build: Company Task & Workflow Management System

Build a polished, production-ready internal **Task & Workflow Management System** for a small-to-medium company.

The company currently relies heavily on **Facebook Messenger** for sending tasks. This makes tasks difficult to track, deadlines easy to miss, and completion dependent on employees replying to Messenger.

The goal is NOT to replace Messenger completely.

Instead:

* **Task System = source of truth**
* **Messenger = communication/notification channel**
* **Dashboard = management overview**

The application should be fast, simple, mobile-friendly, and easy enough for a CEO to use without training.

---

# 1. TECH STACK

## Frontend

* Vue 3
* TypeScript
* Vite
* Tailwind CSS
* Pinia
* Vue Router
* Lucide Icons
* Responsive/mobile-first UI

## Backend

* Laravel 12+
* PHP 8.3+
* Laravel Sanctum
* REST API architecture

## Database

* MySQL 8+

## Authentication

* Laravel Sanctum
* Secure session/token authentication
* Role-based access control

## Deployment

Frontend:

* Vercel or company hosting

Backend:

* Hostinger/VPS/shared Laravel-compatible hosting

Database:

* MySQL

The architecture should allow the frontend and backend to be deployed separately.

---

# 2. USER ROLES

Implement three basic roles:

### CEO / Admin

Can:

* Create tasks
* Schedule tasks
* Assign tasks
* Assign tasks to teams
* View all teams
* View all members
* View all tasks
* Edit tasks
* Delete/cancel tasks
* Mark tasks as done
* Reopen tasks
* View task history
* View overdue tasks
* View team workload

### Team Lead

Can:

* View their team
* Create tasks for their team
* Assign tasks to team members
* Monitor task progress
* Review completed tasks
* Reassign tasks

### Employee

Can:

* View assigned tasks
* View team tasks where permitted
* Start tasks
* Mark tasks as done
* Add notes
* Attach files
* View deadlines
* Receive notifications

Keep permissions simple and understandable.

---

# 3. COMPANY ORGANIZATION

The system must support:

## Teams

Examples:

* AI Video Editors
* Platform Marketing
* Automation
* Development
* Customer Service
* Design
* Other teams

Each team contains members.

A user may belong to one or multiple teams.

Example:

AI Video Editors

* Vince
* Anna
* Mark
* John

Automation

* Vince
* Carlo
* Ben

The CEO should be able to select a team first and then easily select the appropriate member.

---

# 4. TASK LIFECYCLE

Keep the task status system intentionally simple.

### CREATED / SCHEDULED

Task exists but has not started yet.

### IN PROGRESS

The task is currently active.

### DONE

The employee has completed the task.

Do NOT create unnecessary statuses such as:

* Pending
* Waiting
* Blocked
* Reviewing
* Archived
* Submitted

unless they become necessary later.

---

# 5. AUTOMATIC TASK START

Support both immediate and scheduled tasks.

### Immediate task

CEO creates:

> Create 3 product videos

The task becomes:

`IN PROGRESS`

immediately.

### Scheduled task

CEO creates:

> Create product videos tomorrow at 9:00 AM

The task initially appears as:

`SCHEDULED`

When the scheduled date/time arrives, automatically change it to:

`IN PROGRESS`

The system should handle timezone consistently using:

`Asia/Manila`

Allow the CEO to create:

* Immediate tasks
* Scheduled tasks
* Tasks with deadlines

Example:

Start:
September 20, 9:00 AM

Deadline:
September 20, 5:00 PM

---

# 6. TASK CREATION SHOULD BE EXTREMELY FAST

This is one of the most important parts of the application.

The CEO should be able to create a task in seconds.

Task creation form:

### Task title

Example:

> Create 3 product videos

### Description

Optional detailed instructions.

### Team

Dropdown/search:

> AI Video Editors

### Assignee

After selecting the team, show only relevant members.

Example:

> Vince
> Anna
> Mark
> John

Also allow:

> Assign to entire team

### Start

Options:

* Start now
* Schedule

### Deadline

Optional date/time.

### Priority

Keep only:

* Normal
* High
* Urgent

### Attachments

Allow files/images.

### Create Task

The interface should minimize unnecessary fields.

---

# 7. SMART TEAM SELECTION

When the CEO selects:

> AI Video Editors

the system should immediately show that team's members.

Example:

AI VIDEO EDITORS

5 members

○ Vince
○ Anna
○ Mark
○ John
○ Kyle

Also show each member's active workload.

Example:

Vince — 2 active tasks
Anna — 5 active tasks
Mark — 1 active task

This helps the CEO choose an appropriate person without manually checking everyone's workload.

Do NOT automatically assign based on workload unless explicitly enabled later.

The system should assist the CEO, not make personnel decisions for her.

---

# 8. CEO DASHBOARD

The dashboard should be extremely clean.

Top-level statistics:

### Tasks Today

### In Progress

### Scheduled

### Overdue

### Completed

Example:

TODAY

📋 18 Tasks
🔵 8 In Progress
⏰ 4 Scheduled
🔴 2 Overdue
✅ 4 Done

---

# 9. TEAM OVERVIEW

Show teams as cards.

Example:

### 🎬 AI VIDEO EDITORS

5 Members

8 Active Tasks

3 Due Today

2 Overdue

Clicking the team opens:

* Members
* Active tasks
* Scheduled tasks
* Completed tasks

---

# 10. MEMBER WORKLOAD

Within a team:

| Member | Active | Due Today | Overdue |
| ------ | -----: | --------: | ------: |
| Vince  |      2 |         1 |       0 |
| Anna   |      5 |         2 |       1 |
| Mark   |      1 |         0 |       0 |

Use this only as visibility.

Do not create complicated productivity scores.

---

# 11. TASK CARD

Every task should clearly display:

* Task title
* Team
* Assignee
* Creator
* Status
* Priority
* Start date/time
* Deadline
* Created date
* Attachments
* Description

Example:

---

CREATE 3 PRODUCT VIDEOS

🎬 AI Video Editors

Assigned to:
Vince

Created by:
CEO

🔵 IN PROGRESS

🔥 High Priority

Started:
Today, 9:00 AM

Due:
Today, 5:00 PM

---

# 12. TASK HISTORY / AUDIT TRAIL

Every important task action should be recorded.

Example:

Task created by CEO
↓
Assigned to Vince
↓
Task started
↓
Description updated
↓
Attachment added
↓
Marked Done

Display a simple activity timeline.

This is important because it removes the need to search Messenger to understand what happened.

---

# 13. COMPLETION

Employees should NOT need to reply to the CEO in Messenger just to say:

> Done po.

Instead they click:

### ✅ Mark as Done

Optionally allow a completion note:

> Finished all 3 videos. Uploaded them to the shared folder.

Record:

* Who completed it
* Completion timestamp
* Completion note

The CEO dashboard automatically updates.

---

# 14. OVERDUE TASKS

Automatically detect overdue tasks.

If:

Current time > deadline

and:

Status != DONE

then display:

🔴 OVERDUE

Do not automatically mark overdue tasks as done or cancelled.

---

# 15. NOTIFICATIONS

The system should have an internal notification system.

Notify employees when:

* A task is assigned to them
* A task's scheduled start time arrives
* A deadline is approaching
* A task is reassigned
* A task is edited significantly

Notify CEO/team lead when:

* A task is marked Done
* A task becomes overdue
* A task is completed

Keep notifications concise.

Example:

> 📋 New Task
> Create 3 product videos
> Assigned by CEO
> Due today at 5:00 PM

---

# 16. MESSENGER INTEGRATION

Do NOT make Facebook Messenger the primary database.

The task system should be the source of truth.

Initially support a simple workflow where the system can generate a notification message that can be sent through Messenger.

Example:

> 📋 NEW TASK
>
> Create 3 product videos
>
> Assigned to: Vince
> Team: AI Video Editors
> Priority: High
> Due: Today, 5:00 PM
>
> Open Task:
> [Task Link]

Architect the notification system so that actual Messenger/API integration can be added later without rebuilding the task system.

Do not depend on Messenger for core functionality.

---

# 17. TASK LINKS

Every task should have a unique URL.

Example:

`/tasks/123`

If an employee receives a notification containing the task link, they can immediately open the exact task.

---

# 18. SEARCH

Implement fast global task search.

Search by:

* Task title
* Description
* Employee
* Team

Example:

Search:

> TikTok

Returns all relevant TikTok tasks.

---

# 19. FILTERS

Provide simple filters:

### Status

* All
* Scheduled
* In Progress
* Done
* Overdue

### Team

* All Teams
* AI Video Editors
* Platform Marketing
* Automation
* Development
* etc.

### Assignee

Select employee.

### Date

* Today
* Tomorrow
* This week
* Custom

### Priority

* Normal
* High
* Urgent

Filters should work together.

---

# 20. MY TASKS

Employees should have a dedicated page:

# My Tasks

TODAY

🔴 Urgent
Create product video

🔵 In Progress
Update product listings

⏰ Scheduled
Prepare campaign assets — 2:00 PM

COMPLETED

✅ Product research

Keep this page extremely simple.

---

# 21. CEO "ATTENTION NEEDED" SECTION

This is an important usability feature.

The CEO should immediately see things requiring attention:

### Attention Needed

🔴 2 Overdue Tasks

⏰ 3 Tasks Starting Soon

⚠️ 4 Tasks Due Today

This prevents the CEO from having to manually inspect every team.

---

# 22. QUICK ACTIONS

CEO dashboard should have prominent:

`+ Create Task`

Also:

`View Overdue`

`View Today's Tasks`

`View Teams`

`View Completed`

Make task creation accessible from anywhere.

---

# 23. RECURRING TASKS

Support recurring tasks, but keep the implementation simple.

Examples:

* Every day
* Every week
* Every month

Example:

> Daily product monitoring
>
> Every weekday at 9:00 AM

The system generates the next task automatically.

Do not create an overly complicated recurrence builder.

---

# 24. TASK TEMPLATES

Allow frequently repeated tasks to become templates.

Example:

### Product Video Template

Team:
AI Video Editors

Priority:
High

Default instructions:
...

The CEO can click:

`Create from Template`

and modify the details.

This can significantly reduce repetitive task creation.

---

# 25. DASHBOARD DESIGN

Use a modern professional SaaS-style interface.

Design principles:

* Clean
* Minimal
* Fast
* Professional
* Mobile responsive
* Large readable status indicators
* Clear hierarchy
* No unnecessary animations
* No excessive gradients
* No clutter

Use Tailwind CSS.

Use Lucide icons.

Support light and dark mode if practical.

Primary accent can use a professional blue.

---

# 26. MOBILE EXPERIENCE

The system MUST work well on phones.

Employees may primarily access tasks from their phones.

Mobile dashboard:

Today's Tasks

Task cards should have:

* Title
* Deadline
* Priority
* Status
* Assignee

Large touch-friendly buttons:

`Start`

`Mark Done`

Avoid tiny desktop-only controls.

---

# 27. DATABASE STRUCTURE

Create appropriate Laravel migrations/models.

At minimum:

### users

* id
* name
* email
* password
* role
* created_at
* updated_at

### teams

* id
* name
* description
* created_at
* updated_at

### team_user

* team_id
* user_id

### tasks

* id
* title
* description
* created_by
* assigned_to nullable
* team_id nullable
* status
* priority
* scheduled_at nullable
* deadline nullable
* started_at nullable
* completed_at nullable
* completion_note nullable
* created_at
* updated_at

### task_attachments

* id
* task_id
* uploaded_by
* file_name
* file_path
* mime_type
* file_size
* created_at

### task_activities

* id
* task_id
* user_id
* action
* metadata JSON nullable
* created_at

### notifications

Use Laravel's notification infrastructure where appropriate.

---

# 28. API STRUCTURE

Create clean REST APIs.

Examples:

GET `/api/tasks`

POST `/api/tasks`

GET `/api/tasks/{id}`

PUT `/api/tasks/{id}`

DELETE `/api/tasks/{id}`

POST `/api/tasks/{id}/start`

POST `/api/tasks/{id}/complete`

POST `/api/tasks/{id}/reopen`

GET `/api/teams`

GET `/api/teams/{id}`

GET `/api/users`

GET `/api/dashboard`

GET `/api/notifications`

Use Laravel Form Requests for validation.

Use API Resources where appropriate.

---

# 29. AUTOMATION / SCHEDULED JOBS

Use Laravel Scheduler/Queues where appropriate.

Automatically:

1. Detect scheduled tasks whose start time has arrived.
2. Change them to IN PROGRESS.
3. Generate appropriate notifications.
4. Detect approaching deadlines.
5. Detect overdue tasks.

Make the jobs idempotent so they can safely run repeatedly.

---

# 30. SECURITY

Implement:

* Authentication
* Authorization policies
* Role permissions
* Request validation
* CSRF protection where applicable
* File upload validation
* Maximum file size
* Allowed MIME types
* Secure password hashing
* Rate limiting for sensitive endpoints
* Prevent unauthorized task access
* Prevent employees from editing other employees' tasks unless permitted

Never trust frontend permissions alone.

Enforce authorization on the Laravel backend.

---

# 31. PERFORMANCE

Optimize for a small company's daily use.

Use:

* Pagination
* Database indexes
* Eager loading where appropriate
* Debounced search
* Lazy loading where appropriate
* Efficient API responses
* Avoid unnecessary polling

The dashboard should feel instant.

---

# 32. IMPORTANT UX RULE

Do NOT turn this into Jira, Asana, Monday.com, or a complicated enterprise project-management platform.

The company needs:

**Create → Assign → Work → Done**

Everything else should support that flow.

The CEO should be able to create a task in roughly **10–20 seconds**.

An employee should be able to understand their task immediately.

---

# 33. INNOVATIVE BUT PRACTICAL FEATURES

Only implement features that genuinely reduce work.

### 1. Smart Team → Member Selection

Selecting a team immediately filters available employees and shows their current workload.

### 2. Quick Task Creation

Allow the CEO to create tasks with minimal fields.

### 3. Task Templates

Useful for repetitive company workflows.

### 4. Automatic Scheduling

Scheduled tasks automatically become active at the appropriate time.

### 5. Attention Needed

The CEO immediately sees overdue and upcoming important tasks.

### 6. Activity Timeline

Provides accountability without requiring Messenger conversations.

### 7. Messenger-ready Notifications

The system can generate concise messages containing a direct task link.

Do NOT add AI merely for the sake of calling something "AI."

---

# 34. OPTIONAL FUTURE AI FEATURE

Architect the system so AI can be added later.

Potential future feature:

CEO types:

> "Tomorrow morning, have the AI video team make three videos for the new herbal product and finish them before 5 PM."

AI could extract:

Team:
AI Video Editors

Task:
Create 3 promotional videos

Start:
Tomorrow morning

Deadline:
Tomorrow 5 PM

Priority:
Normal

BUT:

AI must never automatically create or assign important tasks without confirmation.

The CEO should see:

> I understood this as:

and then:

`Confirm & Create`

This should be a future-ready architecture, not a requirement for the MVP.

---

# 35. RESPONSIVE NAVIGATION

Desktop:

Sidebar:

Dashboard
Tasks
Teams
Members
Templates
Notifications
Settings

Mobile:

Bottom navigation or compact menu:

Home
Tasks
Teams
Notifications

Large:

`+ Create Task`

button.

---

# 36. EMPTY STATES

Create useful empty states.

Example:

> 🎉 No overdue tasks
>
> Everything is on schedule.

For employees:

> You're all caught up!
>
> No active tasks assigned to you.

---

# 37. ERROR HANDLING

Provide clear human-readable errors.

Never expose raw Laravel/PHP errors to users.

Example:

Instead of:

> SQLSTATE[23000]...

show:

> Unable to save the task. Please try again.

Log technical errors server-side.

---

# 38. SEED DATA

Create realistic seed data for demonstration.

Teams:

* AI Video Editors
* Platform Marketing
* Automation
* Development

Create several employees and example tasks in different states.

This should allow the application to look complete immediately after installation.

---

# 39. DOCUMENTATION

Provide:

* Installation instructions
* Environment configuration
* Database setup
* Migration commands
* Seeder commands
* Local development instructions
* Production deployment instructions
* Scheduler configuration
* Queue configuration
* API documentation
* Default login credentials for development only

---

# 40. DEVELOPMENT APPROACH

Build the system in this order:

### Phase 1

Authentication + users + roles

### Phase 2

Teams + team members

### Phase 3

Task creation + assignment

### Phase 4

Task status + completion

### Phase 5

CEO dashboard

### Phase 6

Employee "My Tasks"

### Phase 7

Scheduling + automatic status changes

### Phase 8

Notifications

### Phase 9

Attachments + activity timeline

### Phase 10

Templates + recurring tasks

### Phase 11

Messenger-ready notification architecture

### Phase 12

Polish + responsive design + security + testing

---

# FINAL PRODUCT GOAL

The finished application should make the company's workflow feel like:

CEO:

**Create Task**

↓

Choose:

**Team → Member**

↓

Set:

**Start → Deadline → Priority**

↓

**Create**

↓

Employee receives notification

↓

Employee opens task

↓

**In Progress**

↓

Employee finishes work

↓

**Mark Done**

↓

CEO automatically sees:

**✅ Completed**

The company should no longer need Messenger conversations as proof or the primary record of whether work was completed.

The system should be simple enough that employees actually use it every day.

Prioritize **simplicity, speed, reliability, and real operational usefulness over unnecessary features.**
