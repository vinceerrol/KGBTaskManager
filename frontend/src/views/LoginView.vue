<template>
  <div class="login-page">
    <aside class="login-story">
      <div class="row"><span class="brand-mark" aria-hidden="true">K</span><span class="brand-name">KCG <span>workspace</span></span></div>
      <div><p class="eyebrow" style="color:#b5addf">Good work starts with clarity</p><h1>Less chasing.<br />More creating.</h1><p>One place for your team's tasks, priorities and progress. Know what matters. Get it moving.</p>
        <div class="login-visual" aria-hidden="true"><div class="row" style="margin-bottom:20px"><CircleCheck style="color:#a69aff" /><strong>Your team, in sync</strong></div><div class="row" style="margin-bottom:14px"><span class="badge" style="background:#3d355a;color:#d6cbff">In progress</span><span>Campaign launch</span></div><div class="progress-track" style="background:#424760"><div class="progress-fill" style="background:#a69aff;transform:scaleX(.72)" /></div><div class="row" style="margin-top:18px;color:#bfc6df"><Users aria-hidden="true" /><span>Clear ownership. Shared momentum.</span></div></div>
      </div>
      <p class="login-story-footer small" style="font-size:12px">KCG · Task & workflow management</p>
    </aside>
    <main class="login-form-wrap">
      <div class="login-form">
        <div><p class="eyebrow">Welcome back</p><h1>Sign in to your workspace</h1><p class="page-subtitle">Your next great work is waiting.</p></div>
        <div v-if="auth.error" class="error-banner" role="alert"><AlertCircle aria-hidden="true" /><span>{{ auth.error }}</span></div>
        <form class="stack" @submit.prevent="handleLogin">
          <div class="field"><label for="login-email">Work email</label><input id="login-email" v-model="email" type="email" name="email" autocomplete="username" required placeholder="you@company.com" :aria-invalid="!!auth.validationErrors.email" :aria-describedby="auth.validationErrors.email ? 'login-email-error' : undefined" /><p v-if="auth.validationErrors.email" id="login-email-error" class="field-error">{{ auth.validationErrors.email }}</p></div>
          <div class="field"><label for="login-password">Password</label><div class="password-field"><input id="login-password" v-model="password" :type="showPassword ? 'text' : 'password'" name="password" autocomplete="current-password" required placeholder="Enter your password" /><button class="icon-btn" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword"><EyeOff v-if="showPassword" aria-hidden="true" /><Eye v-else aria-hidden="true" /></button></div></div>
          <button type="submit" class="btn btn-primary" :disabled="loading"><LoaderCircle v-if="loading" class="spinner" aria-hidden="true" /><ArrowRight v-else aria-hidden="true" />{{ loading ? 'Signing in…' : 'Sign in' }}</button>
        </form>
        <template v-if="isDevelopment">
          <hr class="form-divider" /><div class="stack"><div><h3>Explore the demo</h3><p class="small muted">Preview the workspace from each role.</p></div><div class="demo-grid"><button v-for="role in demoRoles" :key="role.value" class="btn" :disabled="loading" @click="quickLogin(role.value)"><component :is="role.icon" aria-hidden="true" /><strong>{{ role.name }}</strong><span class="small muted">{{ role.label }}</span></button></div></div>
        </template>
        <p class="small muted">Need access? Contact your workspace administrator.</p>
      </div>
    </main>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { CircleCheck, Users, Crown, User, Eye, EyeOff, ArrowRight, AlertCircle, LoaderCircle } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'
const auth = useAuthStore(), router = useRouter(), route = useRoute()
const email = ref(''), password = ref(''), showPassword = ref(false), loading = ref(false)
const isDevelopment = import.meta.env.DEV
const demoRoles = [{ value: 'ceo' as UserRole, name: 'Sophia', label: 'CEO', icon: Crown }, { value: 'team_lead' as UserRole, name: 'Anna', label: 'Team lead', icon: Users }, { value: 'employee' as UserRole, name: 'Carlo', label: 'Member', icon: User }]
onMounted(() => { auth.error = ''; auth.validationErrors = {} })
function destination() { const path = typeof route.query.redirect === 'string' ? route.query.redirect : ''; return path.startsWith('/') && !path.startsWith('//') && !path.startsWith('/login') ? path : auth.isEmployee ? '/my-tasks' : '/' }
async function handleLogin() { if (loading.value) return; loading.value = true; const success = await auth.login(email.value.trim(), password.value); loading.value = false; if (success) await router.push(destination()) }
async function quickLogin(role: UserRole) { if (loading.value) return; loading.value = true; const success = await auth.demoLogin(role); loading.value = false; if (success) await router.push(destination()) }
</script>
