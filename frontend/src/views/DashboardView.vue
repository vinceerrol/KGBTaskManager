<template>
  <div class="page">
    <div class="page-header"><div><p class="eyebrow">{{ today }}</p><h1>{{ greeting }}, {{ firstName }}<span style="color:var(--brand)">.</span></h1><p class="page-subtitle">{{ auth.isEmployee ? 'A clear view of your work and your team’s momentum.' : 'Here’s what’s moving across your workspace today.' }}</p></div><div class="row wrap"><button class="btn" :disabled="refreshing" @click="refresh"><RefreshCw :class="{ spinner: refreshing }" aria-hidden="true" /><span>Refresh</span></button><button v-if="!auth.isEmployee" class="btn" @click="$emit('open-write-task')"><FilePenLine aria-hidden="true" />Write a task</button><button v-if="!auth.isEmployee" class="btn btn-primary" @click="$emit('open-create-task')"><Plus aria-hidden="true" />Create task</button></div></div>
    <div v-if="tasks.error || tasks.statsError" class="error-banner" role="alert"><CircleAlert aria-hidden="true" /><span class="grow">{{ tasks.error || tasks.statsError }}</span><button class="btn" @click="refresh">Try again</button></div>
    <div class="stats-grid" :aria-busy="tasks.statsLoading">
      <button v-for="stat in statistics" :key="stat.label" class="stat-card" :class="stat.tone" @click="router.push({ path:'/tasks',query:stat.query })">
        <span class="stat-top">{{ stat.label }}<component :is="stat.icon" aria-hidden="true" /></span><strong class="stat-value">{{ tasks.statsLoading && !tasks.stats ? '—' : stat.value }}</strong><span class="stat-bottom">{{ stat.caption }}<ArrowUpRight style="width:14px;height:14px;margin-left:auto" aria-hidden="true" /></span>
      </button>
    </div>
    <LoadingState v-if="tasks.loading && !tasks.tasks.length" label="Loading your overview" :count="2" />
    <div v-else class="overview-grid">
      <AttentionNeeded @select-task="$emit('select-task',$event)" />
      <section class="panel stack">
        <div class="section-header"><div><h2>Team momentum</h2><p class="small muted" style="margin-top:5px">Active tasks per team</p></div><RouterLink to="/teams" class="icon-btn" aria-label="View team workloads"><ArrowUpRight aria-hidden="true" /></RouterLink></div>
        <p v-if="teams.error" class="field-error" role="alert">{{ teams.error }} <button class="text-link" @click="teams.fetchTeams()">Retry</button></p>
        <div v-for="team in teams.teams" :key="team.id" class="team-row"><RouterLink :to="{ path:'/teams',query:{ team:team.id } }" class="row grow"><span class="team-icon"><component :is="teamIcon(team.name)" aria-hidden="true" /></span><span class="truncate"><strong>{{ team.name }}</strong><span class="muted small" style="display:block">{{ team.members_count || 0 }} members<span v-if="team.overdue_count" class="danger-text"> · {{ team.overdue_count }} overdue</span></span></span></RouterLink><div class="progress-track" :aria-label="team.name + ': ' + (team.active_tasks_count || 0) + ' active tasks'"><div class="progress-fill" :style="{ transform:'scaleX(' + (team.active_tasks_count || 0) / maxTeamLoad + ')' }" /></div><span class="small muted" style="text-align:right">{{ team.active_tasks_count || 0 }} active</span></div>
        <p v-if="!teams.teams.length && !teams.loading" class="small muted">Your teams will appear here when they are added.</p>
      </section>
    </div>
    <section class="stack">
      <div class="section-header"><div><h2>Work in motion</h2><p class="small muted" style="margin-top:5px">Prioritized by overdue work, urgency and upcoming deadlines.</p></div><RouterLink to="/tasks" class="text-link">View all tasks <ArrowRight aria-hidden="true" /></RouterLink></div>
      <LoadingState v-if="tasks.loading && !tasks.tasks.length" />
      <EmptyState v-else-if="!activeTasks.length && !tasks.error" title="Ready for your next chapter" description="There are no active tasks. Start with a clear outcome and an owner." :icon="CircleCheck"><button v-if="!auth.isEmployee" class="btn btn-primary" @click="$emit('open-create-task')"><Plus aria-hidden="true" />Create your first task</button><RouterLink v-else to="/my-tasks" class="btn">Go to my work</RouterLink></EmptyState>
      <div v-else class="task-grid"><TaskCard v-for="task in activeTasks.slice(0,6)" :key="task.id" :task="task" @select="$emit('select-task',$event)" @complete="$emit('complete-task',$event)" @share-messenger="$emit('share-messenger',$event)" @start="tasks.startTask" @reopen="tasks.reopenTask" /></div>
    </section>
    <p class="small muted row"><Clock3 aria-hidden="true" />{{ lastRefreshed ? 'Updated ' + lastRefreshed + ' · ' : '' }}Workspace time: Asia/Manila (UTC+8)</p>
  </div>
