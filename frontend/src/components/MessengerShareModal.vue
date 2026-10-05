<template>
  <BaseModal :is-open="isOpen && !!task" title="Send to Messenger" description="Copy a ready-to-send message, then pick the group chat in Messenger." @close="$emit('close')">
    <div class="stack">
      <div v-if="localOnly" class="error-banner" role="note"><TriangleAlert aria-hidden="true" /><p>This link only opens on this computer. Open the app from your PC's network address (for example <code>http://192.168.x.x:5173</code>) or set <code>VITE_PUBLIC_APP_URL</code> so teammates can open it. See <code>docs/LOCAL_SETUP.md</code>.</p></div>
      <ol class="small muted" style="margin:0;padding-left:20px;display:grid;gap:4px">
        <li>Press <strong>Copy &amp; open Messenger</strong>. The message is copied for you.</li>
        <li v-if="onPhone">Messenger's <strong>Send to</strong> list opens with the task link. Choose the group chat.</li>
        <li v-else>Messenger opens in a new tab. Choose the group chat.</li>
        <li>Paste the message ({{ onPhone ? 'press and hold, then Paste' : 'Ctrl+V' }}) and send.</li>
      </ol>
      <div class="field"><label for="share-message">Message</label><textarea id="share-message" :value="message" rows="10" readonly style="min-height:240px" @focus="selectText" /></div>
      <div class="field"><label for="share-link">Task link</label><div class="row"><input id="share-link" class="input" :value="taskUrl" readonly @focus="selectText" /><button class="btn" @click="copyOnly(taskUrl, 'link')"><Check v-if="copied === 'link'" aria-hidden="true" /><Link v-else aria-hidden="true" />{{ copied === 'link' ? 'Copied' : 'Copy link' }}</button></div></div>
      <p v-if="copied === 'opened'" class="notice" role="status"><Check aria-hidden="true" />Message copied. Choose the group chat in Messenger, then paste and send.</p>
      <p v-if="copyError" class="field-error" role="alert">Copying isn't allowed here. Select the message above, copy it, then paste it in Messenger.</p>
      <p class="small muted">Recipients sign in before the task opens.</p>
    </div>
    <template #footer>
      <button class="btn btn-ghost" @click="copyOnly(message, 'message')"><Check v-if="copied === 'message'" aria-hidden="true" /><Copy v-else aria-hidden="true" />{{ copied === 'message' ? 'Copied' : 'Copy message' }}</button>
      <button v-if="canShare" class="btn" @click="shareSheet"><Share2 aria-hidden="true" />Share…</button>
      <button class="btn btn-primary" @click="copyAndOpen"><Send aria-hidden="true" />Copy &amp; open Messenger</button>
    </template>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Check, Link, Copy, Send, Share2, TriangleAlert } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import { assigneeLabel, formatDateTime, statusLabel } from '@/utils/tasks'
import { buildShareMessage, taskLink, isLocalOnlyUrl, isMobileDevice, messengerTarget, copyText, copyTextNow } from '@/utils/share'
import type { Task } from '@/types'
const props = defineProps<{ isOpen: boolean; task: Task | null }>(); defineEmits(['close'])
const copied = ref(''), copyError = ref(false)
watch(() => props.isOpen, () => { copied.value = ''; copyError.value = false })
const onPhone = isMobileDevice(navigator.userAgent)
const canShare = typeof navigator.share === 'function'
const taskUrl = computed(() => props.task ? taskLink(props.task.id, window.location.origin, import.meta.env.VITE_PUBLIC_APP_URL) : '')
const localOnly = computed(() => !!taskUrl.value && isLocalOnlyUrl(taskUrl.value))
const message = computed(() => props.task ? buildShareMessage({
  title: props.task.title,
  description: props.task.description,
  assignees: assigneeLabel(props.task),
  team: props.task.team?.name || 'General',
  priority: props.task.priority,
  status: statusLabel(props.task),
  due: props.task.deadline ? formatDateTime(props.task.deadline) + ' (UTC+8)' : 'No deadline',
  url: taskUrl.value,
}) : '')
async function copyOnly(value: string, type: string) {
  const ok = await copyText(value)
  copyError.value = !ok
  copied.value = ok ? type : ''
}
// Copy synchronously first so opening Messenger still counts as part of the same click.
async function copyAndOpen() {
  copyError.value = false
  let ok = copyTextNow(message.value)
  const target = messengerTarget(navigator.userAgent, taskUrl.value)
  if (!ok) ok = await copyText(message.value)
  if (target.kind === 'app') window.location.href = target.href
  else window.open(target.href, '_blank', 'noopener')
  copyError.value = !ok
  copied.value = ok ? 'opened' : ''
}
async function shareSheet() {
  try { await navigator.share({ title: props.task?.title, text: message.value }) }
  catch (err) { if ((err as DOMException)?.name !== 'AbortError') copyError.value = !(await copyText(message.value)) }
}
function selectText(event: FocusEvent) { (event.target as HTMLInputElement | HTMLTextAreaElement).select() }
</script>
