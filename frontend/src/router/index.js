import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import DashboardView from '../views/DashboardView.vue'
import MeetingDetailView from '../views/MeetingDetailView.vue'
import TasksView from '../views/TasksView.vue'
import AssigneesView from '../views/AssigneesView.vue'

const routes = [
  { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
  { path: '/', name: 'dashboard', component: DashboardView, meta: { auth: true } },
  { path: '/meetings/:id', name: 'meeting-detail', component: MeetingDetailView, meta: { auth: true } },
  { path: '/tasks', name: 'tasks', component: TasksView, meta: { auth: true } },
  { path: '/assignees', name: 'assignees', component: AssigneesView, meta: { auth: true, manager: true } }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.auth && !auth.isAuthenticated) return '/login'
  if (to.meta.manager && !auth.isManager) return '/'
  if (to.meta.guest && auth.isAuthenticated) return '/'
})

export default router