</template>
<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, FilePenLine, RefreshCw, CircleAlert, CalendarDays, CircleDot, Clock3, CircleCheck, ArrowUpRight, ArrowRight, Video, Megaphone, Code2, Palette, Workflow, Users } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useTaskStore } from '@/stores/tasks'
import { useTeamStore } from '@/stores/teams'
import { focusScore } from '@/utils/tasks'
import AttentionNeeded from '@/components/AttentionNeeded.vue'
import TaskCard from '@/components/TaskCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
defineEmits(['open-create-task','open-write-task','select-task','complete-task','share-messenger'])
const auth = useAuthStore(), tasks = useTaskStore(), teams = useTeamStore(), router = useRouter()
const refreshing = ref(false), lastRefreshed = ref('')
const firstName = computed(() => auth.user?.name.split(' ')[0] || 'there')
const today = new Date().toLocaleDateString('en',{ timeZone:'Asia/Manila',weekday:'long',month:'long',day:'numeric' })
const hour = Number(new Date().toLocaleTimeString('en-GB',{ timeZone:'Asia/Manila',hour:'2-digit',hour12:false }))
const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening'
const statistics = computed(() => [
  { label:'Tasks today',value:tasks.stats?.tasks_today || 0,caption:'Due, scheduled or created',icon:CalendarDays,tone:'',query:{ date_filter:'today' } },
  { label:'In progress',value:tasks.stats?.in_progress || 0,caption:'Work underway',icon:CircleDot,tone:'',query:{ status:'IN PROGRESS' } },
  { label:'Scheduled',value:tasks.stats?.scheduled || 0,caption:'Coming up next',icon:Clock3,tone:'',query:{ status:'SCHEDULED' } },
  { label:'Overdue',value:tasks.stats?.overdue || 0,caption:'Needs a follow-up',icon:CircleAlert,tone:'danger',query:{ status:'OVERDUE' } },
  { label:'Completed',value:tasks.stats?.completed || 0,caption:'Finished across all time',icon:CircleCheck,tone:'success',query:{ status:'DONE' } },
])
const activeTasks = computed(() => tasks.tasks.filter(task => task.status !== 'DONE').sort((a,b) => focusScore(b) - focusScore(a)))
const maxTeamLoad = computed(() => Math.max(1,...teams.teams.map(team => team.active_tasks_count || 0)))
function teamIcon(name: string) { return name.includes('Video') ? Video : name.includes('Marketing') ? Megaphone : name.includes('Development') ? Code2 : name.includes('Design') ? Palette : name.includes('Automation') ? Workflow : Users }
async function refresh() { if (refreshing.value) return; refreshing.value = true; await Promise.allSettled([tasks.fetchTasks(),tasks.fetchDashboardStats(),teams.fetchTeams()]); refreshing.value = false; if (!tasks.error && !tasks.statsError) lastRefreshed.value = new Date().toLocaleTimeString('en',{ timeZone:'Asia/Manila',hour:'numeric',minute:'2-digit' }) }
onMounted(() => { if (!tasks.tasks.length && !tasks.loading) void refresh() })
</script>
