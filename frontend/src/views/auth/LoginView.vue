<template>
  <div class="bg-background pattern-bg min-h-[calc(100vh-80px)] flex items-center justify-center p-4 md:p-8 font-inter text-on-background overflow-x-hidden">
    <!-- Main Container -->
    <main class="w-full max-w-[960px] animate-in fade-in zoom-in duration-500 relative z-10">
      <!-- Login Card -->
      <div class="glass-panel soft-shadow rounded-[24px] overflow-hidden border border-white/40 flex flex-col lg:flex-row shadow-2xl">
        <!-- Left Column: Brand & Welcome -->
        <div class="lg:flex flex-col justify-center p-8 lg:p-12 bg-primary/[0.03] relative overflow-hidden border-b lg:border-b-0 lg:border-r border-outline-variant/10 flex-1">
          <div class="absolute top-0 right-0 w-64 h-64 bg-primary-fixed rounded-bl-full opacity-20 -z-10 blur-3xl"></div>
          <div class="flex flex-col items-center lg:items-start text-center lg:text-left gap-4">
            <div class="text-3xl md:text-4xl font-bold text-primary tracking-tight leading-tight select-none">
              Komibook<span class="text-secondary text-xl md:text-2xl font-semibold align-top ml-1">Premium</span>
            </div>
            <div class="flex flex-col gap-3">
              <h1 class="text-2xl md:text-3xl font-semibold text-on-surface tracking-tight">Chào mừng trở lại</h1>
              <p class="text-base text-on-surface-variant font-medium max-w-[300px] leading-relaxed">Đăng nhập để tiếp tục khám phá kho tàng tri thức của bạn.</p>
            </div>
            <!-- Trust Indicators -->
            <div class="hidden lg:flex flex-col gap-3 mt-6">
              <div class="flex items-center gap-3 text-on-surface-variant/80">
                <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                <span class="text-sm font-medium">Hơn 10,000+ đầu sách tuyển chọn</span>
              </div>
              <div class="flex items-center gap-3 text-on-surface-variant/80">
                <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                <span class="text-sm font-medium">Trải nghiệm đọc Premium mượt mà</span>
              </div>
              <div class="flex items-center gap-3 text-on-surface-variant/80">
                <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                <span class="text-sm font-medium">Đồng bộ dữ liệu đa thiết bị</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Login Form -->
        <div class="p-8 md:p-10 flex flex-col gap-6 flex-1 bg-white/60 backdrop-blur-sm relative">
          <div class="absolute bottom-0 left-0 w-40 h-40 bg-secondary-fixed rounded-tr-full opacity-10 -z-10 blur-3xl"></div>
          <!-- Social Logins -->
          <div class="flex flex-col gap-3">
            <button type="button" @click="openGoogleLogin" class="w-full h-12 flex items-center justify-center gap-3 px-6 border border-outline-variant/60 rounded-xl bg-surface hover:bg-surface-container-low transition-all duration-200 cursor-pointer group shadow-sm active:scale-[0.98]">
              <svg class="w-5 h-5 transition-transform group-hover:scale-110" viewBox="0 0 24 24">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"></path>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"></path>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"></path>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"></path>
              </svg>
              <span class="text-sm font-semibold text-on-surface">Tiếp tục với Google</span>
            </button>
            <button type="button" @click="openFacebookLogin" class="w-full h-12 flex items-center justify-center gap-3 px-6 border border-outline-variant/60 rounded-xl bg-surface hover:bg-surface-container-low transition-all duration-200 cursor-pointer group shadow-sm active:scale-[0.98]">
              <svg class="w-5 h-5 text-[#1877F2] transition-transform group-hover:scale-110" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.414c0-3.026 1.792-4.697 4.533-4.697 1.313 0 2.686.236 2.686.236v2.974h-1.513c-1.49 0-1.956.931-1.956 1.887v2.259h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073Z"/>
              </svg>
              <span class="text-sm font-semibold text-on-surface">Tiếp tục với Facebook</span>
            </button>
          </div>

          <!-- Divider -->
          <div class="flex items-center gap-4 py-2">
            <div class="flex-1 h-[1px] bg-outline-variant/40"></div>
            <span class="text-[11px] font-bold text-outline uppercase tracking-widest select-none">hoặc đăng nhập bằng email</span>
            <div class="flex-1 h-[1px] bg-outline-variant/40"></div>
          </div>

          <!-- Form -->
          <form @submit.prevent="handleLogin" class="flex flex-col gap-6">
            <div class="flex flex-col gap-4">
              <!-- Email Input -->
              <div class="flex flex-col gap-1.5">
                <label class="text-[13px] font-semibold text-on-surface-variant ml-0.5 flex items-center gap-2 uppercase tracking-wide" for="email">
                  <span class="material-symbols-outlined text-[18px] text-primary/80">person</span>
                  Email
                </label>
                <input                  v-model="email"
                  class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all duration-200"                  id="login-email"                  placeholder="name@example.com"                  type="email"
                  autocomplete="email"
                  required
                />
              </div>
              <!-- Password Input -->
              <div class="flex flex-col gap-1.5">
                <div class="flex justify-between items-center px-0.5">
                  <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide" for="login-password">
                    <span class="material-symbols-outlined text-[18px] text-primary/80">lock</span>
                    Mật khẩu
                  </label>
                  <router-link to="/forgot-password" class="text-xs font-semibold text-primary hover:text-secondary transition-colors">Quên mật khẩu?</router-link>
                </div>
                <div class="relative group">
                  <input                    v-model="password"
                    class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all duration-200"                    id="login-password"                    placeholder="••••••••"                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    required
                  />
                  <button @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary cursor-pointer transition-colors p-1" type="button">
                    <span class="material-symbols-outlined" style="font-size: 20px;">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
                  </button>
                </div>
              </div>
              <!-- Remember Me -->
              <div class="flex items-center gap-3 mt-1 px-0.5">
                <input                  v-model="remember"
                  class="peer w-5 h-5 rounded-md border border-outline-variant text-primary focus:ring-primary focus:ring-offset-0 bg-surface-container-lowest cursor-pointer transition-all checked:bg-primary checked:border-primary"                  id="remember"                  type="checkbox"
                />
                <label class="text-sm font-medium text-on-surface-variant cursor-pointer select-none hover:text-on-surface transition-colors" for="remember">Duy trì đăng nhập</label>
              </div>
            </div>
            <!-- Submit Button -->
            <button              class="w-full h-12 bg-primary text-on-primary font-semibold text-base rounded-xl hover:bg-primary-container hover:shadow-lg active:scale-[0.98] focus:outline-none focus:ring-4 focus:ring-primary/10 transition-all duration-300 soft-shadow disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center gap-2 cursor-pointer mt-2"              type="submit"
              :disabled="loading"
            >
              <template v-if="loading">
                <span class="pi pi-spin pi-spinner text-sm"></span>
                <span>Đang đăng nhập...</span>
              </template>
              <template v-else>
                <span>Đăng nhập</span>
                <span class="material-symbols-outlined text-[20px]">login</span>
              </template>
            </button>
          </form>

          <!-- Footer Links -->
          <div class="text-center mt-2">
            <span class="text-sm text-on-surface-variant">Chưa có tài khoản?</span>
            <router-link to="/register" class="text-sm font-bold text-primary hover:text-secondary ml-2 underline underline-offset-4 transition-colors">Đăng ký ngay</router-link>
          </div>
        </div>
      </div>

      <!-- Trust indicators / Minimal Footer -->
      <div class="mt-8 text-center flex flex-col items-center gap-4">
        <div class="flex items-center gap-4 text-xs font-semibold text-on-surface-variant/40 uppercase tracking-widest">
          <a class="hover:text-primary transition-all" href="#">Điều khoản</a>
          <span class="w-1 h-1 bg-outline/20 rounded-full"></span>
          <a class="hover:text-primary transition-all" href="#">Bảo mật</a>
          <span class="w-1 h-1 bg-outline/20 rounded-full"></span>
          <a class="hover:text-primary transition-all" href="#">Trợ giúp</a>
        </div>
      </div>
    </main>



    <!-- Social Account Complete Registration Dialog -->
    <Dialog      v-model:visible="googleRegDialogVisible"      :header="`Hoàn tất đăng ký với ${socialProviderLabel}`"      :modal="true"      class="!max-w-xl !w-[90vw] !rounded-[24px] !bg-surface-container-lowest"
    >
      <form @submit.prevent="handleGoogleRegister" class="flex flex-col gap-5 py-4">
        <div class="bg-primary/5 p-4 rounded-xl border border-primary/10 flex items-center gap-3">
          <span class="material-symbols-outlined text-primary text-2xl">verified_user</span>
          <div>
            <div class="text-xs font-bold text-primary uppercase tracking-wide">Tài khoản {{ socialProviderLabel }} đã xác minh</div>
            <div class="text-xs text-on-surface-variant mt-0.5">KomiBook đã xác minh tài khoản với {{ socialProviderLabel }}. Vui lòng bổ sung thêm thông tin.</div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Email (Read-only) -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">mail</span>
              Email
            </label>
            <input              v-model="googleRegForm.email"
              required
              type="email"
              :disabled="socialEmailLocked"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface-variant disabled:opacity-60 disabled:cursor-not-allowed"            />
          </div>

          <!-- Name -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">person</span>
              Họ và tên
            </label>
            <input              v-model="googleRegForm.name"
              required
              placeholder="Họ và tên"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4">
          <!-- Gender -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">wc</span>
              Giới tính
            </label>
            <select              v-model="googleRegForm.gender"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface focus:outline-none focus:border-primary transition-all duration-200"            >
              <option value="male">Nam</option>
              <option value="female">Nữ</option>
              <option value="other">Khác</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Birthday -->
          <div class="flex flex-col gap-1.5 col-span-1">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">cake</span>
              Ngày sinh
            </label>
            <input              v-model="googleRegForm.birthday"
              type="date"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>

          <!-- Desired Role -->
          <div class="flex flex-col gap-1.5 col-span-1">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">group</span>
              Vai trò đăng ký
            </label>
            <select              v-model="googleRegForm.desired_role"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface focus:outline-none focus:border-primary transition-all duration-200"            >
              <option value="customer">Độc giả</option>
              <option value="author">Tác giả</option>
              <option value="vendor">Nhà bán</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Password -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">lock</span>
              Mật khẩu tự chọn
            </label>
            <input              v-model="googleRegForm.password"
              required
              type="password"
              placeholder="Tối thiểu 8 ký tự"
              autocomplete="new-password"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>

          <!-- Password Confirmation -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">verified_user</span>
              Xác nhận mật khẩu
            </label>
            <input              v-model="googleRegForm.password_confirmation"
              required
              type="password"
              placeholder="••••••••"
              autocomplete="new-password"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>
        </div>

        <button          type="submit"
          :disabled="loading"
          class="w-full h-12 bg-primary text-on-primary font-semibold rounded-xl hover:bg-primary-container hover:shadow-lg active:scale-[0.98] transition-all duration-200 flex justify-center items-center gap-2 cursor-pointer mt-4"
        >
          <template v-if="loading">
            <span class="pi pi-spin pi-spinner text-sm"></span>
            <span>Đang thiết lập tài khoản...</span>
          </template>
          <template v-else>
            <span>Hoàn tất đăng ký</span>
            <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
          </template>
        </button>
      </form>
    </Dialog>

    <!-- Phone OTP Verification Dialog -->
    <Dialog      v-model:visible="phoneDialogVisible"      header="Đăng nhập / Đăng ký bằng Số điện thoại"      :modal="true"      class="!max-w-md !w-[90vw] !rounded-[24px] !bg-surface-container-lowest"
    >
      <div class="flex flex-col gap-6 py-4">
        <p class="text-xs text-on-surface-variant leading-relaxed">Nhập số điện thoại của bạn để nhận mã xác thực OTP gửi qua SMS:</p>
        <div class="flex flex-col gap-4">
          <!-- Phone Input Section -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">Số điện thoại</label>
            <div class="flex gap-2">
              <input                v-model="phoneInput"                placeholder="Ví dụ: 0989999999"                :disabled="otpSent"
                class="flex-grow h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary transition-all w-[60%]"
              />
              <button                type="button"
                @click="handleSendOtp"
                :disabled="otpSent || loading"
                class="flex-grow h-11 bg-primary text-on-primary rounded-xl font-bold text-xs uppercase tracking-widest cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed hover:bg-primary-container"
              >
                Gửi OTP
              </button>
            </div>
          </div>

          <!-- OTP Verify Section -->
          <div v-if="otpSent" class="flex flex-col gap-2 animate-in fade-in slide-in-from-top-4 duration-300">
            <div class="flex justify-between items-center">
              <label class="text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">Mã xác thực OTP (8 chữ số)</label>
            </div>
            <OtpCodeInput v-model="otpInput" :length="8" :disabled="loading" />
            <button              type="button"
              @click="handleVerifyOtp"
              :disabled="loading"
              class="w-full h-11 bg-primary text-on-primary rounded-xl font-bold text-xs uppercase tracking-widest cursor-pointer hover:bg-primary-container mt-2"
            >
              Xác nhận OTP
            </button>
          </div>
        </div>
      </div>
    </Dialog>

    <!-- Phone Complete Registration Dialog -->
    <Dialog      v-model:visible="phoneRegDialogVisible"      header="Hoàn tất đăng ký bằng Số điện thoại"      :modal="true"      class="!max-w-xl !w-[90vw] !rounded-[24px] !bg-surface-container-lowest"
    >
      <form @submit.prevent="handlePhoneRegister" class="flex flex-col gap-5 py-4">
        <div class="bg-primary/5 p-4 rounded-xl border border-primary/10 flex items-center gap-3">
          <span class="material-symbols-outlined text-primary text-2xl">verified_user</span>
          <div>
            <div class="text-xs font-bold text-primary uppercase tracking-wide">Số điện thoại đã xác minh</div>
            <div class="text-xs text-on-surface-variant mt-0.5">Số điện thoại <strong>{{ phoneInput }}</strong> đã xác thực thành công. Vui lòng thiết lập thông tin.</div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Phone (Read-only) -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">phone</span>
              Số điện thoại
            </label>
            <input              v-model="phoneRegForm.phone"
              disabled
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface-variant opacity-60 cursor-not-allowed"            />
          </div>

          <!-- Name -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">person</span>
              Họ và tên
            </label>
            <input              v-model="phoneRegForm.name"
              required
              placeholder="Họ và tên"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Email (Optional) -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">mail</span>
              Email (Không bắt buộc)
            </label>
            <input              v-model="phoneRegForm.email"
              type="email"
              placeholder="name@example.com"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>

          <!-- Gender -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">wc</span>
              Giới tính
            </label>
            <select              v-model="phoneRegForm.gender"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface focus:outline-none focus:border-primary transition-all duration-200"            >
              <option value="male">Nam</option>
              <option value="female">Nữ</option>
              <option value="other">Khác</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Birthday -->
          <div class="flex flex-col gap-1.5 col-span-1">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">cake</span>
              Ngày sinh
            </label>
            <input              v-model="phoneRegForm.birthday"
              type="date"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>

          <!-- Desired Role -->
          <div class="flex flex-col gap-1.5 col-span-1">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">group</span>
              Vai trò đăng ký
            </label>
            <select              v-model="phoneRegForm.desired_role"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface focus:outline-none focus:border-primary transition-all duration-200"            >
              <option value="customer">Độc giả</option>
              <option value="author">Tác giả</option>
              <option value="vendor">Nhà bán</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Password -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">lock</span>
              Mật khẩu tự chọn
            </label>
            <input              v-model="phoneRegForm.password"
              required
              type="password"
              placeholder="Tối thiểu 8 ký tự"
              autocomplete="new-password"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>

          <!-- Password Confirmation -->
          <div class="flex flex-col gap-1.5">
            <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide">
              <span class="material-symbols-outlined text-[18px] text-primary/80">verified_user</span>
              Xác nhận mật khẩu
            </label>
            <input              v-model="phoneRegForm.password_confirmation"
              required
              type="password"
              placeholder="••••••••"
              autocomplete="new-password"
              class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary transition-all duration-200"            />
          </div>
        </div>

        <button          type="submit"
          :disabled="loading"
          class="w-full h-12 bg-primary text-on-primary font-semibold rounded-xl hover:bg-primary-container hover:shadow-lg active:scale-[0.98] transition-all duration-200 flex justify-center items-center gap-2 cursor-pointer mt-4"
        >
          <template v-if="loading">
            <span class="pi pi-spin pi-spinner text-sm"></span>
            <span>Đang thiết lập tài khoản...</span>
          </template>
          <template v-else>
            <span>Hoàn tất đăng ký</span>
            <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
          </template>
        </button>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import { getPostLoginRedirect } from '@/router/guard.js'
