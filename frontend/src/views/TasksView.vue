<script setup>
import { onMounted, ref } from 'vue'
import api from '../api/client'
import AppLayout from '../components/AppLayout.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const tasks = ref([])
const assignees = ref([])
const status = ref('pending_approval')
const loading = ref(true)
const error = ref('')
const actionLoading = ref({})
const assignmentLoading = ref({})
const assignmentDrafts = ref({})

async function fetchTasks() {
  loading.value = true
  const params = status.value ? { status: status.value } : {}
  const { data } = await api.get('/tasks', { params })
  tasks.value = data.data.data
  assignmentDrafts.value = Object.fromEntries(
    tasks.value.map((task) => [task.id, task.matched_user_id || ''])
  )
  loading.value = false
}

async function fetchAssignees() {
  if (!auth.isManager) return
  const { data } = await api.get('/assignees', { params: { per_page: 100 } })
  assignees.value = data.data.data
}

async function approve(task) {
  error.value = ''
  actionLoading.value[task.id] = true
  try {
    await api.post(`/tasks/${task.id}/approve`, { comment: 'Approved from approvals page' })
    await fetchTasks()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not approve task.'
  } finally {
    actionLoading.value[task.id] = false
  }
}

async function reject(task) {
  const comment = prompt('Why reject this task?')
  if (!comment) return
  actionLoading.value[task.id] = true
  await api.post(`/tasks/${task.id}/reject`, { comment })
  await fetchTasks()
  actionLoading.value[task.id] = false
}

async function sendEmail(task) {
  actionLoading.value[task.id] = true
  await api.post(`/tasks/${task.id}/send-email`)
  await fetchTasks()
  actionLoading.value[task.id] = false
}

async function updateAssignee(task) {
  error.value = ''
  assignmentLoading.value[task.id] = true
  try {
    await api.put(`/tasks/${task.id}/assignee`, {
      matched_user_id: assignmentDrafts.value[task.id] || null
    })
    await fetchTasks()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not update assignee.'
  } finally {
    assignmentLoading.value[task.id] = false
  }
}

async function autoAssign(task) {
  error.value = ''
  assignmentLoading.value[task.id] = true
  try {
    await api.post(`/tasks/${task.id}/auto-assign`)
    await fetchTasks()
  } catch (e) {
    error.value = e.response?.data?.message || 'Ollama could not assign this task.'
  } finally {
    assignmentLoading.value[task.id] = false
  }
}

function assigneeLabel(user) {
  return [user.name, user.position, user.email].filter(Boolean).join(' - ')
}

onMounted(async () => {
  await Promise.all([fetchTasks(), fetchAssignees()])
})
</script>

<template>
  <AppLayout>
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="font-bold uppercase tracking-[0.25em] text-teal-700/70">Approvals</p>
        <h1 class="mt-2 text-4xl font-black text-[#172033]">Task approvals</h1>
        <p class="mt-2 text-slate-500">Review and assign tasks before any email is sent.</p>
      </div>
      <select v-model="status" class="input max-w-xs" @change="fetchTasks">
        <option value="pending_approval">Pending approval</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="sent">Sent</option>
        <option value="">All tasks</option>
      </select>
    </div>

    <div v-if="error" class="mt-6 rounded-3xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700">
      {{ error }}
    </div>

    <div class="mt-8 grid gap-4">
      <div v-if="loading" class="card p-6 text-slate-500">Loading tasks...</div>
      <div v-for="task in tasks" :key="task.id" class="card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <StatusBadge :status="task.status" />
              <span class="badge bg-blue-50 text-blue-700">{{ task.priority }}</span>
            </div>
            <h2 class="mt-3 text-lg font-black text-slate-950">{{ task.title }}</h2>
            <p class="mt-2 text-slate-600">{{ task.description }}</p>
            <p class="mt-3 text-sm text-slate-500">
              Meeting: <RouterLink class="font-bold text-slate-700 hover:text-slate-950" :to="`/meetings/${task.meeting_id}`">{{ task.meeting?.title }}</RouterLink>
              - Assignee: <b>{{ task.assignee_name || 'Unknown' }}</b>
              <span v-if="task.assignee_email"> - {{ task.assignee_email }}</span>
              <span v-if="task.matched_user?.position"> - {{ task.matched_user.position }}</span>
            </p>
            <div v-if="auth.isManager && task.status === 'pending_approval'" class="mt-4 grid gap-2 rounded-2xl border border-[#dbe7f3] bg-[#f7fbff] p-3 sm:max-w-xl sm:grid-cols-[1fr_auto]">
              <label class="sr-only" :for="`assignee-${task.id}`">Assignee</label>
              <select :id="`assignee-${task.id}`" v-model="assignmentDrafts[task.id]" class="input bg-white">
                <option value="">Choose assignee before approval</option>
                <option v-for="user in assignees" :key="user.id" :value="user.id">
                  {{ assigneeLabel(user) }}
                </option>
              </select>
              <button class="btn-secondary" :disabled="assignmentLoading[task.id]" @click="updateAssignee(task)">
                Save assignee
              </button>
            </div>
          </div>
          <div v-if="auth.isManager" class="flex shrink-0 flex-wrap gap-2">
            <button v-if="task.status === 'pending_approval'" class="btn-secondary" :disabled="assignmentLoading[task.id]" @click="autoAssign(task)">Ollama assign</button>
            <button v-if="task.status === 'pending_approval'" class="btn-primary" :disabled="actionLoading[task.id]" @click="approve(task)">Approve</button>
            <button v-if="task.status === 'pending_approval'" class="btn-secondary" :disabled="actionLoading[task.id]" @click="reject(task)">Reject</button>
            <button v-if="task.status === 'approved'" class="btn-primary" :disabled="actionLoading[task.id]" @click="sendEmail(task)">Send email</button>
          </div>
        </div>
      </div>
      <div v-if="!loading && !tasks.length" class="card p-6 text-slate-500">No tasks found.</div>
    </div>
  </AppLayout>
</template>
