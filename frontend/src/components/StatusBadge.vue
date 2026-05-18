<script setup>
defineProps({ status: { type: String, default: 'unknown' } })

const colors = {
  uploaded: 'bg-sky-50 text-sky-700',
  transcribing: 'bg-amber-50 text-amber-700',
  transcribed: 'bg-blue-50 text-blue-700',
  ai_processing: 'bg-violet-50 text-violet-700',
  needs_approval: 'bg-orange-50 text-orange-700',
  completed: 'bg-teal-50 text-teal-700',
  failed: 'bg-rose-50 text-rose-700',
  pending_approval: 'bg-orange-50 text-orange-700',
  approved: 'bg-teal-50 text-teal-700',
  rejected: 'bg-rose-50 text-rose-700',
  sent: 'bg-blue-50 text-blue-700'
}
</script>

<template>
  <span class="badge" :class="colors[status] || 'bg-blue-50 text-blue-700'">
    {{ status.replaceAll('_', ' ') }}
  </span>
</template>