import OtpCodeInput from '@/components/auth/OtpCodeInput.vue'
import { requestFacebookLogin } from '@/services/facebookAuth'

const email = ref('')
const password = ref('')
const remember = ref(false)
const loading = ref(false)
const showPassword = ref(false)

const googleRegDialogVisible = ref(false)
const socialProviderLabel = ref('Google')
const socialEmailLocked = ref(true)

const googleRegForm = reactive({
  challenge_token: '',
  name: '',
  email: '',
  gender: 'male',
  birthday: '',
  desired_role: 'customer',
  password: '',
  password_confirmation: ''
})

const phoneDialogVisible = ref(false)
const phoneRegDialogVisible = ref(false)
const otpSent = ref(false)
const phoneInput = ref('')
const otpInput = ref('')

const phoneRegForm = reactive({
  name: '',
  phone: '',
  email: '',
  gender: 'male',
  birthday: '',
  desired_role: 'customer',
  password: '',
  password_confirmation: ''
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()


let googleSdkPromise = null
const loadGoogleSdk = () => {
  if (window.google?.accounts?.id) {
    return Promise.resolve(window.google)
  }
  if (googleSdkPromise) return googleSdkPromise
  googleSdkPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.defer = true
    script.onload = () => {
      if (window.google?.accounts?.id) resolve(window.google)
      else reject(new Error('Google Identity Services SDK không khởi tạo được.'))
    }
    script.onerror = () => reject(new Error('Không thể tải Google Identity Services SDK.'))
    document.head.appendChild(script)
  })
  return googleSdkPromise
}

