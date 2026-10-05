<template>
  <div class="page">
    <div class="page-header">
      <div><p class="eyebrow">Administration</p><h1>People & teams</h1><p class="page-subtitle">Add accounts, set roles, deactivate leavers and organize teams.</p></div>
      <div class="row wrap"><button class="btn" :disabled="loading" @click="load"><RefreshCw :class="{ spinner: loading }" aria-hidden="true" />Refresh</button><button class="btn" @click="openTeam()"><Users aria-hidden="true" />New team</button><button class="btn btn-primary" @click="openAccount()"><UserPlus aria-hidden="true" />New account</button></div>
    </div>
    <div v-if="error" class="error-banner" role="alert"><CircleAlert aria-hidden="true" /><span class="grow">{{ error }}</span><button class="btn" @click="load">Try again</button></div>
    <LoadingState v-if="loading && !users.length" label="Loading people" />
    <template v-else>
      <section class="panel stack">
        <div class="section-header"><div><h2>Accounts</h2><p class="small muted" style="margin-top:5px">{{ activeCount }} active · {{ users.length - activeCount }} deactivated</p></div></div>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Accounts table. Scroll horizontally on small screens.">
          <table class="data-table"><caption class="sr-only">Workspace accounts</caption>
            <thead><tr><th scope="col">Person</th><th scope="col">Role</th><th scope="col">Teams</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <th scope="row" style="text-align:left;background:transparent;padding:16px 12px"><span class="row"><span class="avatar" aria-hidden="true">{{ initials(user.name) }}</span><span><strong>{{ user.name }}</strong><span class="muted small" style="display:block;font-weight:400;overflow-wrap:anywhere">{{ user.email }}</span></span></span></th>
                <td>{{ roleLabel(user.role) }}</td>
                <td>{{ user.teams?.map(team => team.name).join(', ') || 'No team' }}</td>
                <td><span class="badge" :class="user.is_active === false ? 'badge-danger' : 'badge-success'">{{ user.is_active === false ? 'Deactivated' : 'Active' }}</span></td>
                <td><button class="btn" :aria-label="'Edit ' + user.name" @click="openAccount(user)"><Pencil aria-hidden="true" />Edit</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <section class="panel stack">
        <div class="section-header"><div><h2>Teams</h2><p class="small muted" style="margin-top:5px">{{ teams.length }} teams</p></div></div>
        <p v-if="!teams.length" class="muted">No teams yet. Create one to start organizing work.</p>
        <div v-for="team in teams" :key="team.id" class="row wrap" style="justify-content:space-between;border-top:1px solid var(--line);padding-top:12px">
          <div><strong>{{ team.name }}</strong><p class="small muted">{{ team.description || 'No description' }} · {{ team.members_count || 0 }} members</p></div>
          <div class="row wrap"><button class="btn" :aria-label="'Edit ' + team.name" @click="openTeam(team)"><Pencil aria-hidden="true" />Edit</button><button class="btn btn-ghost" style="color:var(--danger)" :aria-label="'Delete ' + team.name" @click="removeTeam(team)"><Trash2 aria-hidden="true" />Delete</button></div>
        </div>
      </section>
    </template>

    <BaseModal :is-open="accountOpen" :title="accountForm.id ? 'Edit account' : 'New account'" :description="accountForm.id ? 'Change details, role, teams or status.' : 'Create a sign-in for a new team member.'" :busy="saving" @close="accountOpen = false">
      <form id="account-form" class="stack" novalidate @submit.prevent="saveAccount">
        <div v-if="formError" class="error-banner" role="alert">{{ formError }}</div>
        <div class="field"><label for="account-name">Full name</label><input id="account-name" v-model="accountForm.name" autofocus maxlength="255" :aria-invalid="!!formErrors.name" /><p v-if="formErrors.name" class="field-error">{{ formErrors.name }}</p></div>
        <div class="field"><label for="account-email">Email</label><input id="account-email" v-model="accountForm.email" type="email" autocomplete="off" :aria-invalid="!!formErrors.email" /><p v-if="formErrors.email" class="field-error">{{ formErrors.email }}</p></div>
        <div class="form-grid">
          <div class="field"><label for="account-role">Role</label><select id="account-role" v-model="accountForm.role"><option value="employee">Team member</option><option value="team_lead">Team lead</option><option value="ceo">CEO / administrator</option></select></div>
          <div class="field"><label for="account-password">{{ accountForm.id ? 'New password' : 'Password' }} <span v-if="accountForm.id" class="small muted">Leave blank to keep</span></label><input id="account-password" v-model="accountForm.password" type="password" autocomplete="new-password" minlength="10" :aria-invalid="!!formErrors.password" /><p v-if="formErrors.password" class="field-error">{{ formErrors.password }}</p><p v-else class="field-hint">At least 10 characters. Share it privately; the person can change it after signing in.</p></div>
        </div>
        <fieldset class="stack" style="border:0;padding:0;margin:0"><legend style="font-weight:600;font-size:13px;margin-bottom:8px">Teams</legend><div class="form-grid"><label v-for="team in teams" :key="team.id" class="member-choice"><input v-model="accountForm.team_ids" type="checkbox" :value="team.id" /><span>{{ team.name }}</span></label></div><p v-if="!teams.length" class="small muted">No teams yet.</p></fieldset>
        <label v-if="accountForm.id" class="member-choice"><input v-model="accountForm.is_active" type="checkbox" /><span>Account is active (deactivating signs the person out everywhere)</span></label>
      </form>
      <template #footer><button class="btn btn-ghost" :disabled="saving" @click="accountOpen = false">Cancel</button><button class="btn btn-primary" form="account-form" type="submit" :disabled="saving"><LoaderCircle v-if="saving" class="spinner" aria-hidden="true" />{{ saving ? 'Saving…' : accountForm.id ? 'Save changes' : 'Create account' }}</button></template>
    </BaseModal>

    <BaseModal :is-open="teamOpen" :title="teamForm.id ? 'Edit team' : 'New team'" description="Name the team and choose its members." :busy="saving" @close="teamOpen = false">
      <form id="team-form" class="stack" novalidate @submit.prevent="saveTeam">
        <div v-if="formError" class="error-banner" role="alert">{{ formError }}</div>
        <div class="field"><label for="team-name">Team name</label><input id="team-name" v-model="teamForm.name" autofocus maxlength="255" :aria-invalid="!!formErrors.name" /><p v-if="formErrors.name" class="field-error">{{ formErrors.name }}</p></div>
        <div class="field"><label for="team-description">Description <span class="small muted">Optional</span></label><textarea id="team-description" v-model="teamForm.description" rows="3" /></div>
        <fieldset class="stack" style="border:0;padding:0;margin:0"><legend style="font-weight:600;font-size:13px;margin-bottom:8px">Members</legend><p v-if="teamLoading" role="status" class="small muted">Loading members…</p><div v-else class="form-grid"><label v-for="user in activeUsers" :key="user.id" class="member-choice"><input v-model="teamForm.member_ids" type="checkbox" :value="user.id" /><span>{{ user.name }}</span></label></div></fieldset>
      </form>
      <template #footer><button class="btn btn-ghost" :disabled="saving" @click="teamOpen = false">Cancel</button><button class="btn btn-primary" form="team-form" type="submit" :disabled="saving || teamLoading"><LoaderCircle v-if="saving" class="spinner" aria-hidden="true" />{{ saving ? 'Saving…' : teamForm.id ? 'Save changes' : 'Create team' }}</button></template>
    </BaseModal>
  </div>
