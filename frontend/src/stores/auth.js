import { defineStore } from 'pinia'
import api from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('meeting_ai_user') || 'null'),
    token: localStorage.getItem('meeting_ai_token')
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    isManager: (state) => ['admin', 'manager'].includes(state.user?.role)
  },
  actions: {
    async login(credentials) {
      const { data } = await api.post('/auth/login', credentials)
      this.user = data.data.user
      this.token = data.data.token
      localStorage.setItem('meeting_ai_token', this.token)
      localStorage.setItem('meeting_ai_user', JSON.stringify(this.user))
    },
    async logout() {
      try {
        await api.post('/auth/logout')
      } finally {
        this.user = null
        this.token = null
        localStorage.removeItem('meeting_ai_token')
        localStorage.removeItem('meeting_ai_user')
      }
    }
  }
})
