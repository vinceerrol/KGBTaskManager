<template>
  <BaseModal :is-open="isOpen && !!task" title="Complete this task" :description="task?.title" :busy="submitting" @close="$emit('close')">
    <form id="complete-task-form" class="stack" @submit.prevent="handleComplete">
      <div class="notice"><CircleCheck aria-hidden="true" /><p>Your team will see the updated status. Add a handoff note or a link to the finished work.</p></div>
      <div class="field"><label for="completion-note">Completion note <span class="small muted">Optional</span></label><textarea id="completion-note" v-model="completionNote" autofocus rows="4" placeholder="What was delivered? Where can the team find it?" /></div>
      <p class="small muted">You can undo completion from the confirmation message.</p>
      <p v-if="failed" class="field-error" role="alert">The task wasn't completed. Your note is still here; try again.</p>
    </form>
    <template #footer><button class="btn btn-ghost" :disabled="submitting" @click="$emit('close')">Keep working</button><button form="complete-task-form" type="submit" class="btn btn-primary" :disabled="submitting"><LoaderCircle v-if="submitting" class="spinner" aria-hidden="true" /><Check v-else aria-hidden="true" />{{ submitting ? 'Completing…' : 'Complete task' }}</button></template>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, watch } from 'vue'
import { CircleCheck, Check, LoaderCircle } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import { useTaskStore } from '@/stores/tasks'
import type { Task } from '@/types'
const props = defineProps<{ isOpen: boolean; task: Task | null }>(), emit = defineEmits(['close', 'completed'])
const tasks = useTaskStore(), completionNote = ref(''), submitting = ref(false), failed = ref(false)
watch(() => props.isOpen, open => { if (open) { completionNote.value = ''; failed.value = false } })
async function handleComplete() { if (!props.task || submitting.value) return; submitting.value = true; failed.value = false; const success = await tasks.completeTask(props.task.id, completionNote.value.trim() || undefined); submitting.value = false; if (success) { emit('completed', props.task.id); emit('close') } else failed.value = true }
</script>
