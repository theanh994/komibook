# 📚 KomiBook — Frontend

Frontend của dự án **KomiBook** — nền tảng thương mại điện tử sách đa người bán (multi-vendor), xây dựng bằng **Vue 3** với Composition API, **Pinia** quản lý state và **PrimeVue** làm UI component library.

---

## 🛠️ Công nghệ sử dụng

| Thành phần        | Phiên bản  |
|-------------------|------------|
| Vue               | ^3.5.31    |
| Vue Router        | ^5.0.4     |
| Pinia             | ^3.0.4     |
| PrimeVue          | ^4.5.5     |
| Tailwind CSS      | ^4.2.2     |
| Axios             | ^1.15.0    |
| Vite              | ^8.0.3     |

### Dev Tools
- `eslint` + `oxlint` — Linting
- `prettier` — Code formatting
- `vite-plugin-vue-devtools` — Vue DevTools Vite plugin

---

## 📁 Cấu trúc thư mục

```
frontend/
├── public/                  # Static assets (favicon, ...)
├── src/
│   ├── assets/
│   │   └── main.css         # Global styles & Tailwind directives
│   ├── router/
│   │   └── index.js         # Cấu hình Vue Router
│   ├── services/
│   │   └── axios.js         # Axios instance (base URL, interceptors)
│   ├── stores/              # Pinia stores (quản lý state)
│   ├── App.vue              # Root component
│   └── main.js              # Entry point — khởi tạo Vue app
├── index.html               # HTML template
├── vite.config.js           # Cấu hình Vite
├── eslint.config.js         # Cấu hình ESLint
└── .prettierrc.json         # Cấu hình Prettier
```

---

## ⚙️ Cấu hình Axios

File: `src/services/axios.js`

```js
const apiClient = axios.create({
  baseURL: 'http://localhost:8000/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})
```

- **`baseURL`**: Trỏ tới Laravel backend (`http://localhost:8000/api`).
- **`withCredentials: true`**: Gửi cookie session theo mỗi request (cần thiết cho Sanctum).
- **Request Interceptor**: Có thể bổ sung Bearer token vào header nếu cần.
- **Response Interceptor**: Tự động bắt lỗi `401 Unauthenticated` và cảnh báo người dùng.

---

## 🎨 UI & Styling

### PrimeVue
Sử dụng **PrimeVue v4** với theme **Aura** (preset mặc định).

```js
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      darkModeSelector: '.dark-mode', // Kích hoạt dark mode bằng class
    },
  },
})
```

- Icon pack: **PrimeIcons v7**
- Tất cả component PrimeVue có thể dùng toàn cục sau khi đăng ký.

### Tailwind CSS v4
Tích hợp qua `@tailwindcss/vite` plugin. Không cần file `tailwind.config.js` riêng.

---

## 🗺️ Routing (Vue Router v5)

Cấu hình tại `src/router/index.js`.

> ⚠️ Routes đang được phát triển. Các trang chức năng sẽ được bổ sung theo tiến độ dự án.

---

## 🗃️ State Management (Pinia)

Stores được đặt trong `src/stores/`. Pinia được khởi tạo trong `main.js`:

```js
app.use(createPinia())
```

> ⚠️ Stores chức năng (auth, cart, ...) đang được xây dựng.

---

## 🚀 Hướng dẫn cài đặt & chạy

### Yêu cầu hệ thống
- **Node.js**: `^20.19.0` hoặc `>=22.12.0`
- **npm**: phiên bản tương ứng với Node

```bash
# 1. Cài đặt dependencies
npm install

# 2. Khởi động server phát triển
npm run dev
# Frontend chạy tại: http://localhost:5173

# 3. Build production bundle
npm run build

# 4. Preview bản build
npm run preview
```

---

## 🧹 Linting & Formatting

```bash
# Chạy toàn bộ linting (oxlint + eslint)
npm run lint

# Format code với Prettier
npm run format
```

---

## 🔗 Kết nối với Backend

| Môi trường  | Backend URL              |
|-------------|--------------------------|
| Development | `http://localhost:8000`  |

Đảm bảo backend Laravel đã:
1. Chạy tại `http://localhost:8000`
2. Cấu hình CORS cho phép `http://localhost:5173`
3. Bật Laravel Sanctum

---

## 📌 Ghi chú

- Frontend và backend được tách riêng trong monorepo (thư mục `frontend/` và `backend/`).
- Giao tiếp API hoàn toàn qua JSON (stateless token auth với Sanctum).
- Dark mode được kích hoạt bằng cách thêm class `.dark-mode` vào element gốc.
