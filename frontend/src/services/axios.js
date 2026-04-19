import axios from 'axios'

const apiClient = axios.create({
  baseURL: 'http://localhost:8000/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Request interceptor — có thể thêm token vào header nếu cần
apiClient.interceptors.request.use(
  (config) => {
    return config
  },
  (error) => Promise.reject(error),
)

// Response interceptor — xử lý lỗi chung (ví dụ 401 Unauthenticated)
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // TODO: redirect về trang đăng nhập nếu cần
      console.warn('Unauthenticated – vui lòng đăng nhập lại.')
    }
    return Promise.reject(error)
  },
)

export default apiClient
