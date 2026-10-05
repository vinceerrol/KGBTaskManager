import { defineStore } from 'pinia'
import { ref, watchEffect } from 'vue'

function read<T>(key: string, fallback: T): T {
  try { return JSON.parse(localStorage.getItem(key) || 'null') ?? fallback } catch { return fallback }
}
export const usePreferencesStore = defineStore('preferences', () => {
  const theme = ref<'light' | 'dark' | 'system'>(read('kcg_theme', 'light'))
  const density = ref<'comfortable' | 'compact'>(read('kcg_density', 'comfortable'))
  const motion = ref<'system' | 'reduced'>(read('kcg_motion', 'system'))
  const taskView = ref<'grid' | 'list' | 'board'>(read('kcg_task_view', 'grid'))
  const singleKeyShortcuts = ref<boolean>(read('kcg_single_key_shortcuts', true))
  const systemDark = ref(window.matchMedia('(prefers-color-scheme: dark)').matches)
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => { systemDark.value = event.matches })
  watchEffect(() => {
    document.documentElement.dataset.theme = theme.value === 'system' ? (systemDark.value ? 'dark' : 'light') : theme.value
    document.documentElement.dataset.density = density.value
    document.documentElement.dataset.motion = motion.value
    try {
      localStorage.setItem('kcg_theme', JSON.stringify(theme.value))
      localStorage.setItem('kcg_density', JSON.stringify(density.value))
      localStorage.setItem('kcg_motion', JSON.stringify(motion.value))
      localStorage.setItem('kcg_task_view', JSON.stringify(taskView.value))
      localStorage.setItem('kcg_single_key_shortcuts', JSON.stringify(singleKeyShortcuts.value))
    } catch { /* Keep preferences usable if browser storage is unavailable. */ }
  })
  return { theme, density, motion, taskView, singleKeyShortcuts }
})
