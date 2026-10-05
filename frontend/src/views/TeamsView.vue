<template>
  <div class="page">
    <div class="page-header"><div><p class="eyebrow">People / Workload</p><h1>Better together.</h1><p class="page-subtitle">See how work is distributed. Give every task a clear owner.</p></div><div class="row wrap"><button class="btn" :disabled="teams.loading" @click="refresh"><RefreshCw :class="{ spinner:teams.loading }" aria-hidden="true" />Refresh</button><button v-if="!auth.isEmployee" class="btn btn-primary" @click="$emit('open-create-task', { team_id:selectedTeamId })"><Plus aria-hidden="true" />Create task</button></div></div>
    <div v-if="teams.error" class="error-banner" role="alert"><CircleAlert aria-hidden="true" /><span class="grow">{{ teams.error }}</span><button class="btn" @click="refresh">Try again</button></div>
    <LoadingState v-if="teams.loading && !teams.teams.length" label="Loading teams" />
    <EmptyState v-else-if="!teams.teams.length && !teams.error" title="Bring your team together" description="Teams will appear here when they are configured in the workspace." :icon="Users" />
    <div v-else class="team-grid" aria-label="Select a team">
      <button v-for="team in teams.teams" :key="team.id" class="team-choice" :aria-pressed="selectedTeamId === team.id" @click="selectTeam(team.id)"><span class="row"><span class="team-icon"><Users aria-hidden="true" /></span><span class="grow" /><span v-if="team.overdue_count" class="badge badge-danger">{{ team.overdue_count }} overdue</span><Check v-if="selectedTeamId === team.id" style="color:var(--brand)" aria-hidden="true" /></span><span><strong style="font-size:16px">{{ team.name }}</strong><span class="muted small" style="display:block;margin-top:6px">{{ team.description || 'Your team workspace.' }}</span></span><span class="row wrap" style="padding-top:12px;border-top:1px solid var(--line)"><span class="badge">{{ team.members_count || 0 }} members</span><span class="badge badge-brand">{{ team.active_tasks_count || 0 }} active</span><span class="small muted">{{ team.due_today_count || 0 }} due today</span></span></button>
    </div>
    <LoadingState v-if="teams.detailLoading" label="Loading member workloads" :count="2" />
    <div v-else-if="teams.detailError" class="error-banner" role="alert"><span class="grow">{{ teams.detailError }}</span><button class="btn" @click="selectedTeamId && teams.fetchTeam(selectedTeamId)">Try again</button></div>
    <template v-else-if="teams.currentTeam && teams.currentTeam.id === selectedTeamId">
      <TeamWorkloadTable :members="teams.currentTeam.members || []" @assign-to="quickAssign" />
      <section class="stack"><div class="section-header"><div><h2>Work in {{ teams.currentTeam.name }}</h2><p class="small muted" style="margin-top:5px">{{ teamTasks.length }} tasks · All stages</p></div><RouterLink :to="{ path:'/tasks', query:{ team_id:selectedTeamId } }" class="text-link">Open task workspace <ArrowRight aria-hidden="true" /></RouterLink></div><EmptyState v-if="!teamTasks.length" title="A clean slate for this team" description="Create a task to turn your next goal into shared progress."><button v-if="!auth.isEmployee" class="btn btn-primary" @click="$emit('open-create-task',{ team_id:selectedTeamId })"><Plus aria-hidden="true" />Create a team task</button></EmptyState><TaskCollection v-else :tasks="teamTasks" mode="grid" @select-task="$emit('select-task',$event)" @complete-task="$emit('complete-task',$event)" @share-messenger="$emit('share-messenger',$event)" /></section>
    </template>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { RefreshCw, Plus, CircleAlert, Users, Check, ArrowRight } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useTeamStore } from '@/stores/teams'
import { useTaskStore } from '@/stores/tasks'
import { focusScore } from '@/utils/tasks'
import TeamWorkloadTable from '@/components/TeamWorkloadTable.vue'
import TaskCollection from '@/components/TaskCollection.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import type { User } from '@/types'
const emit = defineEmits(['open-create-task','select-task','complete-task','share-messenger'])
const auth = useAuthStore(), teams = useTeamStore(), tasks = useTaskStore(), route = useRoute(), router = useRouter()
const selectedTeamId = ref<number | null>(null)
const teamTasks = computed(() => {
  const source = tasks.tasks.length ? tasks.tasks.filter(task => task.team_id === selectedTeamId.value) : teams.currentTeam?.tasks || []
  return [...source].map(task => ({ ...task, team:task.team || teams.currentTeam })).sort((a,b) => focusScore(b) - focusScore(a))
})
async function selectTeam(id:number) { selectedTeamId.value = id; if (String(route.query.team) !== String(id)) await router.replace({ query:{ team:String(id) } }); await teams.fetchTeam(id) }
function quickAssign(member:User) { emit('open-create-task',{ team_id:selectedTeamId.value,assigned_to:member.id }) }
async function refresh() { await Promise.allSettled([teams.fetchTeams(),tasks.fetchTasks()]); if (selectedTeamId.value) await teams.fetchTeam(selectedTeamId.value) }
watch(() => route.query.team, value => { const id = Number(value); if (id && id !== selectedTeamId.value && teams.teams.some(team => team.id === id)) { selectedTeamId.value = id; void teams.fetchTeam(id) } })
onMounted(async () => { await teams.fetchTeams(); const fromQuery = Number(route.query.team); const id = teams.teams.find(team => team.id === fromQuery)?.id || teams.teams[0]?.id; if (id) await selectTeam(id); if (!tasks.tasks.length && !tasks.loading) void tasks.fetchTasks() })
</script>
