# Brief to Task implementation plan

Accepted scope and delivery status · updated 1 October 2026

## Agreed interaction

Add **Write a task** as a separate route for creating one new task. The left side is an ordinary English/Taglish paragraph editor. Exact `@` selections link people and teams. The right side is an editable live draft that follows the existing Create task form's layout and field order.

There are no writing modes or extra setup controls on the paragraph side. Preview edits remain authoritative and do not rewrite the original paragraph. Existing normal creation and editing of saved tasks retain their current workflows.

## Implementation sequence

1. **Exact input and instant draft — implemented.** Native multiline input, scoped person/team suggestions, keyboard selection, UTF-16 mention bindings, English/Taglish rules, distinct scheduled start/deadline, immediate preview and automatic retraction of removed mentions.
2. **Correction and recovery — implemented.** Edited field markers, protected manual corrections, account-scoped local draft recovery, explicit whole-team ownership, visible review issues, error focus and submission shortcuts.
3. **Optional semantic interpretation — implemented.** Authenticated Laravel endpoint and optional server-side OpenAI/Groq adapters using strict structured output. Debounce, request revision/session checks, cancellation, bounded calls, rate-limit cooldowns and local fallback. No live provider evaluation is claimed.
4. **Reliable creation — implemented.** Additive source/retry metadata, assignment/date validation, an atomic transaction, actor/key uniqueness and payload hashing. Identical retries return the original task and do not duplicate notifications.
5. **Responsive and compatibility verification — implemented.** Independently scrolling desktop panes, stacked mobile layout, visible footer at ordinary short-window sizes, focus containment and native controls. Existing creation form checked without discarding its unfinished draft.

## Deliberate boundaries

- Plain names do not resolve into accounts. `@` selections are the source of exact identities.
- A team mention supplies context. Entire-team ownership requires an explicit phrase or preview confirmation.
- This creates one task. Automatic task splitting, recurring-task creation, invented teams and cross-task dependency generation are deferred.
- Manual preview edits do not generate or rewrite paragraph text.
- Date-only deadlines visibly use the end of that calendar day at 23:59. A separate date-precision schema and working-hours inference are deferred.
- No confidence percentages, chat interviews, streaming partial JSON, or unrelated redesign of saved-task forms.
- Local rules cover supported phrases. Unrestricted English/Taglish accuracy requires representative evaluation with the configured optional provider.

## Validation and rollout

The production build and 28 frontend tests pass. The complete backend suite passes 38 tests with 251 assertions. Browser verification covered mentions, correction preservation, recovery, whole-team confirmation, Escape, keyboard creation, saved task inspection and compatibility with the original form. The follow-up fixes mention-only title headers, independent start/deadline parsing, explicit date inheritance and inline blue mention boxes. Desktop and mobile footer/overflow checks passed at 1131×612, 1366×650, 390×700 and 320×640.

The additive migration is installed on the isolated SQLite preview only. Apply it to the intended deployment database. AI stays disabled until backend configuration enables the chosen provider with a server key. Run a bilingual pilot with actual business examples before making claims about interpretation accuracy, creation speed or cost.

[Implementation and setup notes](BRIEF_TO_TASK.md) contain the actual API contract, configuration, supported phrases, recovery policy and verification details.

## Research used

- [Todoist Quick Add](https://www.todoist.com/help/todoist/features/use-task-quick-add-in-todoist-va4Lhpzz): precedent for visible natural date recognition.
- [Linear Triage Intelligence](https://linear.app/docs/triage-intelligence): suggested properties and review workflows.
- [Microsoft Guidelines for Human-AI Interaction](https://www.microsoft.com/en-us/research/uploads/prod/2019/03/AI_Guidelines_Poster_PrintQuality.pdf): efficient correction and uncertainty handling.
- [W3C combobox pattern](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/): suggestion keyboard behavior, adapted around native multiline input.
- [OpenAI Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs): strict structured field proposals rather than automatic actions.
- [Groq Structured Outputs](https://console.groq.com/docs/structured-outputs), [Reasoning](https://console.groq.com/docs/reasoning) and [Rate Limits](https://console.groq.com/docs/rate-limits): strict Chat Completions, bounded reasoning and free-tier fallback behavior.
