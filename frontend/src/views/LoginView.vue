<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const loading = ref(false)
const error = ref('')
const form = reactive({ email: 'admin@example.com', password: 'password', device_name: 'vue-spa' })

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(form)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message || 'Login failed.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-[#eef4fb] p-6">
    <div class="grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-[#dbe7f3] bg-white shadow-soft lg:grid-cols-2">
      <div class="hidden bg-[#103d45] p-10 text-white lg:block">
        <div class="rounded-3xl border border-white/10 bg-white/10 p-6 backdrop-blur">
          <p class="text-sm uppercase tracking-[0.3em] text-teal-100">Laravel - Vue - Groq</p>
          <h1 class="mt-6 text-4xl font-black leading-tight">Convert bilingual meetings into approved tasks.</h1>
          <p class="mt-5 text-teal-50/80">Record or upload Arabic/English meetings, generate minutes, approve tasks, then send clean emails to each assignee.</p>
        </div>
      </div>
      <form class="p-8 lg:p-12" @submit.prevent="submit">
        <h2 class="text-3xl font-black text-[#172033]">Welcome back</h2>
        <p class="mt-2 text-[#607089]">Sign in to manage meeting minutes and approvals.</p>

        <div v-if="error" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ error }}</div>

        <label class="mt-8 block text-sm font-bold text-[#43536a]">Email</label>
        <input v-model="form.email" class="input mt-2" type="email" required />

        <label class="mt-5 block text-sm font-bold text-[#43536a]">Password</label>
        <input v-model="form.password" class="input mt-2" type="password" required />

        <button class="btn-primary mt-8 w-full" :disabled="loading">
          {{ loading ? 'Signing in...' : 'Sign in' }}
        </button>
      </form>
    </div>
  </div>
</template>
