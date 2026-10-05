<template>
  <div class="toast-container" aria-label="Workspace messages">
    <TransitionGroup name="toast">
      <div v-for="item in toasts" :key="item.id" class="toast-item" @mouseenter="toast.pause(item.id)" @mouseleave="toast.resume(item.id)" @focusin="toast.pause(item.id)" @focusout="toast.resume(item.id)">
        <component :is="item.type === 'success' ? CheckCircle2 : item.type === 'error' ? AlertCircle : Info" :class="item.type === 'error' ? 'danger-text' : 'success-text'" aria-hidden="true" />
        <div class="grow" :role="item.type === 'error' ? 'alert' : 'status'" aria-atomic="true"><strong v-if="item.title">{{ item.title }}</strong><p>{{ item.message }}</p></div>
        <button v-if="item.action" class="btn btn-soft" @click="runAction(item)"> {{ item.actionLabel }} </button>
        <button class="icon-btn" aria-label="Dismiss message" @click="removeToast(item.id)"><X aria-hidden="true" /></button>
      </div>
    </TransitionGroup>
  </div>
</template>
<script setup lang="ts">
import { CheckCircle2, AlertCircle, Info, X } from 'lucide-vue-next'
import { useToast, type ToastItem } from '@/stores/toast'
const { toasts, toast, removeToast } = useToast()
function runAction(item: ToastItem) { item.action?.(); removeToast(item.id) }
</script>