const handleGoogleCredentialResponse = async (response) => {
  if (!response?.credential) return
  loading.value = true
  try {
    const res = await authStore.loginWithGoogle({
      id_token: response.credential
    })

    if (res.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng nhập Google thành công!', life: 3000 })
      setTimeout(() => {
        router.push(getPostLoginRedirect(route))
      }, 500)
    } else if (res.status === 'needs_registration') {
      socialProviderLabel.value = 'Google'
      socialEmailLocked.value = true
      googleRegForm.challenge_token = res.data.challenge_token
      googleRegForm.email = res.data.email
      googleRegForm.name = res.data.name
      googleRegForm.gender = 'male'
      googleRegForm.birthday = ''
      googleRegForm.desired_role = 'customer'
      googleRegForm.password = ''
      googleRegForm.password_confirmation = ''
      googleRegDialogVisible.value = true
    }
  } catch (error) {
    let errorMessage = error.response?.data?.message || 'Token Google không hợp lệ hoặc đã hết hạn.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: errorMessage, life: 3000 })
  } finally {
    loading.value = false
  }
}

const openGoogleLogin = async () => {
  const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID
  if (!clientId) {
    toast.add({
      severity: 'error',
      summary: 'Cấu hình chưa sẵn sàng',
      detail: 'Chưa cấu hình Google Client ID (VITE_GOOGLE_CLIENT_ID). Vui lòng kiểm tra file .env.',
      life: 4000
    })
    return
  }
  try {
    const google = await loadGoogleSdk()
    google.accounts.id.initialize({
      client_id: clientId,
      callback: handleGoogleCredentialResponse,
      auto_select: false,
    })
    google.accounts.id.prompt()
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi Google GIS SDK',
      detail: e.message || 'Không thể tải Google Identity Services SDK.',
      life: 4000
    })
  }
}

