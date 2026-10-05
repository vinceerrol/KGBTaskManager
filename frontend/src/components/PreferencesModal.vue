<template>
  <BaseModal :is-open="isOpen" title="Make this space yours" description="Preferences are saved in this browser." @close="$emit('close')">
    <div class="stack" style="gap:24px">
      <div class="field"><label for="theme">Appearance</label><select id="theme" v-model="preferences.theme"><option value="light">Light</option><option value="dark">Dark</option><option value="system">Follow device</option></select></div>
      <div class="field"><label for="density">Content spacing</label><select id="density" v-model="preferences.density"><option value="comfortable">Comfortable — more breathing room</option><option value="compact">Compact — more content at a glance</option></select></div>
      <div class="field"><label for="motion">Motion</label><select id="motion" v-model="preferences.motion"><option value="system">Follow device preference</option><option value="reduced">Reduce animations</option></select><p class="field-hint">Your device's reduced-motion preference is always respected.</p></div>
      <hr class="form-divider" />
      <div class="stack" style="gap:8px"><label class="member-choice"><input v-model="preferences.singleKeyShortcuts" type="checkbox" /><span>Enable single-key shortcuts (C and /)</span></label><p class="field-hint">Turn these off if they interfere with speech input. Ctrl/Cmd K remains available.</p></div>
      <div class="stack"><h3>Move faster with shortcuts</h3><div class="row"><kbd>Ctrl / ⌘ K</kbd><span>Search tasks and commands</span></div><div class="row"><kbd>/</kbd><span>Open search</span></div><div v-if="!auth.isEmployee" class="row"><kbd>C</kbd><span>Create a task</span></div><div class="row"><kbd>Ctrl / ⌘ Enter</kbd><span>Submit a task form</span></div><div class="row"><kbd>Esc</kbd><span>Close a dialog or popover</span></div></div>
      <p class="small muted">All task dates use Asia/Manila (UTC+8). Saved views and unfinished drafts belong to your account in this browser.</p>
    </div>
    <template #footer><button class="btn btn-primary" @click="$emit('close')">Done</button></template>
  </BaseModal>
</template>
<script setup lang="ts">
import BaseModal from '@/components/BaseModal.vue'
import { usePreferencesStore } from '@/stores/preferences'
import { useAuthStore } from '@/stores/auth'
defineProps<{ isOpen: boolean }>(); defineEmits(['close'])
const preferences = usePreferencesStore(), auth = useAuthStore()
</script>
