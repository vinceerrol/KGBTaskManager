<template>
  <Teleport to="body">
    <dialog ref="dialog" class="modal" :class="[{ wide }, dialogClass]" :style="dialogStyle" :aria-labelledby="titleId" @cancel.prevent="requestClose" @click="handleBackdrop" @keydown="containFocus">
      <div class="modal-header">
        <div class="grow"><h2 :id="titleId" tabindex="-1">{{ title }}</h2><p v-if="description">{{ description }}</p></div>
        <button class="icon-btn" type="button" :aria-label="`Close ${title}`" :disabled="busy" @click="requestClose"><X aria-hidden="true" /></button>
      </div>
      <div class="modal-body"><slot /></div>
      <div v-if="$slots.footer" class="modal-footer"><slot name="footer" /></div>
    </dialog>
  </Teleport>
</template>
<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, useId } from 'vue'
import { X } from 'lucide-vue-next'
import { lockDialog, unlockDialog } from '@/utils/dialog'
const props = defineProps<{ isOpen: boolean; title: string; description?: string; wide?: boolean; busy?: boolean; dialogClass?: string; dialogStyle?: Record<string, string> }>()
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement | null>(null)
const titleId = `dialog-${useId()}`
let trigger: HTMLElement | null = null
watch(() => props.isOpen, async open => {
  await nextTick()
  if (!dialog.value || props.isOpen !== open) return
  if (open && !dialog.value.open) {
    trigger = document.activeElement as HTMLElement
    dialog.value.showModal()
    lockDialog(dialog.value)
    const initial = dialog.value.querySelector<HTMLElement>('[autofocus]') || dialog.value.querySelector<HTMLElement>(`#${CSS.escape(titleId)}`)
    initial?.focus({ preventScroll: true })
    const body = dialog.value.querySelector<HTMLElement>('.modal-body')
    if (body) body.scrollTop = 0
  } else if (!open && dialog.value.open) {
    dialog.value.close()
    unlockDialog(dialog.value)
    if (trigger?.isConnected && !trigger.closest('[inert]')) trigger.focus({ preventScroll: true })
  }
}, { immediate: true })
function requestClose() { if (!props.busy) emit('close') }
function containFocus(event: KeyboardEvent) {
  if (event.key !== 'Tab' || !dialog.value) return
  const controls = Array.from(dialog.value.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])')).filter(element => !element.matches(':disabled') && element.tabIndex >= 0 && element.getClientRects().length)
  const first = controls[0], last = controls[controls.length - 1]
  if (!first || !last) { event.preventDefault(); return }
  const active = document.activeElement as HTMLElement
  if (event.shiftKey && (active === first || !controls.includes(active))) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && (active === last || !controls.includes(active))) { event.preventDefault(); first.focus() }
}
function handleBackdrop(event: MouseEvent) {
  const element = dialog.value
  if (!element || event.target !== element) return
  const rect = element.getBoundingClientRect()
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) requestClose()
}
onBeforeUnmount(() => { if (dialog.value) { dialog.value.close(); unlockDialog(dialog.value) } })
</script>