const openFacebookLogin = async () => {
  const appId = import.meta.env.VITE_FACEBOOK_APP_ID
  const graphVersion = import.meta.env.VITE_FACEBOOK_GRAPH_VERSION

  if (!appId || !graphVersion) {
    toast.add({
      severity: 'error',
      summary: 'Cấu hình chưa sẵn sàng',
      detail: 'Chưa cấu hình Facebook App ID hoặc Graph API version.',
      life: 4000
    })
    return
  }

  loading.value = true
  try {
    const accessToken = await requestFacebookLogin(appId, graphVersion)
    const res = await authStore.loginWithFacebook({ access_token: accessToken })

    if (res.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng nhập Facebook thành công!', life: 3000 })
      setTimeout(() => {
        router.push(getPostLoginRedirect(route))
      }, 500)
    } else if (res.status === 'needs_registration') {
      socialProviderLabel.value = 'Facebook'
      socialEmailLocked.value = Boolean(res.data.email)
      googleRegForm.challenge_token = res.data.challenge_token
      googleRegForm.email = res.data.email || ''
      googleRegForm.name = res.data.name || ''
      googleRegForm.gender = 'male'
      googleRegForm.birthday = ''
      googleRegForm.desired_role = 'customer'
      googleRegForm.password = ''
      googleRegForm.password_confirmation = ''
      googleRegDialogVisible.value = true
    }
  } catch (error) {
    const errorMessage = error.response?.data?.message || error.message || 'Đăng nhập Facebook không thành công.'
    toast.add({ severity: 'error', summary: 'Lỗi Facebook Login', detail: errorMessage, life: 4000 })
  } finally {
    loading.value = false
  }
}

