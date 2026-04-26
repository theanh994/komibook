import axios from 'axios'
import router from '@/router'

// Sử dụng proxy để mọi request đều là same-origin, loại bỏ hoàn toàn các lỗi Cookie/CORS
const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Request interceptor — tự động thêm Bearer token vào header
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error),
)

// Response interceptor — xử lý lỗi chung (ví dụ 401 Unauthenticated)
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      console.warn('Unauthenticated – vui lòng đăng nhập lại.')
      
      // Do không thể import useAuthStore tĩnh do vòng lặp import nên sẽ require động
      import('@/stores/auth').then(({ useAuthStore }) => {
        const authStore = useAuthStore()
        authStore.logout(true) // skipApi = true: tránh vòng lặp 401 → logout → 401
        router.push({ name: 'login' })
      })
    }
    return Promise.reject(error)
  },
)

export default apiClient
