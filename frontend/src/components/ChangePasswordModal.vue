<template>
  <BaseModal :is-open="isOpen" title="Change password" description="Other devices will be signed out. This one stays signed in." :busy="saving" @close="$emit('close')">
    <form id="change-password-form" class="stack" novalidate @submit.prevent="submit">
      <div v-if="error" class="error-banner" role="alert">{{ error }}</div>
      <div class="field"><label for="current-password">Current password</label><input id="current-password" v-model="form.current_password" type="password" autocomplete="current-password" autofocus :aria-invalid="!!errors.current_password" /><p v-if="errors.current_password" class="field-error">{{ errors.current_password }}</p></div>
      <div class="field"><label for="new-password">New password</label><input id="new-password" v-model="form.password" type="password" autocomplete="new-password" minlength="10" :aria-invalid="!!errors.password" /><p v-if="errors.password" class="field-error">{{ errors.password }}</p><p v-else class="field-hint">At least 10 characters, different from the current one.</p></div>
      <div class="field"><label for="confirm-password">Confirm new password</label><input id="confirm-password" v-model="form.password_confirmation" type="password" autocomplete="new-password" :aria-invalid="!!errors.password_confirmation" /><p v-if="errors.password_confirmation" class="field-error">{{ errors.password_confirmation }}</p></div>
    </form>
    <template #footer><button class="btn btn-ghost" :disabled="saving" @click="$emit('close')">Cancel</button><button class="btn btn-primary" form="change-password-form" type="submit" :disabled="saving"><LoaderCircle v-if="saving" class="spinner" aria-hidden="true" />{{ saving ? 'Saving…' : 'Change password' }}</button></template>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import api from '@/services/api'
import { toast } from '@/stores/toast'
import { errorMessage, fieldErrors } from '@/utils/tasks'
const props = defineProps<{ isOpen: boolean }>(), emit = defineEmits(['close'])
const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const saving = ref(false), error = ref(''), errors = ref<Record<string, string>>({})
watch(() => props.isOpen, open => { if (open) { Object.assign(form, { current_password: '', password: '', password_confirmation: '' }); error.value = ''; errors.value = {} } })
async function submit() {
  if (saving.value) return
  error.value = ''; errors.value = {}
  if (form.password.length < 10) { errors.value = { password: 'Use at least 10 characters.' }; return }
  if (form.password !== form.password_confirmation) { errors.value = { password_confirmation: 'The passwords do not match.' }; return }
  saving.value = true
  try { await api.post('/auth/change-password', { ...form }); toast.success('Password changed. Other devices were signed out.'); emit('close') }
  catch (err) { errors.value = fieldErrors(err); error.value = errorMessage(err) }
  finally { saving.value = false }
}
</script>
