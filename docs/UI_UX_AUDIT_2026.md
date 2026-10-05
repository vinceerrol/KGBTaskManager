# KCG workspace UI/UX audit and implementation
Date: September 30, 2026. Scope: every existing application screen, shared component, task dialog and supported workflow.

## Result
The workspace now has a consistent visual system, accessible navigation and dialogs, clearer assignment flows, practical productivity features and reliable feedback. This work covers sign-in, Overview, My Work, All Tasks, Teams & Workload, Workflow Library, task creation/editing/completion/sharing, notifications and preferences.

The implementation preserves Vue, Pinia and Laravel. It uses the existing product's indigo identity, readable Inter typography, a quiet canvas, and a dark navigation rail. It adds useful choices without turning the default workspace into a dense control panel.

## Research and design basis
Sources were consulted directly during this audit. The recommendations below apply established standards and current design-system guidance to this product; they are not predictions about every 2026 trend.

- Keyboard access, visible and unobscured focus, reflow, status messages, input errors and pointer target size informed the accessibility work. WCAG 2.2 AA sets a 24 CSS pixel target criterion with exceptions; this design uses 44 pixel primary controls. [W3C WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- Dialogs need an inert background, contained keyboard focus, a meaningful accessible name, an Escape path and focus restoration. Native dialogs plus an explicit Tab boundary handler implement those behaviors. [W3C modal dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)
- Motion follows the operating system's reduced-motion preference and also has a user override. [MDN prefers-reduced-motion](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion)
- Empty states explain the state and give an appropriate next action. Search with no matches, a new workspace and a request failure each have different messages. [Carbon empty states](https://carbondesignsystem.com/patterns/empty-states-pattern/)
- Forms use persistent labels, contextual guidance, grouped choices, inline validation, an error summary and preserved input after failure. [Carbon forms](https://carbondesignsystem.com/patterns/forms-pattern/)
- Notifications distinguish background feedback from errors requiring attention. Confirmed task changes, persistent error feedback, an accessible inbox and contextual dialog feedback replace decorative or misleading status claims. [Carbon notifications](https://carbondesignsystem.com/patterns/notification-pattern/)

## Screen and workflow findings
P1 means a workflow, access or accessibility defect; P2 means a substantial usability problem; P3 means polish. These findings were addressed in the implementation.

| Area | Priority | Finding | Implemented change |
| --- | --- | --- | --- |
| Visual foundation | P2 | Very small text, inconsistent spacing and scattered styling reduced scanability. | Semantic color/spacing/motion tokens, 14px body text, 12px metadata, clear heading hierarchy, consistent controls and panels. |
| Responsive foundation | P1 | Zoom was disabled; small screens could lose access to secondary pages. | Zoom restored, phone-sized form controls, safe-area navigation, all-page mobile menu and responsive layouts. |
| Global navigation | P1 | Clickable containers and uncertain current location made keyboard navigation harder. | Semantic links/buttons, active page styling, skip link, page titles and focus on route changes. |
| Header search | P1 | The search affordance implied behavior that was not connected to task discovery. | Search-and-command palette covering task title, instructions, team and owner; keyboard selection and direct task opening. |
| Header menus | P1 | Unsupported outside-click handling left menus unreliable. | Real pointer/focus listeners, Escape closing and focus restoration. |
| Account switching | P1 | Account-specific lists and detail state could remain after a role switch. | Store resets, stale fetch protection, cleared dialogs/messages and account-scoped drafts/views. |
| Sign-in | P2 | Field guidance and error recovery were weak; development demo affordances appeared as normal product behavior. | Labels, autocomplete, reveal-password control, error summary, preserved redirect, development-only demo UI and a server production guard. |
| Overview | P2 | Status cards and team indicators needed clearer meaning and useful destinations. | Clickable metrics with matching task filters, real counts, explicit captions, team workload links and refresh feedback. |
| Attention list | P2 | A short attention list could hide the relationship to all overdue work. | Prioritized actionable rows plus a link to the complete filtered overdue view. |
| My Work | P2 | Personal work needed a clearer next step and useful subsets. | Active/Today/Overdue/Completed views, counts, personal assignments and shared team work. |
| Focus workflow | P2 | Selecting the next task required repeated scanning. | Optional focus mode with a clear suggestion and explanation based on overdue state, urgency, deadline and progress. |
| All Tasks | P2 | Filtering and presentation choices were limited or inconsistent. | Local scoped filtering, sorting, removable filter chips, URL filters, and cards/list/board presentations. |
| Saved views | P2 | Repeated searches required rebuilding the same filters. | Named account-scoped browser views storing filters, presentation and sort; removal supports Undo. |
| Task cards | P1 | Card clicks and action controls needed meaningful keyboard targets and labels. | Named task title buttons, semantic articles, named actions, pending states and role-aware controls. |
| Creation | P1 | Prefills and team/member assignment could be lost; instructions and ownership were easy to miss. | Template/quick-assignment prefills, scoped member choices, multi-owner checkboxes, whole-team assignment, date validation and explicit timezone. |
| Draft recovery | P2 | Closing a form could discard work. | Account-scoped browser draft storage with explicit Resume; form input survives failed submissions. |
| Task details | P2 | Details and history needed a coherent reading order and dependable editing. | Ownership, dates, instructions, files, completion notes and ordered activity; inline saved/error feedback. |
| Reassignment | P1 | Existing work needed a direct way to change its team and owners. | Team and multi-owner editing, updated owner relations, notifications for newly assigned members, and assignment history. |
| Completion | P1 | A successful-looking action could precede server confirmation or be difficult to recover. | Confirmed completion, handoff notes, pending controls and a ten-second Undo that reopens the task. |
| Task transitions | P1 | Repeated transitions could duplicate activity and notifications. | Invalid/repeated status transitions are rejected; owner/team permissions match visible actions. |
| Deletion | P1 | Destructive actions needed a clear boundary. | Administrator-only deletion with an explicit in-dialog confirmation and a Keep task path. |
| Files | P2 | Upload limits, errors and feedback were hard to discover. | Named upload control, visible 20 MB limit, inline success/error feedback and attachment metadata. |
| Sharing | P2 | Sharing needed a clear, trustworthy handoff. | Copy task link, copy a formatted message, visible clipboard feedback, manual Messenger opening and authenticated deep links. |
| Teams | P2 | Workload indicators could imply capacity or performance without evidence. | Real active/due/overdue counts, relative distribution, scoped team access, readable member tables and selected-member quick assignment. |
| Templates | P1 | Reuse needed to preserve instructions and context. | Search, instruction preview, creation, save-from-task and usable task prefills. |
| Recurrence | P1 | Schedule records existed without a complete execution path or useful next-run feedback. | Validated daily/weekly/monthly schedules, next-run dates, confirmed pause/resume and a registered Laravel runner. |
| Notifications | P1 | Read status and task navigation were disconnected or premature. | Confirmed read updates, task links, originating-view return, loading/error/empty inbox states. |
| Preferences | P2 | A single visual density and motion setting could not suit different users. | Light/dark/device appearance, comfortable/compact spacing, reduced-motion preference and shortcut help. |
| Time handling | P1 | Offset dates could shift by eight hours between SQL storage, API output and UI. | UTC normalization for new task/schedule writes, consistent UTC+8 display, UTC day boundaries and scheduler comparisons. |
| Feedback | P1 | Request failure could look like an empty dataset or hide messages behind dialogs. | Independent loading/error states, retry paths, retained data, skeletons, in-dialog feedback and accessible status/error messages. |
| Motion | P3 | Repetitive decoration competed with work and could ignore user preference. | Short opacity/transform entrances, subtle hover/press feedback, progress/pending states and reduced-motion support. |
| Perceived performance | P2 | Every screen did not need to be downloaded at sign-in. | Lazy route chunks, local filtering of the loaded scoped task list and separate loading states. |

## Interaction decisions
- Ctrl/Cmd K opens search, including from a text field. Optional single-key shortcuts (/ for search and C for manager task creation) avoid text fields and can be disabled in Preferences. Ctrl/Cmd Enter submits the task form.
- Focus mode is optional and deterministic. It does not send workspace data to an AI service.
- Board stages expose explicit actions that work with keyboard and touch. Dragging is not required.
- Team workload counts describe activity. They do not claim to measure an individual's capacity or productivity.
- Theme, density and motion preferences belong to the browser. Drafts and saved views additionally belong to the signed-in account.
- Data refreshes on entry, explicit refresh, inbox opening and confirmed mutations. This implementation does not claim push-based live synchronization.
- Sharing copies a message; the user controls whether and where to send it.

## Role behavior
| Role | Read scope | Actions |
| --- | --- | --- |
| CEO / administrator | Entire workspace | Create, assign, edit, transition, delete and manage workflows. |
| Team lead | Their teams, their own creations and directly assigned work | Create/assign within their teams; manage their own/team work; act on personally assigned work. |
| Member | Their teams and directly assigned work | Start, complete, reopen/undo and upload for their own assignments or unassigned whole-team work. |
Shared general templates are available to all roles. Template/workflow creation is restricted to managers. Task deletion and team creation are restricted to the administrator. The API enforces these boundaries.

## Verification
Final browser checks and the review preview use a separate SQLite fixture, storage/app/uiux-preview.sqlite, served with Laravel's --no-reload option. Initial browser QA used the configured local MySQL development database because Laravel's reload process discarded the process-level database overrides. The two tasks, two templates and one recurring schedule created by QA were identified by exact ID, title, creator and creation date, backed up, then removed with their related audit activity and notifications. The known seed notification opened during QA was restored to unread. Six existing tasks, two templates and the original recurring schedule remain; no database was reset. The private cleanup backup is in backend/storage/app/uiux-qa-records-backup-2026-09-30.json.

| Check | Result |
| --- | --- |
| Frontend production build | vue-tsc and Vite pass. Lazy route chunks produced. |
| Laravel tests | 19 passing tests, 108 assertions, using an in-memory SQLite database. |
| Status workflows | Start, complete, Undo and reopen checked through the browser; duplicate calls covered by API tests. |
| Forms | Empty title, field error focus, draft resume, template prefill, quick assignment, scheduled creation, date editing and reassignment checked. |
| Keyboard | Dialog Tab/Shift+Tab boundaries, Escape, trigger restoration and command arrows/Enter checked. Disabling single-key shortcuts prevents C from opening a dialog; Ctrl K still opens search from a text field. Workload scroll regions are named and focusable. |
| Navigation | Direct task link, sign-in return, notifications, originating-view return and all-page mobile menu checked. |
| Preferences | Light/dark, compact/comfortable and reduced-motion behavior checked; reduced motion drops animation duration to approximately 0.01 ms. |
| Responsive task layout | 375, 768, 1024 and 1440 CSS pixel widths checked without document overflow. Boards stack below 1024px. At 1920 × 915 browser-content pixels (matching the user report after browser chrome), the complete desktop sidebar—including its footer—fits on screen. It compresses vertical spacing below 920px, hides the secondary tip below 720px and scrolls internally if needed. |
| Phone dialogs | Scrollable body and visible action footer checked at 375 x 812. |
| API dates | Offset round trips, UTC-midnight day boundaries, scheduled start instant, weekly weekday and short-month recurrence tested. |
| Roles | Member actions and role switching checked in the browser; API tests cover foreign teams, foreign owners, scoped reads and production demo blocking. |
| Files | Upload, size rejection, storage persistence and activity covered by API tests. |
| Failures | Input retention, loading/error/retry components and confirmed mutation behavior checked. See the recovery evidence below. |

Recovery evidence: a temporary 503 response was restricted to the local SQLite preview. All seven loaded task cards remained visible with a contextual error and a Try again action. After removing the temporary fault, Try again succeeded, the inline error cleared and all seven tasks remained visible. The temporary failure code was removed from the application. [Recovery screenshot](assets/uiux-recovery.jpg)

Measured core token contrast, rather than a claim about every possible content combination:

| Pair | Light | Dark |
| --- | ---: | ---: |
| Body text / surface | 15.25:1 | 14.48:1 |
| Secondary text / surface | 5.37:1 | 8.15:1 |
| Primary button text / brand | 6.53:1 | 8.28:1 |
| Control boundary / surface | 3.06:1 | 4.71:1 |

Light-theme semantic success/warning/error/info badge text measured at least 5.38:1 on its matching background. This is an accessibility-oriented implementation, not a certification of WCAG conformance. Screen-reader user testing and cross-browser/device validation remain appropriate release checks.

## Deployment and data considerations
1. Run Laravel's scheduler in the deployed environment. Registering the command does not start a hosted cron process. Recurring and automatic scheduled tasks depend on it.
2. New date writes are normalized to UTC. Older rows may mix local and UTC values; review them using known reference times before any data repair. No automatic historical rewrite was performed.
3. The existing public storage mechanism for attachments is retained. Establish a private-download policy before using confidential files; frontend task visibility does not secure a public file URL.
4. Test against the deployed MySQL database and hosting setup before release. SQLite tests verify behavior but do not prove MySQL concurrency or production performance.
5. Saved views and drafts are local to this browser. Cross-device synchronization would require a separate server-backed preference model.

## Next product research
The implemented changes address the observed workflow and accessibility defects. Future additions should follow user evidence:
- Observe a CEO, lead and member creating, finding, reassigning and completing work. Measure task success, time, repeated clicks and recovery.
- If filtering large datasets becomes slow, introduce server pagination and indexed search without breaking saved views and task links.
- If review handoffs are frequent, evaluate a dedicated review/approval stage with clear ownership.
- If users repeatedly coordinate dependencies, evaluate linked tasks and dependency warnings.
- Consider optional digests and notification preferences after measuring inbox volume.

## Evidence
- [Desktop overview](assets/uiux-overview.jpg)
- [Desktop overview at 1920 × 915 browser-content pixels](assets/uiux-overview-normal-browser.jpg)
- [Task board](assets/uiux-task-board.jpg)
- [Mobile dark theme and focus workflow](assets/uiux-mobile-dark.jpg)
- [Retained data during a failed request](assets/uiux-recovery.jpg)

The design decisions and component rules are recorded in [the design system](../design-system/kcg-workspace/MASTER.md). The isolated review preview runs at http://127.0.0.1:5174 while its local development processes are running.
