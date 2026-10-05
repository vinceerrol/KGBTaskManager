<template>
  <nav class="mobile-nav" aria-label="Mobile navigation">
    <RouterLink to="/" :class="{ active: route.path === '/' }" :aria-current="route.path === '/' ? 'page' : undefined"><LayoutDashboard aria-hidden="true" />Overview</RouterLink>
    <RouterLink to="/my-tasks" :class="{ active: route.path === '/my-tasks' }" :aria-current="route.path === '/my-tasks' ? 'page' : undefined"><CircleCheck aria-hidden="true" />My work</RouterLink>
    <button v-if="!auth.isEmployee" class="mobile-create" aria-label="Create task" @click="$emit('open-create-task')"><Plus aria-hidden="true" /></button>
    <RouterLink to="/tasks" :class="{ active: route.path.startsWith('/tasks') }" :aria-current="route.path.startsWith('/tasks') ? 'page' : undefined"><ListTodo aria-hidden="true" />Tasks</RouterLink>
    <button @click="$emit('open-more')" aria-label="More workspace pages"><Ellipsis aria-hidden="true" />More</button>
  </nav>
</template>
<script setup lang="ts">
import { useRoute } from 'vue-router'
import { LayoutDashboard, CircleCheck, Plus, ListTodo, Ellipsis } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
defineEmits(['open-create-task', 'open-more'])
const route = useRoute(), auth = useAuthStore()
</script>
