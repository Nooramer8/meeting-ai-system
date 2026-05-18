<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import api from '../api/client'
import AppLayout from '../components/AppLayout.vue'
import StatusBadge from '../components/StatusBadge.vue'

const meetings = ref([])
const loading = ref(true)
const uploading = ref(false)
const title = ref('Weekly Project Meeting')
const recordingLanguage = ref('ar')
const selectedFile = ref(null)
const error = ref('')
const mediaRecorder = ref(null)
const activeStream = ref(null)
const isRecording = ref(false)
const microphonePermission = ref('unknown')
const audioInputs = ref([])
const selectedAudioInput = ref('')
const recordingSeconds = ref(0)
const recorderStatus = ref('Ready')
let recordingTimer = null
const chunks = []
const recordedUrl = ref('')

async function fetchMeetings() {
  loading.value = true
  const { data } = await api.get('/meetings')
  meetings.value = data.data.data
  loading.value = false
}

function onFileChange(event) {
  selectedFile.value = event.target.files[0]
}

function formatDuration(seconds) {
  const minutes = Math.floor(seconds / 60).toString().padStart(2, '0')
  const rest = (seconds % 60).toString().padStart(2, '0')
  return `${minutes}:${rest}`
}

async function refreshMicrophones() {
  if (!navigator.mediaDevices?.enumerateDevices) return

  try {
    const devices = await navigator.mediaDevices.enumerateDevices()
    audioInputs.value = devices.filter((device) => device.kind === 'audioinput')
    if (!selectedAudioInput.value && audioInputs.value.length > 0) {
      selectedAudioInput.value = audioInputs.value[0].deviceId
    }
  } catch (e) {
    recorderStatus.value = e.message || 'Could not read microphone devices.'
  }
}

async function refreshMicrophonePermission() {
  if (!navigator.permissions?.query) {
    await refreshMicrophones()
    return
  }

  try {
    const status = await navigator.permissions.query({ name: 'microphone' })
    microphonePermission.value = status.state
    status.onchange = () => {
      microphonePermission.value = status.state
      refreshMicrophones()
    }
  } catch {
    microphonePermission.value = 'unknown'
  }

  await refreshMicrophones()
}

function recordingErrorMessage(error) {
  if (error.name === 'NotAllowedError') {
    return 'Microphone is still blocked by the browser or Windows. Check site permissions and Windows Privacy & security > Microphone.'
  }
  if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
    return 'No microphone was found. Connect or enable a microphone, then click Refresh mics.'
  }
  if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
    return 'The microphone is busy or blocked by another app. Close apps using the mic, then try again.'
  }
  if (error.name === 'SecurityError') {
    return 'The browser blocked microphone recording for this page.'
  }
  return error.message || 'Could not start recording.'
}

async function startRecording() {
  error.value = ''
  recorderStatus.value = 'Requesting microphone...'
  if (!navigator.mediaDevices?.getUserMedia) {
    error.value = 'Your browser does not support microphone recording.'
    recorderStatus.value = 'Unsupported browser'
    return
  }

  try {
    const audio = selectedAudioInput.value
      ? { deviceId: { exact: selectedAudioInput.value }, echoCancellation: true, noiseSuppression: true }
      : { echoCancellation: true, noiseSuppression: true }
    const stream = await navigator.mediaDevices.getUserMedia({ audio })
    activeStream.value = stream
    await refreshMicrophonePermission()
    await refreshMicrophones()
    chunks.length = 0
    const mimeType = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : ''
    mediaRecorder.value = new MediaRecorder(stream, mimeType ? { mimeType } : undefined)
    mediaRecorder.value.ondataavailable = (event) => {
      if (event.data.size > 0) chunks.push(event.data)
    }
    mediaRecorder.value.onerror = (event) => {
      error.value = event.error?.message || 'Recording stopped because of a recorder error.'
      recorderStatus.value = 'Recorder error'
      stopRecording()
    }
    mediaRecorder.value.onstop = () => {
      const type = mimeType || 'audio/webm'
      const blob = new Blob(chunks, { type })
      if (blob.size > 0) {
        selectedFile.value = new File([blob], `meeting-${Date.now()}.webm`, { type })
        if (recordedUrl.value) URL.revokeObjectURL(recordedUrl.value)
        recordedUrl.value = URL.createObjectURL(blob)
        recorderStatus.value = 'Recording saved'
      } else {
        error.value = 'No audio was captured. Check the selected microphone and try again.'
        recorderStatus.value = 'No audio captured'
      }
      stopStream()
      stopRecordingTimer()
    }
    mediaRecorder.value.start(1000)
    isRecording.value = true
    recordingSeconds.value = 0
    recorderStatus.value = 'Recording...'
    recordingTimer = window.setInterval(() => {
      recordingSeconds.value += 1
    }, 1000)
  } catch (e) {
    error.value = recordingErrorMessage(e)
    recorderStatus.value = e.name || 'Recording blocked'
    await refreshMicrophonePermission()
  }
}

function stopRecording() {
  if (mediaRecorder.value?.state === 'recording') {
    mediaRecorder.value.stop()
  } else {
    stopStream()
  }
  isRecording.value = false
  stopRecordingTimer()
}

function stopStream() {
  activeStream.value?.getTracks().forEach((track) => track.stop())
  activeStream.value = null
}

