# 📚 KomiBook — Backend API

Backend của dự án **KomiBook** — nền tảng thương mại điện tử sách đa người bán (multi-vendor), xây dựng bằng **Laravel 11** và xác thực qua **Laravel Sanctum**.

---

## 🛠️ Công nghệ sử dụng

| Thành phần        | Phiên bản |
|-------------------|-----------|
| PHP               | ^8.2      |
| Laravel Framework | ^11.0     |
| Laravel Sanctum   | ^4.0      |
| Predis (Redis)    | ^3.4      |
| Laravel Tinker    | ^2.9      |

### Dev Dependencies
- `barryvdh/laravel-ide-helper` — IDE autocompletion
- `laravel/pint` — PHP code style fixer
- `phpunit/phpunit` — unit testing
- `spatie/laravel-ignition` — error reporting

---

## 📁 Cấu trúc thư mục chính

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── AuthController.php     # Xử lý đăng ký / đăng nhập / đăng xuất / me
│   │   ├── Requests/
│   │   │   └── Auth/
│   │   │       ├── LoginRequest.php
│   │   │       └── RegisterRequest.php
│   │   └── Resources/
│   │       └── UserResource.php           # API Resource cho User (bao gồm vendor_profile)
│   ├── Models/
│   │   ├── User.php                       # Role: admin | vendor | customer
│   │   ├── Vendor.php                     # Hồ sơ cửa hàng của Vendor
│   │   ├── Book.php                       # Sách (có Global Scope theo vendor)
│   │   ├── Category.php                   # Danh mục sách
│   │   ├── Order.php                      # Đơn hàng
│   │   └── OrderItem.php                  # Chi tiết đơn hàng
│   ├── Scopes/
│   │   ├── BookVisibilityScope.php        # Global scope: vendor chỉ thấy sách của mình
│   │   └── OrderVisibilityScope.php       # Global scope: vendor chỉ thấy đơn hàng mình nhận
│   ├── Services/                          # Business logic layer (chuẩn bị mở rộng)
│   └── Traits/
│       └── MultiVendorScoped.php          # Trait áp dụng Global Scope cho models đa vendor
├── database/
│   ├── migrations/
│   │   ├── create_users_table.php
│   │   ├── create_vendors_table.php
│   │   ├── create_categories_table.php
│   │   ├── create_books_table.php
│   │   ├── create_orders_table.php
│   │   ├── create_order_items_table.php
│   │   └── create_personal_access_tokens_table.php
│   └── seeders/
│       └── DatabaseSeeder.php             # Seed admin, vendor, customer mẫu
└── routes/
    └── api.php                            # Định nghĩa tất cả API routes
```

---

## 🗄️ Cơ sở dữ liệu

### Bảng `users`
| Cột        | Kiểu                              | Mô tả                         |
|------------|-----------------------------------|-------------------------------|
| `id`       | bigint (PK)                       |                               |
| `name`     | varchar                           |                               |
| `email`    | varchar (unique)                  |                               |
| `password` | varchar (hashed)                  |                               |
| `role`     | enum: `admin`, `vendor`, `customer` | Phân quyền người dùng       |

### Bảng `vendors`
| Cột           | Kiểu    | Mô tả                    |
|---------------|---------|--------------------------|
| `id`          | bigint  |                          |
| `user_id`     | bigint (FK → users) | 1-1 với user |
| `shop_name`   | varchar | Tên cửa hàng             |
| `slug`        | varchar | Đường dẫn thân thiện URL |
| `logo`        | varchar | Ảnh logo                 |
| `description` | text    | Mô tả cửa hàng           |
| `status`      | enum: `pending`, `active`, `banned` |  |

### Bảng `books`
| Cột           | Kiểu    | Mô tả                         |
|---------------|---------|-------------------------------|
| `vendor_id`   | FK      | Cửa hàng sở hữu sách          |
| `category_id` | FK      | Danh mục sách                 |
| `title`       | varchar |                               |
| `slug`        | varchar |                               |
| `author`      | varchar |                               |
| `price`       | integer | Giá gốc (VNĐ)                 |
| `sale_price`  | integer | Giá khuyến mãi (nullable)     |
| `stock`       | integer | Số lượng tồn kho              |
| `type`        | enum: `physical`, `ebook` |                |
| `status`      | enum: `draft`, `published`, `archived` |   |

### Bảng `orders` & `order_items`
Lưu thông tin đơn hàng và chi tiết sản phẩm trong đơn.

---

## 🔐 Xác thực & Phân quyền

Dự án sử dụng **Laravel Sanctum** (token-based auth cho SPA).

### Chính sách Token
- **Single-device policy**: Khi đăng nhập thành công, tất cả token cũ bị xóa trước khi cấp token mới.
- Token được gửi qua header `Authorization: Bearer <token>`.

### Phân quyền theo Role
| Role       | Mô tả                                                   |
|------------|----------------------------------------------------------|
| `admin`    | Quản trị toàn bộ hệ thống                               |
| `vendor`   | Quản lý cửa hàng, sách và đơn hàng của mình             |
| `customer` | Tìm kiếm, mua sách, xem lịch sử đơn hàng               |

### Global Scopes (Multi-Vendor Isolation)
- `BookVisibilityScope`: Vendor chỉ truy vấn được sách của mình.
- `OrderVisibilityScope`: Vendor chỉ truy vấn được đơn hàng liên quan đến cửa hàng mình.
- Trait `MultiVendorScoped` được áp dụng vào model `Book` và `Order`.

---

## 🛣️ API Routes

Base URL: `http://localhost:8000/api`

