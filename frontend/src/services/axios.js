import axios from 'axios'

// Sử dụng Sanctum Cookie Session authentication (withCredentials: true)
const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Response interceptor — chuyển tiếp lỗi cho component/router guard xử lý
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      console.warn('Unauthenticated request.')
    }

    const originalRequest = error.config
    if (
      error.response?.status === 423 &&
      error.response?.data?.code === 'RECENT_AUTHENTICATION_REQUIRED' &&
      !originalRequest?._recentAuthRetried &&
      typeof window !== 'undefined'
    ) {
      const currentPassword = window.prompt('Phiên xác nhận đã hết hạn. Vui lòng nhập lại mật khẩu để tiếp tục:')
      if (currentPassword) {
        await apiClient.post('/api/auth/confirm-password', { current_password: currentPassword })
        return apiClient.request({ ...originalRequest, _recentAuthRetried: true })
      }
    }
    return Promise.reject(error)
  },
)

export default apiClient
