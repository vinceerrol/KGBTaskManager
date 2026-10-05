# Write a task

Implemented 30 September 2026; improved 1 October 2026 following composer testing. This is an additional creation route for CEOs and team leads. It creates one new task. The existing Create task form and editing of saved tasks keep their existing workflows.

## Using the composer

Open **Write a task** from the sidebar, Overview, the command palette, or the mobile More menu. Write in English or Taglish. Type `@` and select the exact person or team using the keyboard or pointer. A pasted name alone is not a linked account.

Example:

> Ipagawa kay @Carlo Mendoza ang 3 product videos sa @AI Video Editors, due bukas at 5 PM. Gamitin ang brand guide.

The paragraph is the only input surface on the left. The right side follows the existing creation form's field order: title, instructions, team, owners, priority, start and deadline. Its fields remain editable. Manually corrected values receive an **Edited** marker and survive later paragraph changes and AI responses. Preview edits never rewrite the paragraph.

Selected mentions have blue rounded boxes inside the paragraph and blue badges below it. The editor remains a native textarea: an aria-hidden highlight mirror preserves plain-text editing, selection, keyboard navigation and composition input. Its wrapping, width and scroll position follow the textarea; editing a linked name removes its binding. During IME composition and forced-color display, native text remains visible. Phone input uses 16px text.

Selecting a team gives team context. With no individual owners, creation stays disabled until the user explicitly chooses **Entire team**. That choice pins the team and ownership scope so later interpretation cannot silently move a confirmed broadcast to a different team. General tasks can be left unassigned. Start now creates an In progress task, matching the existing product behavior.

Desktop panes scroll independently. Narrow layouts stack the request above the preview; the dialog body scrolls and creation actions remain in the footer. Suggestions support arrow keys, Enter and Escape. Escape closes suggestions first, then the dialog. Ctrl/Cmd+Enter submits the reviewed draft. IME composition does not submit or interpret unfinished composition text. Existing focus containment, dark appearance and reduced-motion settings apply.

## Local interpretation and its limits

The instant layer needs no API key. It supports exact mentions, common English/Taglish assignment and reference phrases, priority, relative days, English/Filipino weekday names, ISO dates, full English month names with day numbers, and explicit AM/PM or 24-hour times. It distinguishes scheduled starts from deadlines. Removed or edited mentions lose their account binding. Titles automatically use the first meaningful work sentence after mention-only headers. Explicit priority signals include `urgent`, `madalian`, `high priority`, and `priority: high`; negation and later clear priority corrections are respected. Output-quality terms such as **highly edited** stay Normal unless a priority directive is also present.

For the reported request, with an October 1 reference:

> @AI Video Editors<br>@Carlo Mendoza and @Mark Dela Cruz @Anna Santos<br>gumawa kayo ngayon ng ai video na highly edited for our FB Meta ADS bukas. start na bukas ng 10:32pm at ang deadline 11pm

The local draft generates a title, selects the three people, uses **October 2, 22:32** for start and **October 2, 23:00** for deadline, and keeps Normal priority. A time-only deadline inherits the one explicit start calendar date even if its clause appears first; the preview displays **Uses the start date**. `Start now, deadline 11pm` uses the reference calendar day. Explicit deadline dates take precedence. Earlier deadlines stay on their stated/inherited day and require correction; the parser never silently rolls them to tomorrow. Empty, conflicting or unsupported timing phrases require review. Ambiguous numeric dates require an ISO date or a written month.

Date reference is recorded when the draft opens and retained on resume. Dates use Asia/Manila, UTC+8, regardless of browser timezone. Date-only deadlines are visibly set to **23:59, end of calendar day**; this version does not introduce a separate date-precision model. A bare hour without AM/PM, EOD, mamaya, or a scheduled start without a time requires correction. Past dates and deadlines before start are rejected on submission. Review resumed relative dates against their original reference.

This local layer is a defined set of rules, not unrestricted bilingual language understanding. Operational instructions initially retain the request text with selected mention names rendered normally; users can edit those instructions independently. Exact ownership and timing come from the reviewed fields. Recurrence and multiple selected teams require review rather than silently creating recurring or multi-team work. Creating several independent tasks from a paragraph is outside this release.

## Optional AI setup: Groq or OpenAI

Interpretation is an authenticated server request. No provider key belongs in frontend environment variables or this chat. For **Groq**, configure `backend/.env`:

```dotenv
TASK_BRIEF_AI_ENABLED=true
TASK_BRIEF_AI_PROVIDER=groq
GROQ_API_KEY=your-server-side-key
GROQ_TASK_MODEL=openai/gpt-oss-120b
```