const handleSendOtp = async () => {
  const cleanedPhone = phoneInput.value ? phoneInput.value.replace(/[^0-9]/g, '') : ''
  if (!cleanedPhone || !/^0[35789]\d{8}$/.test(cleanedPhone)) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Số điện thoại không đúng định dạng 10 chữ số tại Việt Nam.', life: 3000 })
    return
  }
  loading.value = true
  try {
    await authStore.sendPhoneOtp(cleanedPhone)
    otpSent.value = true
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Mã OTP đã được gửi đến số điện thoại của bạn!', life: 3000 })
  } catch (error) {
    let errorMessage = error.response?.data?.message || 'Gửi OTP không thành công, vui lòng thử lại.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: errorMessage, life: 3000 })
  } finally {
    loading.value = false
  }
}

const handleVerifyOtp = async () => {
  const cleanedPhone = phoneInput.value ? phoneInput.value.replace(/[^0-9]/g, '') : ''
  if (!otpInput.value || !/^\d{8}$/.test(otpInput.value)) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập đúng 8 chữ số mã OTP.', life: 3000 })
    return
  }
  loading.value = true
  try {
    const res = await authStore.verifyPhoneOtp(cleanedPhone, otpInput.value)
    if (res.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng nhập thành công!', life: 3000 })
      phoneDialogVisible.value = false
      setTimeout(() => {
        router.push(getPostLoginRedirect(route))
      }, 500)
    } else if (res.status === 'needs_registration') {
      phoneDialogVisible.value = false
      phoneRegForm.phone = res.data.phone
      phoneRegForm.name = ''
      phoneRegForm.email = ''
      phoneRegForm.gender = 'male'
      phoneRegForm.birthday = ''
      phoneRegForm.desired_role = 'customer'
      phoneRegForm.password = ''
      phoneRegForm.password_confirmation = ''
      phoneRegDialogVisible.value = true
    }
  } catch (error) {
    let errorMessage = error.response?.data?.message || 'Mã OTP không chính xác hoặc đã hết hạn.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: errorMessage, life: 3000 })
  } finally {
    loading.value = false
  }
}