### Public Routes (không cần xác thực)
| Method | Endpoint           | Controller action          | Mô tả              |
|--------|--------------------|----------------------------|--------------------|
| POST   | `/auth/register`   | `AuthController@register`  | Đăng ký tài khoản  |
| POST   | `/auth/login`      | `AuthController@login`     | Đăng nhập          |

### Protected Routes (yêu cầu `Authorization: Bearer <token>`)
| Method | Endpoint       | Controller action         | Mô tả                      |
|--------|----------------|---------------------------|----------------------------|
| POST   | `/auth/logout` | `AuthController@logout`   | Đăng xuất (thu hồi token)  |
| GET    | `/auth/me`     | `AuthController@me`       | Thông tin user hiện tại    |

---

## 📦 API Resource

### `UserResource`
Trả về thông tin user trong mọi response xác thực:

```json
{
  "id": 1,
  "name": "Nguyễn Văn A",
  "email": "vendor@example.com",
  "role": "vendor",
  "created_at": "2026-04-19T00:00:00.000Z",
  "vendor_profile": {
    "shop_name": "Sách Hay",
    "slug": "sach-hay",
    "logo": null,
    "description": "Cửa hàng sách chất lượng",
    "status": "active"
  }
}
```

> `vendor_profile` chỉ xuất hiện khi `role === 'vendor'`. Sử dụng `whenLoaded()` để tránh N+1 query.

---

## ⚙️ Cấu hình môi trường

Sao chép file `.env.example` thành `.env` và cập nhật các giá trị:

```env
APP_NAME=KomiBook
APP_URL=http://localhost:8000

# Database (MySQL cho production)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=komibook
DB_USERNAME=root
DB_PASSWORD=

# Cache & Queue (Redis)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# CORS — cho phép frontend Vue
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

---

## 🚀 Hướng dẫn cài đặt & chạy

```bash
# 1. Cài đặt dependencies
composer install

# 2. Sao chép file cấu hình
cp .env.example .env

# 3. Tạo application key
php artisan key:generate

# 4. Chạy migration & seed dữ liệu mẫu
php artisan migrate --seed

# 5. Khởi động server phát triển (dùng Laravel Herd hoặc artisan)
php artisan serve
# Server chạy tại: http://localhost:8000

# 6. (Tùy chọn) Sinh file IDE helper
php artisan ide-helper:generate
php artisan ide-helper:models --nowrite
```

---

## 🧪 Kiểm thử

```bash
# Chạy toàn bộ test suite
php artisan test

# Kiểm tra code style
./vendor/bin/pint
```

---

## 📌 Ghi chú

- CORS được cấu hình để chấp nhận request từ `http://localhost:5173` (frontend Vue).
- Middleware `auth:sanctum` bảo vệ tất cả protected routes.
- Model `User` implement `HasApiTokens` từ Sanctum.
- Password được tự động hash thông qua `cast: 'hashed'` trong model.
