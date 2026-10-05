import { createRouter, createWebHistory } from 'vue-router'
const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, from, savedPosition) { if (to.params.id || from.params.id) return false; return savedPosition || { top: 0 } },
  routes: [
    { path: '/', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { requiresAuth: true, title: 'Overview' } },
    { path: '/my-tasks', name: 'my-tasks', component: () => import('@/views/MyTasksView.vue'), meta: { requiresAuth: true, title: 'My work' } },
    { path: '/tasks', name: 'tasks', component: () => import('@/views/TasksListView.vue'), meta: { requiresAuth: true, title: 'All tasks' } },
    { path: '/tasks/:id', name: 'task-detail', component: () => import('@/views/TasksListView.vue'), meta: { requiresAuth: true, title: 'Task details' } },
    { path: '/teams', name: 'teams', component: () => import('@/views/TeamsView.vue'), meta: { requiresAuth: true, title: 'Teams & workload' } },
    { path: '/templates', name: 'templates', component: () => import('@/views/TemplatesView.vue'), meta: { requiresAuth: true, title: 'Workflow library' } },
    { path: '/people', name: 'people', component: () => import('@/views/PeopleView.vue'), meta: { requiresAuth: true, requiresCeo: true, title: 'People & teams' } },
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { title: 'Sign in' } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})
router.beforeEach(to => {
  const token = localStorage.getItem('kcg_auth_token')
  if (to.meta.requiresAuth && !token) return { path: '/login', query: { redirect: to.fullPath } }
  if (to.path === '/login' && token) return '/'
  // The server enforces this too; the check only keeps other roles from landing on an empty admin page.
  if (to.meta.requiresCeo) {
    try { if (JSON.parse(localStorage.getItem('kcg_auth_user') || 'null')?.role !== 'ceo') return '/' } catch { return '/' }
  }
})
export default router
