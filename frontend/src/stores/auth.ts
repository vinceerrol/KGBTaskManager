import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import type { User, UserRole } from '@/types'
import { toast } from '@/stores/toast'
import { errorMessage, fieldErrors } from '@/utils/tasks'

function getInitialUser(): User | null {
  try {
    const saved = localStorage.getItem('kcg_auth_user')
    return saved ? JSON.parse(saved) : null
  } catch (e) {
    return null
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('kcg_auth_token'))
  const user = ref<User | null>(getInitialUser())
  const error = ref('')
  const validationErrors = ref<Record<string, string>>({})

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isCeo = computed(() => user.value?.role === 'ceo')
  const isTeamLead = computed(() => user.value?.role === 'team_lead')
  const isEmployee = computed(() => user.value?.role === 'employee')

  function setAuth(newToken: string, newUser: User) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('kcg_auth_token', newToken)
    localStorage.setItem('kcg_auth_user', JSON.stringify(newUser))
  }

  function clearAuth() {
    token.value = null
    user.value = null
    localStorage.removeItem('kcg_auth_token')
    localStorage.removeItem('kcg_auth_user')
  }

  async function login(email: string, password: string): Promise<boolean> {
    error.value = ''; validationErrors.value = {}
    try {
      const res = await api.post('/auth/login', { email, password })
      setAuth(res.data.token, res.data.user)
      toast.success(`Welcome back, ${res.data.user.name}!`)
      return true
    } catch (err: any) {
      error.value = err.response?.status === 422 ? 'Check your email and password, then try again.' : errorMessage(err)
      validationErrors.value = fieldErrors(err)
      return false
    }
  }

  async function demoLogin(role: UserRole): Promise<boolean> {
    error.value = ''; validationErrors.value = {}
    try {
      const res = await api.post('/auth/demo-login', { role })
      setAuth(res.data.token, res.data.user)
      toast.success(`Switched to demo role: ${res.data.user.name} (${res.data.user.role.toUpperCase()})`)
      return true
    } catch (err: any) {
      error.value = errorMessage(err)
      return false
    }
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      const res = await api.get('/auth/me')
      user.value = res.data.user
      localStorage.setItem('kcg_auth_user', JSON.stringify(res.data.user))
    } catch (err: any) {
      if (err.response?.status === 401) clearAuth()
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch (e) {
      // ignore
    } finally {
      clearAuth()
      window.location.href = '/login'
    }
  }

  return {
    error,
    validationErrors,
    token,
    user,
    isAuthenticated,
    isCeo,
    isTeamLead,
    isEmployee,
    login,
    demoLogin,
    logout,
    fetchUser,
    setAuth,
    clearAuth
  }
})
