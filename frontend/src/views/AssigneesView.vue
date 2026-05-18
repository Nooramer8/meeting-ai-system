<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '../api/client'
import AppLayout from '../components/AppLayout.vue'

const assignees = ref([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const form = reactive({
  name: '',
  email: '',
  phone: '',
  position: ''
})

async function fetchAssignees() {
  loading.value = true
  const { data } = await api.get('/assignees')
  assignees.value = data.data.data
  loading.value = false
}

function resetForm() {
  form.name = ''
  form.email = ''
  form.phone = ''
  form.position = ''
}

async function createAssignee() {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await api.post('/assignees', {
      name: form.name,
      email: form.email,
      phone: form.phone || null,
      position: form.position || null
    })
    success.value = 'Assignee created and available for task matching.'
    resetForm()
    await fetchAssignees()
  } catch (e) {
    const errors = e.response?.data?.errors
    error.value = errors ? Object.values(errors).flat().join(' ') : e.response?.data?.message || 'Could not create assignee.'
  } finally {
    saving.value = false
  }
}

onMounted(fetchAssignees)
</script>

<template>
  <AppLayout>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="font-bold uppercase tracking-[0.25em] text-teal-700/70">Team</p>
        <h1 class="mt-2 text-4xl font-black text-[#172033]">Assignees</h1>
        <p class="mt-2 text-slate-500">Create people that meeting tasks can be assigned to by name or email.</p>
      </div>
      <button class="btn-secondary" @click="fetchAssignees">Refresh</button>
    </div>

    <section class="mt-8 grid gap-6 lg:grid-cols-[420px_1fr]">
      <form class="card p-6" @submit.prevent="createAssignee">
        <h2 class="text-xl font-black text-slate-950">New assignee</h2>

        <label class="mt-6 block text-sm font-bold text-slate-700">Name</label>
        <input v-model="form.name" class="input mt-2" required />

        <label class="mt-5 block text-sm font-bold text-slate-700">Email</label>
        <input v-model="form.email" class="input mt-2" type="email" required />

        <label class="mt-5 block text-sm font-bold text-slate-700">Phone number</label>
        <input v-model="form.phone" class="input mt-2" type="tel" />

        <label class="mt-5 block text-sm font-bold text-slate-700">Position</label>
        <input v-model="form.position" class="input mt-2" placeholder="Designer, Manager, Developer..." />

        <div v-if="error" class="mt-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ error }}</div>
        <div v-if="success" class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">{{ success }}</div>

        <button class="btn-primary mt-6 w-full" :disabled="saving">{{ saving ? 'Creating...' : 'Create assignee' }}</button>
      </form>

      <div class="grid gap-4 content-start">
        <div v-if="loading" class="card p-6 text-slate-500">Loading assignees...</div>
        <div v-for="assignee in assignees" :key="assignee.id" class="card p-5">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h3 class="text-lg font-black text-slate-950">{{ assignee.name }}</h3>
              <p class="mt-1 text-sm text-slate-500">{{ assignee.position || 'No position' }}</p>
              <p class="mt-3 text-sm text-slate-700">{{ assignee.email }}</p>
              <p v-if="assignee.phone" class="mt-1 text-sm text-slate-700">{{ assignee.phone }}</p>
            </div>
            <span class="badge bg-blue-50 text-blue-700">{{ assignee.role }}</span>
          </div>
        </div>
        <div v-if="!loading && !assignees.length" class="card p-6 text-slate-500">No assignees yet.</div>
      </div>
    </section>
  </AppLayout>
</template>
