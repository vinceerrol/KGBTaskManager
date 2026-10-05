<template>
  <section class="panel stack">
    <div class="section-header"><div><h2>Who has what on their plate?</h2><p class="small muted" style="margin-top:5px">Active counts include work in progress for this team.</p></div><span class="badge badge-brand">{{ members.length }} members</span></div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Member workload table. Scroll horizontally on small screens.">
      <table class="data-table"><caption class="sr-only">Member workload in the selected team</caption><thead><tr><th scope="col">Team member</th><th scope="col">In progress</th><th scope="col">Due today</th><th scope="col">Overdue</th><th v-if="!auth.isEmployee" scope="col">Assign work</th></tr></thead><tbody>
        <tr v-for="member in orderedMembers" :key="member.id"><th scope="row" style="text-align:left;background:transparent;padding:16px 12px"><span class="row"><span class="avatar" aria-hidden="true">{{ initials(member.name) }}</span><span><strong>{{ member.name }}</strong><span class="muted small" style="display:block;font-weight:400">{{ member.role.replace('_',' ') }}</span></span></span></th><td><div class="row"><strong>{{ member.active_tasks_count || 0 }}</strong><div class="progress-track" style="width:64px" aria-hidden="true"><div class="progress-fill" :style="{ transform:'scaleX(' + (member.active_tasks_count || 0) / maximum + ')' }" /></div></div></td><td>{{ member.due_today_count || 0 }}</td><td><span class="badge" :class="member.overdue_count ? 'badge-danger' : 'badge-success'">{{ member.overdue_count || 0 }}</span></td><td v-if="!auth.isEmployee"><button class="btn btn-soft" :aria-label="'Assign a task to ' + member.name" @click="$emit('assign-to',member)"><Plus aria-hidden="true" />Assign task</button></td></tr>
      </tbody></table>
    </div>
    <p v-if="!members.length" class="muted">No members have been added to this team.</p>
    <p class="small muted">Task counts describe activity; they don’t measure individual capacity or performance.</p>
  </section>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { Plus } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { initials } from '@/utils/tasks'
import type { User } from '@/types'
const props = defineProps<{ members: User[] }>(); defineEmits(['assign-to'])
const auth = useAuthStore()
const orderedMembers = computed(() => [...props.members].sort((a,b) => (a.active_tasks_count || 0) - (b.active_tasks_count || 0)))
const maximum = computed(() => Math.max(1,...props.members.map(member => member.active_tasks_count || 0)))
</script>
