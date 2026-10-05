// Sharing a task into Messenger without a Meta app: build the message, copy it, then open Messenger
// so the person picks the group chat and pastes. Kept free of Vue imports so it can be unit tested.

export interface ShareDetails {
  title: string
  description?: string | null
  assignees: string
  team: string
  priority: string
  status: string
  due: string
  url: string
}

const DESCRIPTION_LIMIT = 600

export function buildShareMessage(task: ShareDetails): string {
  const description = (task.description || '').trim()
  const brief = description.length > DESCRIPTION_LIMIT ? description.slice(0, DESCRIPTION_LIMIT).trimEnd() + '…' : description
  return [
    '📌 ' + task.title,
    ...(brief ? ['', brief] : []),
    '',
    '👤 Assigned to: ' + task.assignees,
    '👥 Team: ' + task.team,
    '⚡ Priority: ' + (task.priority === 'normal' ? 'Normal' : task.priority === 'high' ? 'High' : task.priority === 'urgent' ? 'Urgent' : task.priority),
    '📍 Status: ' + task.status,
    '⏰ Due: ' + task.due,
    '',
    '🔗 Open the task: ' + task.url,
  ].join('\n')
}

/** The address teammates should use. A configured public address wins over whatever this browser is on. */
export function taskLink(taskId: number, origin: string, publicBase?: string | null): string {
  const base = (publicBase || '').trim().replace(/\/+$/, '') || origin.replace(/\/+$/, '')
  return base + '/tasks/' + taskId
}

/** Links on localhost only open on the computer that made them. */
export function isLocalOnlyUrl(url: string): boolean {
  try {
    const host = new URL(url).hostname
    return host === 'localhost' || host === '::1' || host === '[::1]' || host.startsWith('127.')
  } catch { return false }
}

export function isMobileDevice(userAgent: string): boolean {
  return /Android|iPhone|iPad|iPod/i.test(userAgent)
}

/**
 * Phones: Messenger's own "Send to" picker, which accepts a link (not free text), so the full message is copied first.
 * Computers: Messenger on the web; there is no way to open a chosen conversation without a Meta app.
 */
export function messengerTarget(userAgent: string, url: string): { kind: 'app' | 'web'; href: string } {
  return isMobileDevice(userAgent)
    ? { kind: 'app', href: 'fb-messenger://share?link=' + encodeURIComponent(url) }
    : { kind: 'web', href: 'https://www.messenger.com/' }
}

/**
 * Copies synchronously so it still counts as part of the click (needed before opening another app or tab),
 * and works on plain-http network addresses where navigator.clipboard is unavailable.
 */
export function copyTextNow(text: string, doc: Document = document): boolean {
  const previous = doc.activeElement as HTMLElement | null
  const area = doc.createElement('textarea')
  area.value = text
  area.setAttribute('readonly', '')
  area.style.position = 'fixed'
  area.style.top = '0'
  area.style.opacity = '0'
  // Inside an open modal dialog only its own contents can take focus.
  const host = doc.querySelector('dialog[open]') || doc.body
  host.appendChild(area)
  // Without focus, the copy acts on the focused button and silently copies nothing while still reporting success.
  area.focus({ preventScroll: true })
  area.select()
  area.setSelectionRange(0, text.length)
  // Writing the text into the copy event makes the result independent of focus and selection quirks.
  let written = false
  const onCopy = (event: ClipboardEvent) => {
    if (!event.clipboardData) return
    event.clipboardData.setData('text/plain', text)
    event.preventDefault()
    written = true
  }
  doc.addEventListener('copy', onCopy, true)
  let ok = false
  try { ok = doc.execCommand('copy') && written } catch { ok = false }
  doc.removeEventListener('copy', onCopy, true)
  area.remove()
  previous?.focus?.({ preventScroll: true })
  return ok
}

export async function copyText(text: string): Promise<boolean> {
  if (copyTextNow(text)) return true
  try { await navigator.clipboard.writeText(text); return true } catch { return false }
}