const handlePhoneRegister = async () => {
  if (phoneRegForm.password !== phoneRegForm.password_confirmation) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Mật khẩu xác nhận không khớp.', life: 3000 })
    return
  }
  loading.value = true
  try {
    await authStore.register({ ...phoneRegForm })
    phoneRegDialogVisible.value = false
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng ký tài khoản bằng số điện thoại thành công!', life: 3000 })
    setTimeout(() => {
      router.push(getPostLoginRedirect(route))
    }, 500)
  } catch (error) {
    let errorMessage = error.response?.data?.message || 'Đăng ký không thành công, vui lòng thử lại.'
    toast.add({ severity: 'error', summary: 'Lỗi đăng ký', detail: errorMessage, life: 3000 })
  } finally {
    loading.value = false
  }
}

const handleGoogleRegister = async () => {
  if (googleRegForm.password !== googleRegForm.password_confirmation) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Mật khẩu xác nhận không khớp.', life: 3000 })
    return
  }
  loading.value = true
  try {
    await authStore.register({
      challenge_token: googleRegForm.challenge_token,
      name: googleRegForm.name,
      password: googleRegForm.password,
      password_confirmation: googleRegForm.password_confirmation,
      gender: googleRegForm.gender,
      birthday: googleRegForm.birthday,
      desired_role: googleRegForm.desired_role
    })
    googleRegDialogVisible.value = false
    toast.add({ severity: 'success', summary: 'Thành công', detail: `Đăng ký tài khoản ${socialProviderLabel.value} thành công!`, life: 3000 })
    setTimeout(() => {
      router.push(getPostLoginRedirect(route))
    }, 500)
  } catch (error) {
    let errorMessage = error.response?.data?.message || 'Đăng ký không thành công, vui lòng thử lại.'
    toast.add({ severity: 'error', summary: 'Lỗi đăng ký', detail: errorMessage, life: 3000 })
  } finally {
    loading.value = false
  }
}

const handleLogin = async () => {
  if (!email.value || !password.value) return
  loading.value = true
  try {
    await authStore.login({      email: email.value,      password: password.value,
      remember: remember.value
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng nhập thành công!', life: 3000 })
    // Redirect sau khi đăng nhập thành công
    setTimeout(() => {
      router.push(getPostLoginRedirect(route))
    }, 500)
  } catch (error) {
    console.error('Login error:', error)
    let errorMessage = 'Sai email hoặc mật khẩu, vui lòng thử lại.'
    if (error.response?.data?.message) {
        errorMessage = error.response.data.message
    }
    toast.add({ severity: 'error', summary: 'Lỗi đăng nhập', detail: errorMessage, life: 3000 })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* Smooth focus state */
input:focus {
  transform: translateY(-1px);
}

.animate-in {
  animation-duration: 0.6s;
  animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes zoom-in {
  from { transform: scale(0.95) translateY(20px); }
  to { transform: scale(1) translateY(0); }
}

.animate-in.fade-in { animation-name: fade-in; }
.animate-in.zoom-in { animation-name: zoom-in; }
</style>
