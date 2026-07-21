import { defineStore } from 'pinia'
import apiClient from '@/services/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    // Kiểm tra cả localStorage và sessionStorage để lấy token hiện tại
    token: localStorage.getItem('token') || sessionStorage.getItem('token') || null,
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.role === 'admin',
    isVendor: (state) => state.user?.role === 'vendor',
    isCustomer: (state) => state.user?.role === 'customer',
  },
  actions: {
    async register(userData) {
      await apiClient.get('/sanctum/csrf-cookie')
      const response = await apiClient.post('/api/auth/register', userData)

      const responseData = response.data.data || response.data
      this.token = responseData.access_token || responseData.token
      
      // Mặc định đăng ký mới thường lưu vào localStorage hoặc tùy chọn
      localStorage.setItem('token', this.token)
      this.user = responseData.user || null
    },

    async login(credentials) {
      // 1. Phải gọi lấy CSRF Cookie của Sanctum trước
      await apiClient.get('/sanctum/csrf-cookie')
      
      // 2. Gửi thông tin đăng nhập
      const response = await apiClient.post('/api/auth/login', credentials)
      
      const responseData = response.data.data || response.data
      this.token = responseData.access_token || responseData.token
      
      // 3. Quyết định nơi lưu trữ dựa trên biến 'remember'
      if (credentials.remember) {
        localStorage.setItem('token', this.token)
        sessionStorage.removeItem('token') // Dọn dẹp session nếu có
      } else {
        sessionStorage.setItem('token', this.token)
        localStorage.removeItem('token') // Đảm bảo không còn token cũ ở local
      }
      
      // Lấy thông tin user ngay sau khi lưu token
      await this.fetchUser()
    },

    async loginWithGoogle(googleData) {
      await apiClient.get('/sanctum/csrf-cookie')
      const response = await apiClient.post('/api/auth/google-login', googleData)
      
      const responseData = response.data.data || response.data
      
      if (response.data.status === 'success') {
        this.token = responseData.access_token || responseData.token
        localStorage.setItem('token', this.token)
        sessionStorage.removeItem('token')
        await this.fetchUser()
      }
      
      return response.data;
    },

    async sendPhoneOtp(phone) {
      const response = await apiClient.post('/api/auth/phone/send-otp', { phone })
      return response.data
    },

    async verifyPhoneOtp(phone, otp) {
      await apiClient.get('/sanctum/csrf-cookie')
      const response = await apiClient.post('/api/auth/phone/verify-otp', { phone, otp })
      
      const responseData = response.data.data || response.data
      
      if (response.data.status === 'success') {
        this.token = responseData.access_token || responseData.token
        localStorage.setItem('token', this.token)
        sessionStorage.removeItem('token')
        await this.fetchUser()
      }
      
      return response.data
    },

    async fetchUser() {
      if (!this.token) return
      
      try {
        const response = await apiClient.get('/api/auth/me')
        const responseData = response.data.data || response.data
        this.user = responseData.user || responseData
      } catch (error) {
        this.logout()
      }
    },

    async logout(skipApi = false) {
      try {
        if (this.token && !skipApi) {
          await apiClient.post('/api/auth/logout')
        }
      } catch (e) {
        console.warn('Logout API failed', e)
      } finally {
        this.token = null
        this.user = null
        // Xóa sạch ở cả hai nơi lưu trữ
        localStorage.removeItem('token')
        sessionStorage.removeItem('token')
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
