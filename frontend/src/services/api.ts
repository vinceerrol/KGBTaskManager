import axios from 'axios'
import { toast } from '@/stores/toast'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Request interceptor to attach token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('kcg_auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor to handle errors gracefully
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status

    if (status === 401) {
      localStorage.removeItem('kcg_auth_token')
      localStorage.removeItem('kcg_auth_user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search)
      }
    } else if (status === 403) {
      toast.error('You do not have permission to perform this action.')
    } else if (status === 422) {
      // The submitting form retains and announces field errors in context.
    } else if (status >= 500) {
      toast.error('Server error. Please try again in a moment.')
    }

    return Promise.reject(error)
  }
)

export default api
