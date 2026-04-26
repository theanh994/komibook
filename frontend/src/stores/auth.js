import { defineStore } from 'pinia'
import apiClient from '@/services/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
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

      // Backend trả về: { status: 'success', data: { user, access_token, token_type } }
      const responseData = response.data.data || response.data

      this.token = responseData.access_token || responseData.token
      localStorage.setItem('token', this.token)

      // Lưu user trực tiếp từ response (không cần gọi thêm /me)
      this.user = responseData.user || null
    },
    async login(credentials) {
      // 1. Phải gọi lấy CSRF Cookie của Sanctum trước
      await apiClient.get('/sanctum/csrf-cookie')
      
      // 2. Gửi thông tin đăng nhập
      const response = await apiClient.post('/api/auth/login', credentials)
      
      // Backend trả về: { status: 'success', data: { access_token: '...', user: {...} } }
      const responseData = response.data.data || response.data
      
      this.token = responseData.access_token || responseData.token
      localStorage.setItem('token', this.token)
      
      // Lấy thông tin user ngay sau khi lưu token
      await this.fetchUser()
    },
    async fetchUser() {
      if (!this.token) return
      
      try {
        const response = await apiClient.get('/api/auth/me')
        const responseData = response.data.data || response.data
        this.user = responseData.user || responseData
      } catch (error) {
        // Nếu token không hợp lệ hoặc lấy thông tin thất bại, thực hiện đăng xuất
        this.logout()
      }
    },
    async logout(skipApi = false) {
      try {
        if (this.token && !skipApi) {
          // Gửi request thu hồi token ở backend
          await apiClient.post('/api/auth/logout')
        }
      } catch (e) {
        console.warn('Logout API failed', e)
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('token')
      }
    },
    async updateProfile(profileData) {
      const response = await apiClient.put('/api/profile/info', profileData)
      const responseData = response.data.data || response.data
      
      // Update local user state
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
