<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-[#eef4fb]">
    <aside class="fixed inset-y-0 left-0 hidden w-72 border-r border-[#dbe7f3] bg-white/85 p-6 backdrop-blur-xl lg:block">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#0f766e] text-xl font-black text-white shadow-sm">AI</div>
        <div>
          <p class="text-sm text-[#607089]">Bilingual Minutes</p>
          <h1 class="text-lg font-black text-[#172033]">Meeting AI</h1>
        </div>
      </div>
      <nav class="mt-10 space-y-2">
        <RouterLink to="/" class="block rounded-2xl px-4 py-3 font-semibold text-[#43536a] hover:bg-[#eef7ff]" active-class="bg-[#0f766e] text-white hover:bg-[#0f766e]">Dashboard</RouterLink>
        <RouterLink to="/tasks" class="block rounded-2xl px-4 py-3 font-semibold text-[#43536a] hover:bg-[#eef7ff]" active-class="bg-[#0f766e] text-white hover:bg-[#0f766e]">Approvals</RouterLink>
        <RouterLink v-if="auth.isManager" to="/assignees" class="block rounded-2xl px-4 py-3 font-semibold text-[#43536a] hover:bg-[#eef7ff]" active-class="bg-[#0f766e] text-white hover:bg-[#0f766e]">Assignees</RouterLink>
      </nav>
      <div class="absolute bottom-6 left-6 right-6 rounded-3xl border border-[#dbe7f3] bg-[#f7fbff] p-4">
        <p class="text-sm font-bold text-[#172033]">{{ auth.user?.name }}</p>
        <p class="text-xs text-[#607089]">{{ auth.user?.email }} · {{ auth.user?.role }}</p>
        <button class="mt-3 text-sm font-bold text-[#43536a] hover:text-[#dc2626]" @click="logout">Logout</button>
      </div>
    </aside>

    <main class="lg:pl-72">
      <header class="sticky top-0 z-20 border-b border-[#dbe7f3] bg-white/80 px-5 py-4 backdrop-blur lg:hidden">
        <div class="flex items-center justify-between">
          <RouterLink to="/" class="font-black text-[#172033]">Meeting AI</RouterLink>
          <button class="text-sm font-bold text-[#43536a]" @click="logout">Logout</button>
        </div>
      </header>
      <div class="mx-auto max-w-7xl p-5 lg:p-8">
        <slot />
      </div>
    </main>
  </div>
</template>
