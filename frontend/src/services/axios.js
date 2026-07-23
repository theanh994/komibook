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
  (error) => {
    if (error.response?.status === 401) {
      console.warn('Unauthenticated request.')
    }
    return Promise.reject(error)
  },
)

export default apiClient
