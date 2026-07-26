import { defineStore } from 'pinia'
import apiClient from '@/services/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    userFetched: false,
  }),
  getters: {
    isAuthenticated: (state) => !!state.user,
    isAdmin: (state) => state.user?.role === 'admin',
    isAuthor: (state) => state.user?.role === 'author' || state.user?.author_profile?.status === 'active',
    isVendor: (state) => state.user?.role === 'vendor',
    isCustomer: (state) => state.user?.role === 'customer',
  },
  actions: {
    async register(userData) {
      await apiClient.get('/sanctum/csrf-cookie')
      const response = await apiClient.post('/api/auth/register', userData)
      await this.fetchUser()
      return response.data
    },

    async sendRegistrationEmailOtp(email) {
      const response = await apiClient.post('/api/auth/email/send-otp', { email })
      return response.data
    },

    async verifyRegistrationEmailOtp(email, otp) {
      const response = await apiClient.post('/api/auth/email/verify-otp', { email, otp })
      return response.data
    },

    async login(credentials) {
      // 1. Phải gọi lấy CSRF Cookie của Sanctum trước
      await apiClient.get('/sanctum/csrf-cookie')
      
      // 2. Gửi thông tin đăng nhập
      const response = await apiClient.post('/api/auth/login', credentials)
      
      // 3. Lấy thông tin user ngay sau khi đăng nhập thành công qua Cookie Session
      await this.fetchUser()
      return response.data
    },

    async loginWithGoogle(googleData) {
      await apiClient.get('/sanctum/csrf-cookie')
      const response = await apiClient.post('/api/auth/google-login', googleData)
      
      if (response.data.status === 'success') {
        await this.fetchUser()
      }
      
      return response.data;
    },

    async loginWithFacebook(facebookData) {
      await apiClient.get('/sanctum/csrf-cookie')
      const response = await apiClient.post('/api/auth/facebook-login', facebookData)

      if (response.data.status === 'success') {
        await this.fetchUser()
      }

      return response.data
    },

    async sendPhoneOtp(phone) {
      const response = await apiClient.post('/api/auth/phone/send-otp', { phone })
      return response.data
    },

    async verifyPhoneOtp(phone, otp) {
      await apiClient.get('/sanctum/csrf-cookie')
      const response = await apiClient.post('/api/auth/phone/verify-otp', { phone, otp })
      
      if (response.data.status === 'success') {
        await this.fetchUser()
      }
      
      return response.data
    },

    async fetchUser() {
      try {
        const response = await apiClient.get('/api/auth/me')
        const responseData = response.data.data || response.data
        this.user = responseData.user || responseData
      } catch {
        this.user = null
      } finally {
        this.userFetched = true
      }
    },

    async logout(skipApi = false) {
      try {
        if (this.user && !skipApi) {
          await apiClient.post('/api/auth/logout')
        }
      } catch (e) {
        console.warn('Logout API failed', e)
      } finally {
        this.user = null
        this.userFetched = true
      }
    },

    async updateProfile(profileData) {
      const response = await apiClient.put('/api/profile/info', profileData)
      const responseData = response.data.data || response.data
      
      if (this.user && responseData) {
        this.user = { ...this.user, ...responseData }
      }
      return response.data
    },

    async updatePassword(passwordData) {
      const response = await apiClient.put('/api/profile/password', passwordData)
      return response.data
    }
  }
})