The Groq adapter uses Chat Completions with a strict JSON schema. The default model and `openai/gpt-oss-20b` are documented as supporting [strict Structured Outputs](https://console.groq.com/docs/structured-outputs). The GPT-OSS models use low reasoning effort with reasoning excluded from the returned message, following [Groq's reasoning API](https://console.groq.com/docs/reasoning). Other configured models must support strict schema output; no automatic downgrade to unrestricted JSON is made. Unsupported models fail back to the local draft. This adapter does not imply measured Taglish accuracy.

The published [Groq free-plan limits](https://console.groq.com/docs/rate-limits) for the default model currently include 30 requests/minute, 1,000 requests/day and 8,000 tokens/minute. Actual workspace limits can differ. The client waits for a 1.5-second typing pause, spaces Groq request starts at least five seconds apart, and skips short or unfinished mention input. Provider HTTP 429 responses honor `Retry-After` with a shared-key backend cooldown and a client cooldown. No automatic retry loop runs during the cooldown; typing and local creation remain usable. Multiple simultaneous users can still exhaust a free-tier quota.

For **OpenAI**, use:

```dotenv
TASK_BRIEF_AI_ENABLED=true
TASK_BRIEF_AI_PROVIDER=openai
OPENAI_API_KEY=your-server-side-key
OPENAI_TASK_MODEL=gpt-4.1-mini
```

AI is disabled by default. Each provider requires its own key and model; OpenAI credentials are never used for Groq. After configuration, run `php artisan config:clear` from the backend and restart the backend process as appropriate. To disable provider calls, set `TASK_BRIEF_AI_ENABLED=false`. A missing key/model or unknown provider disables AI.

The OpenAI adapter uses the Responses API with a strict JSON schema and `store: false`, following [OpenAI's Structured Outputs documentation](https://developers.openai.com/api/docs/guides/structured-outputs). The default configurable model documents [Structured Outputs support](https://developers.openai.com/api/docs/models/gpt-4.1-mini). This choice is not a claim of measured Taglish accuracy or a permanently optimal model.

After the provider-specific typing pause (900 ms for OpenAI), the client sends the paragraph, its selected mentions, revision and date reference. The server sends only the paragraph, selected account/team names and IDs, and timing context to the configured provider. It does not send emails, the whole roster, or unrelated tasks. The composer displays that provider's name when AI is enabled. Calls are limited to 20 per minute per authenticated user, with a 12-second provider timeout. Provider processing is subject to the workspace's provider agreement and settings; `store: false` is not a promise of zero retention.

The provider proposes fields; it never creates tasks. Returned owner/team IDs must belong to the exact selected mentions. Schema, permissions, dates and ownership consistency are validated. Refusal, invalid output, rate limits and connection failure leave the local draft usable. Session/revision guards ignore old responses; submission freezes its reviewed payload.

No real provider request was made for development verification. Server behavior was tested using fake OpenAI and Groq responses, including malformed/refused/truncated output, invalid account IDs, connection failure, timing conflicts and rate-limit cooldown expiry. Schema compliance does not ensure semantic accuracy: broader English/Taglish accuracy and cost should be evaluated with a configured provider and representative business requests.

## Storage and deployment

Run the additive migration against the intended database before using the updated API:

```powershell
Set-Location backend
php artisan migrate
```

It adds `creation_method`, `source_brief`, `creation_key`, and `creation_payload_hash`. The two retry fields are hidden from task JSON. New brief creation requires an explicit assignment scope and a UUID key. A transaction writes the task, owner relation, activity and notifications together. A unique actor/key constraint and payload hash return the original task for a repeated identical submission; different details with an already used key return HTTP 409. Existing task updates do not interpret paragraphs or change the saved source brief.

Unfinished composer drafts are saved to browser local storage under the signed-in account ID and offered for recovery for 14 days. They include the paragraph, mention bindings, preview corrections, reference time and submission key. Starting fresh or successfully creating clears that draft. Clearing the paragraph removes its saved composer draft. Browser-local draft storage follows the same shared-device limitations as the existing creation form.

Authenticated endpoints:

- `GET /api/task-drafts/context`: permitted people/teams, date reference and AI availability.
- `POST /api/task-drafts/interpret`: validated proposed fields, status and echoed revision; no task writes.
- `POST /api/tasks`: the existing creation endpoint with optional brief metadata and retry safeguards.

The additive migration was applied to the existing isolated SQLite preview at `backend/storage/app/uiux-preview.sqlite`. The regular configured MySQL database was not reset or migrated as part of this verification.

## Verification

- Frontend production build passes; 28 parser/state-merge tests pass.
- Full Laravel suite passes: 38 tests, 251 assertions, using SQLite in memory.
- Browser checks covered exact mention selection, live fields, protected edits, draft recovery, explicit whole-team confirmation, layered Escape, Ctrl+Enter creation and the original creation form.
- Responsive checks: default 1131×612, desktop 1366×650, mobile 390×700 and 320×640. No horizontal overflow or hidden dialog footer at those sizes.
- Compact sidebar spacing was adjusted for the added entry; its footer remains visible without scrolling at the default 1131×612 window.
- One task was created through the composer in the isolated preview and inspected for final owner, priority and deadline. Only that QA task and its related test records were subsequently removed. The original Create task draft was preserved.
- The October 1 follow-up reproduced the full reported Taglish paragraph with exact team/member selections, checked inherited deadlines, earlier-deadline warnings, manual corrections, priority changes and mention retraction. Native highlight scrolling and desktop/mobile widths were checked. This follow-up created no tasks or notifications.
- At widths of 360px and below, the header's secondary breadcrumb is hidden so navigation and account controls fit without horizontal page overflow. The current page remains identified by its main heading.

Follow-up screenshots: [inline mentions and generated title](assets/brief-to-task-inline-mentions.jpg), [resolved start and deadline](assets/brief-to-task-date-context.jpg), and [mobile layout](assets/brief-to-task-inline-mobile.jpg).

Key files: `frontend/src/components/TaskBriefEditor.vue`, `frontend/src/components/TaskBriefModal.vue`, `frontend/src/utils/taskBrief.ts`, `backend/app/Http/Controllers/Api/TaskDraftController.php`, `backend/app/Services/TaskBriefInterpreter.php`, and `backend/tests/Feature/TaskDraftTest.php`.