function stopRecordingTimer() {
  if (recordingTimer) {
    window.clearInterval(recordingTimer)
    recordingTimer = null
  }
}

async function upload() {
  if (!selectedFile.value) {
    error.value = 'Choose a file or record audio first.'
    return
  }
  uploading.value = true
  error.value = ''
  try {
    const formData = new FormData()
    formData.append('title', title.value)
    formData.append('language', recordingLanguage.value)
    formData.append('meeting_file', selectedFile.value)
    await api.post('/meetings/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    selectedFile.value = null
    recordedUrl.value = ''
    await fetchMeetings()
  } catch (e) {
    error.value = e.response?.data?.message || 'Upload failed.'
  } finally {
    uploading.value = false
  }
}

onMounted(() => {
  fetchMeetings()
  refreshMicrophonePermission()
})
onUnmounted(() => {
  if (mediaRecorder.value?.state === 'recording') mediaRecorder.value.stop()
  stopRecordingTimer()
  stopStream()
  if (recordedUrl.value) URL.revokeObjectURL(recordedUrl.value)
})
</script>

<template>
  <AppLayout>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="font-bold uppercase tracking-[0.25em] text-teal-700/70">Dashboard</p>
        <h1 class="mt-2 text-4xl font-black text-[#172033]">Meetings</h1>
        <p class="mt-2 text-slate-500">Upload or record Arabic/English meetings and track AI processing.</p>
      </div>
      <button class="btn-secondary" @click="fetchMeetings">Refresh</button>
    </div>

    <section class="card mt-8 p-6">
      <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">
        <div>
          <h2 class="text-xl font-black text-slate-950">Record or upload meeting</h2>
          <p class="mt-1 text-sm text-slate-500">The backend will transcribe, generate minutes, and create pending tasks.</p>

          <label class="mt-6 block text-sm font-bold text-slate-700">Meeting title</label>
          <input v-model="title" class="input mt-2" />

          <label class="mt-5 block text-sm font-bold text-slate-700">Recording language</label>
          <select v-model="recordingLanguage" class="input mt-2">
            <option value="ar">Arabic</option>
            <option value="en">English</option>
            <option value="auto">Auto detect</option>
          </select>

          <label class="mt-5 block text-sm font-bold text-slate-700">Upload audio/video</label>
          <input class="mt-2 block w-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm" type="file" accept="audio/*,video/*" @change="onFileChange" />

          <div v-if="error" class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ error }}</div>
          <button class="btn-primary mt-6" :disabled="uploading" @click="upload">{{ uploading ? 'Uploading...' : 'Upload and process' }}</button>
        </div>

        <div class="rounded-3xl bg-[#103d45] p-6 text-white shadow-soft">
          <h3 class="text-lg font-black">Browser recorder</h3>
          <p class="mt-2 text-sm text-slate-300">Record directly from your microphone, then upload the captured WebM file.</p>

          <div class="mt-5 grid gap-3">
            <div>
              <label class="block text-sm font-bold text-slate-200">Microphone</label>
              <select v-model="selectedAudioInput" class="input mt-2 text-slate-950">
                <option v-if="!audioInputs.length" value="">No microphone found yet</option>
                <option v-for="device in audioInputs" :key="device.deviceId" :value="device.deviceId">
                  {{ device.label || 'Microphone' }}
                </option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-blue-100">Permission: {{ microphonePermission }}</span>
              <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-blue-100">{{ recorderStatus }}</span>
              <span v-if="isRecording" class="rounded-full bg-red-500 px-3 py-1 text-xs font-bold text-white">{{ formatDuration(recordingSeconds) }}</span>
            </div>
          </div>

          <div class="mt-6 flex flex-wrap gap-3">
            <button v-if="!isRecording" class="rounded-2xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950" @click="startRecording">Start recording</button>
            <button v-else class="rounded-2xl bg-red-500 px-4 py-2.5 text-sm font-bold text-white" @click="stopRecording">Stop recording</button>
            <button class="rounded-2xl border border-white/20 px-4 py-2.5 text-sm font-bold text-white" type="button" @click="refreshMicrophones">Refresh mics</button>
          </div>
          <p v-if="selectedFile" class="mt-4 text-sm text-blue-100">Selected: {{ selectedFile.name }}</p>
          <audio v-if="recordedUrl" class="mt-4 w-full" :src="recordedUrl" controls />
          <button
            v-if="recordedUrl"
            class="mt-4 w-full rounded-2xl bg-teal-300 px-4 py-2.5 text-sm font-bold text-[#103d45] transition hover:bg-teal-200 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="uploading"
            @click="upload"
          >
            {{ uploading ? 'Sending...' : 'Send recording to process' }}
          </button>
        </div>
      </div>
    </section>

    <section class="mt-8 grid gap-4">
      <div v-if="loading" class="card p-6 text-slate-500">Loading meetings...</div>
      <RouterLink v-for="meeting in meetings" :key="meeting.id" :to="`/meetings/${meeting.id}`" class="card block p-5 transition hover:-translate-y-1 hover:border-teal-200 hover:shadow-lg">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-lg font-black text-slate-950">{{ meeting.title }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ meeting.original_filename }} · {{ meeting.tasks_count }} tasks · {{ new Date(meeting.created_at).toLocaleString() }}</p>
          </div>
          <StatusBadge :status="meeting.status" />
        </div>
      </RouterLink>
    </section>
  </AppLayout>
</template>
