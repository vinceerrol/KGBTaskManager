# KCG workspace design system
Version: September 30, 2026. Implementation: frontend/src/workspace.css.

## Direction
A calm operations workspace with clear hierarchy, readable task content and visible ownership. Preserve the product's indigo identity. Give the primary action a consistent position. Use progressive disclosure for instructions, templates, preferences and advanced presentation choices.

## Tokens
| Token | Light | Dark | Purpose |
| --- | --- | --- | --- |
| Canvas | #f5f6fa | #111420 | Workspace background |
| Surface | #ffffff | #1c2030 | Cards, forms, header |
| Soft surface | #f8f9fc | #24293c | Secondary grouping |
| Ink | #1e2538 | #f0f2fb | Primary text |
| Muted | #606b80 | #b0b8cd | Secondary information |
| Brand | #5146d8 | #b3aaff | Main action and focus |
| Brand soft | #efedff | #302b52 | Selection and emphasis |
| Control boundary | #8994a8 | #7d8ba7 | Identifiable input edges |
| Success | #16714e | #80ddb1 | Completed work |
| Warning | #925408 | #ffd084 | Scheduling and attention |
| Danger | #b42f49 | #ffabbc | Error, overdue, destructive action |
| Info | #245fba | #9ac5ff | Work in progress |

Pair semantic text with its corresponding soft background. Use labels or icons alongside status color. Decorative panel borders may use a quieter line token; inputs use the control boundary token.

## Typography and geometry
- Inter with system sans fallback. Body: 14px / 1.55. Form labels: 13px. Metadata: 12px.
- Page headings: 25–32px, tight spacing. Section headings: 18px. Task title: 15–17px.
- Primary controls: at least 44px high. Mobile inputs: 16px text. Icons: 18px inside named controls.
- Panel padding: 24px comfortable, 16px compact. Typical gaps: 8, 12, 16, 24 and 32px.
- Panel radius: 16px. Controls: 10px. Dialogs: 20px desktop / 16px phone.
- Keep a readable line length. Wrap task titles and instructions; truncate only secondary summary content with a fuller detail view available.

## Responsive behavior
- Desktop: persistent navigation rail and header, bounded content width, flexible grids.
- Below 1281px: narrower sidebar, two task/team card columns.
- Below 1024px: bottom navigation plus all-page menu; board stages stack.
- Below 768px: single-column cards and forms, two-column metrics/filters, full-width dialog constrained to the viewport.
- Add safe-area padding to mobile navigation and messages. Preserve browser zoom.
- Desktop side navigation adapts to short browser-content heights; all navigation and workspace controls remain accessible. Below 920px reduce vertical gaps; below 720px hide the secondary promo and allow sidebar scrolling as a fallback.
- Tables may scroll inside their own named, keyboard-focusable region. The overall document should not scroll horizontally.

## Components and states
| Component | Required behavior |
| --- | --- |
| Button | Explicit action label; 44px primary target; visible focus; pending/disabled treatment. |
| Field | Persistent label, optional/required guidance, contextual error, preserved value after failure. |
| Task card | Priority/status, title button, team, deadline, owner and explicit actions. |
| Dialog | Native showModal, name from heading, inert background, Tab containment, Escape and trigger restoration. |
| Empty state | Explain the state and provide one useful next action. |
| Loading state | Skeleton or short status message; distinguish loading from an empty result. |
| Error state | Explain recovery, keep existing data/input, and expose retry where appropriate. |
| Toast | Status or alert; named dismiss; optional recovery action; pause timer on hover/focus. |
| Inbox | Confirm read changes; task destination; loading/error/empty states. |
| Workload | Actual counts and relative distribution; never imply invented capacity. |
| Command palette | Named search, result list, selected option, arrows/Enter and Escape. |

## Motion
Fast feedback: 140ms. Page/dialog entrance: 220ms. Ease: cubic-bezier(.2,.7,.2,1).
Animate opacity and transform for entrances. Use restrained hover/press feedback. Pending spinners and skeletons communicate work, not decoration. Respect prefers-reduced-motion and the workspace override. Avoid infinite attention pings and unnecessary motion.

## Content and workflows
- Use plain verbs: Create task, Start, Complete task, Save changes, Reopen, Resume, Pause.
- Confirm success only after the API accepts a change.
- Display dates in Asia/Manila (UTC+8); store new task/schedule dates in UTC.
- Explain what a recommendation uses. Focus suggestions use overdue state, priority, deadlines and progress.
- Keep advanced features optional: saved views, board mode, focus mode and compact spacing.
- Allow C and / shortcuts to be disabled for speech-input compatibility. Preserve Ctrl/Cmd K inside normal text fields and leave typing alone for single-key shortcuts.
- Copy sharing content; let the user decide when to send it.
- Keep role rules consistent in the UI and API. Restricted actions should not be advertised to the wrong role.

## Verification before adding a screen
Check populated, empty, loading, error, pending, success, keyboard, phone and dark-mode states. Verify the changed workflow end to end. Reuse these tokens and BaseModal instead of adding a separate visual or interaction system.
