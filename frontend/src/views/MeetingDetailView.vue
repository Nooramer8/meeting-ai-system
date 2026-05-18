<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api/client'
import AppLayout from '../components/AppLayout.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const auth = useAuthStore()
const meeting = ref(null)
const assignees = ref([])
const loading = ref(true)
const actionLoading = ref({})
const assignmentLoading = ref({})
const assignmentDrafts = ref({})
const error = ref('')

const isArabic = computed(() => {
  const language = meeting.value?.minute?.raw_ai_output?.language || meeting.value?.language
  return language === 'Arabic' || language === 'ar'
})
const contentDir = computed(() => (isArabic.value ? 'rtl' : 'ltr'))
const contentAlign = computed(() => (isArabic.value ? 'text-right' : 'text-left'))
const keyPointsTitle = computed(() => (isArabic.value ? 'النقاط الرئيسية' : 'Key points'))
const decisionsTitle = computed(() => (isArabic.value ? 'القرارات' : 'Decisions'))
const risksTitle = computed(() => (isArabic.value ? 'تنبيهات للمراجعة' : 'Risks / blockers'))

async function fetchMeeting() {
  loading.value = true
  const { data } = await api.get(`/meetings/${route.params.id}`)
  meeting.value = data.data
  assignmentDrafts.value = Object.fromEntries(
    (meeting.value.tasks || []).map((task) => [task.id, task.matched_user_id || ''])
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
    await api.post(`/tasks/${task.id}/approve`, { comment: 'Approved from dashboard' })
    await fetchMeeting()
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
  await fetchMeeting()
  actionLoading.value[task.id] = false
}

async function sendEmail(task) {
  actionLoading.value[task.id] = true
  try {
    await api.post(`/tasks/${task.id}/send-email`)
    await fetchMeeting()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not queue email.'
  } finally {
    actionLoading.value[task.id] = false
  }
}

async function updateAssignee(task) {
  error.value = ''
  assignmentLoading.value[task.id] = true
  try {
    await api.put(`/tasks/${task.id}/assignee`, {
      matched_user_id: assignmentDrafts.value[task.id] || null
    })
    await fetchMeeting()
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
    await fetchMeeting()
  } catch (e) {
    error.value = e.response?.data?.message || 'Ollama could not assign this task.'
  } finally {
    assignmentLoading.value[task.id] = false
  }
}

async function reprocess() {
  await api.post(`/meetings/${meeting.value.id}/reprocess`)
  await fetchMeeting()
}

function assigneeLabel(user) {
  return [user.name, user.position, user.email].filter(Boolean).join(' - ')
}

onMounted(async () => {
  await Promise.all([fetchMeeting(), fetchAssignees()])
})
</script>

<template>
  <AppLayout>
    <div v-if="loading" class="card p-6 text-slate-500">Loading meeting...</div>
    <div v-else-if="meeting" class="space-y-8">
      <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <RouterLink to="/" class="text-sm font-bold text-slate-500 hover:text-slate-950">Back to dashboard</RouterLink>
          <h1 class="mt-3 text-4xl font-black text-slate-950">{{ meeting.title }}</h1>
          <p class="mt-2 text-slate-500">Uploaded by {{ meeting.uploader?.name }} - {{ new Date(meeting.created_at).toLocaleString() }}</p>
        </div>
        <div class="flex items-center gap-3">
          <StatusBadge :status="meeting.status" />
          <button v-if="auth.isManager" class="btn-secondary" @click="reprocess">Reprocess</button>
        </div>
      </div>

      <div v-if="meeting.failure_reason" class="rounded-3xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700">
        {{ meeting.failure_reason }}
      </div>
      <div v-if="error" class="rounded-3xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700">
        {{ error }}
      </div>

      <section class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
          <h2 class="text-2xl font-black text-slate-950">Speech to text from Groq</h2>
          <div class="mt-4 max-h-[520px] overflow-auto rounded-3xl bg-slate-50 p-5 text-sm leading-7 text-slate-700" dir="auto">
            <p v-if="meeting.transcript" class="whitespace-pre-wrap">{{ meeting.transcript }}</p>
            <p v-else class="text-slate-500">Transcript is not ready yet.</p>
          </div>
        </div>

        <div class="card p-6" :dir="contentDir" :class="contentAlign">
          <h2 class="text-2xl font-black text-slate-950" dir="ltr">Summary from Groq</h2>

          <h2 v-if="meeting.minute?.raw_ai_output?.title" class="mt-6 text-2xl font-black text-slate-950">
            {{ meeting.minute.raw_ai_output.title }}
          </h2>
          <h2 v-else class="mt-6 text-xl font-black text-slate-950">Meeting minutes</h2>

          <p v-if="meeting.minute?.summary" class="mt-4 whitespace-pre-wrap leading-8 text-slate-700">{{ meeting.minute.summary }}</p>
          <p v-else class="mt-4 text-slate-500">Minutes are not ready yet.</p>

          <div v-if="meeting.minute?.raw_ai_output?.key_points?.length" class="mt-6">
            <h3 class="font-black text-slate-950">{{ keyPointsTitle }}</h3>
            <ul class="mt-2 list-inside list-disc space-y-1 text-slate-700">
              <li v-for="point in meeting.minute.raw_ai_output.key_points" :key="point">{{ point }}</li>
            </ul>
          </div>

          <div v-if="meeting.minute?.decisions?.length" class="mt-6">
            <h3 class="font-black text-slate-950">{{ decisionsTitle }}</h3>
            <ul class="mt-2 list-inside list-disc space-y-1 text-slate-700">
              <li v-for="decision in meeting.minute.decisions" :key="decision">{{ decision }}</li>
            </ul>
          </div>

          <div v-if="meeting.minute?.risks?.length" class="mt-6">
            <h3 class="font-black text-slate-950">{{ risksTitle }}</h3>
            <ul class="mt-2 list-inside list-disc space-y-1 text-slate-700">
              <li v-for="risk in meeting.minute.risks" :key="risk">{{ risk }}</li>
            </ul>
          </div>
        </div>
      </section>

      <section>
        <h2 class="text-2xl font-black text-slate-950">Tasks requiring approval</h2>
        <div class="mt-4 grid gap-4">
          <div v-for="task in meeting.tasks" :key="task.id" class="card p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <StatusBadge :status="task.status" />
                  <span class="badge bg-blue-50 text-blue-700">{{ task.priority }}</span>
                </div>
                <h3 class="mt-3 text-lg font-black text-slate-950">{{ task.title }}</h3>
                <p class="mt-2 whitespace-pre-wrap text-slate-600">{{ task.description }}</p>
                <p class="mt-3 text-sm text-slate-500">
                  Assignee: <b>{{ task.assignee_name || 'Unknown' }}</b>
                  <span v-if="task.assignee_email"> - {{ task.assignee_email }}</span>
                  <span v-if="task.matched_user?.position"> - {{ task.matched_user.position }}</span>
                  <span v-if="task.due_date"> - Due {{ task.due_date }}</span>
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
          <div v-if="!meeting.tasks?.length" class="card p-6 text-slate-500">No tasks found yet.</div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