</template>
<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { RefreshCw, CircleAlert, Users, UserPlus, Pencil, Trash2, LoaderCircle } from 'lucide-vue-next'
import api from '@/services/api'
import { toast } from '@/stores/toast'
import { useTeamStore } from '@/stores/teams'
import { errorMessage, fieldErrors, initials } from '@/utils/tasks'
import BaseModal from '@/components/BaseModal.vue'
import LoadingState from '@/components/LoadingState.vue'
import type { Team, User, UserRole } from '@/types'
const teamStore = useTeamStore()
const users = ref<User[]>([]), teams = ref<Team[]>([]), loading = ref(false), error = ref(''), saving = ref(false), teamLoading = ref(false)
const accountOpen = ref(false), teamOpen = ref(false), formError = ref(''), formErrors = ref<Record<string, string>>({})
const accountForm = reactive({ id: 0, name: '', email: '', role: 'employee' as UserRole, password: '', team_ids: [] as number[], is_active: true })
const teamForm = reactive({ id: 0, name: '', description: '', member_ids: [] as number[] })
const activeCount = computed(() => users.value.filter(user => user.is_active !== false).length)
const activeUsers = computed(() => users.value.filter(user => user.is_active !== false))
let request = 0
function roleLabel(role: UserRole) { return role === 'ceo' ? 'CEO / administrator' : role === 'team_lead' ? 'Team lead' : 'Team member' }
async function load() {
  const current = ++request; loading.value = true; error.value = ''
  const results = await Promise.allSettled([api.get('/users'), api.get('/teams')])
  if (current !== request) return
  if (results[0].status === 'fulfilled') users.value = results[0].value.data.data || results[0].value.data
  else error.value = errorMessage(results[0].reason)
  if (results[1].status === 'fulfilled') teams.value = results[1].value.data.data || results[1].value.data
  else error.value = errorMessage(results[1].reason)
  loading.value = false
}
function resetErrors() { formError.value = ''; formErrors.value = {} }
function openAccount(user?: User) {
  resetErrors()
  Object.assign(accountForm, user
    ? { id: user.id, name: user.name, email: user.email, role: user.role, password: '', team_ids: (user.teams || []).map(team => team.id), is_active: user.is_active !== false }
    : { id: 0, name: '', email: '', role: 'employee', password: '', team_ids: [], is_active: true })
  accountOpen.value = true
}
async function saveAccount() {
  if (saving.value) return
  resetErrors()
  if (!accountForm.id && accountForm.password.length < 10) { formErrors.value = { password: 'Use at least 10 characters.' }; formError.value = 'Check the highlighted fields.'; return }
  saving.value = true
  try {
    const body: Record<string, unknown> = { name: accountForm.name.trim(), email: accountForm.email.trim(), role: accountForm.role, team_ids: accountForm.team_ids }
    if (accountForm.password) body.password = accountForm.password
    if (accountForm.id) { body.is_active = accountForm.is_active; await api.put('/users/' + accountForm.id, body) } else await api.post('/users', body)
    toast.success(accountForm.id ? 'Account updated.' : 'Account created.')
    accountOpen.value = false
    await Promise.all([load(), teamStore.fetchTeams()])
  } catch (err) { formErrors.value = fieldErrors(err); formError.value = errorMessage(err) }
  finally { saving.value = false }
}
async function openTeam(team?: Team) {
  resetErrors()
  Object.assign(teamForm, { id: team?.id || 0, name: team?.name || '', description: team?.description || '', member_ids: [] })
  teamOpen.value = true
  if (!team) return
  teamLoading.value = true
  try { const res = await api.get('/teams/' + team.id); teamForm.member_ids = ((res.data.data || res.data).members || []).map((member: User) => member.id) }
  catch (err) { formError.value = errorMessage(err) }
  finally { teamLoading.value = false }
}
async function saveTeam() {
  if (saving.value || teamLoading.value) return
  resetErrors()
  if (!teamForm.name.trim()) { formErrors.value = { name: 'Enter a team name.' }; formError.value = 'Check the highlighted fields.'; return }
  saving.value = true
  try {
    const body = { name: teamForm.name.trim(), description: teamForm.description || null, member_ids: teamForm.member_ids }
    if (teamForm.id) await api.put('/teams/' + teamForm.id, body); else await api.post('/teams', body)
    toast.success(teamForm.id ? 'Team updated.' : 'Team created.')
    teamOpen.value = false
    await Promise.all([load(), teamStore.fetchTeams()])
  } catch (err) { formErrors.value = fieldErrors(err); formError.value = errorMessage(err) }
  finally { saving.value = false }
}
async function removeTeam(team: Team) {
  if (!window.confirm('Delete the team "' + team.name + '"? Members keep their accounts. Teams with open tasks cannot be deleted.')) return
  try { await api.delete('/teams/' + team.id); toast.success('Team deleted.'); await Promise.all([load(), teamStore.fetchTeams()]) }
  catch (err) { toast.error(errorMessage(err)) }
}
onMounted(load)
</script>
