<template>
  <BaseModal :is-open="isOpen && !!task" title="Share a clear handoff" description="Copy the task link or a ready-to-send message." @close="$emit('close')">
    <div class="stack">
      <div class="field"><label for="share-link">Task link</label><div class="row"><input id="share-link" class="input" :value="taskUrl" readonly @focus="selectText" /><button class="btn" @click="copy(taskUrl, 'link')"><Check v-if="copied === 'link'" aria-hidden="true" /><Link v-else aria-hidden="true" />{{ copied === 'link' ? 'Copied' : 'Copy link' }}</button></div></div>
      <div class="field"><label for="share-message">Messenger message</label><textarea id="share-message" :value="formattedMessage" rows="9" readonly style="min-height:240px" @focus="selectText" /></div>
      <p class="small muted">The recipient will be asked to sign in before opening the task. Copying a message leaves you in control of when and where it is sent.</p>
      <p v-if="copyError" class="field-error" role="alert">Clipboard access is unavailable. Select the link or message above and copy it manually.</p>
    </div>
    <template #footer><a class="btn" href="https://www.messenger.com" target="_blank" rel="noopener noreferrer">Open Messenger <ExternalLink aria-hidden="true" /><span class="sr-only">(opens a new tab)</span></a><button class="btn btn-primary" @click="copy(formattedMessage, 'message')"><Check v-if="copied === 'message'" aria-hidden="true" /><Copy v-else aria-hidden="true" />{{ copied === 'message' ? 'Message copied' : 'Copy message' }}</button></template>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Check, Link, ExternalLink, Copy } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import { assigneeLabel, formatDateTime } from '@/utils/tasks'
import type { Task } from '@/types'
const props = defineProps<{ isOpen: boolean; task: Task | null }>(); defineEmits(['close'])
const copied = ref(''), copyError = ref(false)
watch(() => props.isOpen, () => { copied.value = ''; copyError.value = false })
const taskUrl = computed(() => props.task ? window.location.origin + '/tasks/' + props.task.id : '')
const formattedMessage = computed(() => props.task ? ['Task: ' + props.task.title, '', 'Assigned to: ' + assigneeLabel(props.task), 'Team: ' + (props.task.team?.name || 'General'), 'Priority: ' + props.task.priority, 'Status: ' + props.task.status, 'Due: ' + formatDateTime(props.task.deadline) + ' (UTC+8)', '', 'Open task: ' + taskUrl.value].join('\n') : '')
async function copy(value: string, type: string) { try { await navigator.clipboard.writeText(value); copied.value = type; copyError.value = false } catch { copyError.value = true } }
function selectText(event: FocusEvent) { (event.target as HTMLInputElement | HTMLTextAreaElement).select() }
</script>
