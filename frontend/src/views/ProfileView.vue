<template>
  <div class="min-h-screen bg-background">
    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row items-stretch gap-xl">
      <!-- Left Sidebar with Context Switcher -->
      <UserSidebar :user="authStore.user" @avatar-click="$refs.avatarInput.click()" />
      <input type="file" ref="avatarInput" class="hidden" accept="image/*" @change="onAvatarSelected" />

      <!-- Right Main Content Area -->
      <main class="flex-1 min-w-0 w-full flex flex-col" aria-labelledby="profile-title">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
          <!-- Header Banner (Clean, no redundant buttons) -->
          <div class="p-lg md:p-xl border-b border-outline-variant/10 bg-gradient-to-r from-surface-container-low to-surface-container-lowest flex items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <h1 id="profile-title" class="text-2xl font-black text-on-surface tracking-tight">Thông tin cá nhân</h1>
                <span                  class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                  :class="{
                    'bg-slate-900 text-amber-300': authStore.isAdmin,
                    'bg-indigo-600 text-white': authStore.isVendor,
                    'bg-surface-container-high text-outline': !authStore.isAdmin && !authStore.isVendor
                  }"
                >
                  {{ roleLabel }}
                </span>
              </div>
              <p class="text-xs text-on-surface-variant font-medium">Quản lý thông tin tài khoản, sở thích đọc sách và bảo mật của bạn.</p>
            </div>
          </div>

          <!-- Navigation Tabs -->
          <div class="px-md pt-md flex gap-md overflow-x-auto no-scrollbar border-b border-outline-variant/10 shrink-0" role="tablist" aria-label="Nhóm cài đặt tài khoản">
            <button              v-for="tab in tabs"              :key="tab.id"
              type="button"
              role="tab"
              :aria-selected="activeTab === tab.id"
              :aria-controls="`profile-panel-${tab.id}`"
              @click="switchTab(tab.id)"
              class="min-h-11 px-lg py-md text-sm font-bold transition-colors border-none bg-transparent cursor-pointer relative whitespace-nowrap"
              :class="activeTab === tab.id ? 'text-primary' : 'text-outline hover:text-on-surface'"
            >
              {{ tab.label }}
              <div v-if="activeTab === tab.id" class="absolute bottom-0 left-0 right-0 h-1 bg-primary rounded-t-full"></div>
            </button>
          </div>

          <div class="p-lg md:p-xl flex-1">
            <!-- TAB 1: THÔNG TIN CHUNG & SỞ THÍCH ĐỌC SÁCH -->
            <div v-if="activeTab === 'general'" id="profile-panel-general" role="tabpanel" class="animate-fade-in space-y-8">
              <form @submit.prevent="handleUpdateInfo" class="space-y-8">
                <!-- KHU VỰC 1: THÔNG TIN CÁ NHÂN CƠ BẢN (Icons removed inside form inputs) -->
                <div class="space-y-4">
                  <div class="border-b border-outline-variant/10 pb-3">
                    <h2 class="text-base font-bold text-on-surface">1. Thông tin cá nhân cơ bản</h2>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Email (Read-only) -->
                    <div class="space-y-2">
                      <label for="profile-email" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Email (Không thể chỉnh sửa)</label>
                      <InputText id="profile-email" v-model="infoForm.email" disabled class="w-full !px-4 !rounded-2xl !bg-surface-container-high !border-none !text-outline !h-11 text-sm font-medium" />
                    </div>

                    <!-- Họ và tên -->
                    <div class="space-y-2">
                      <label for="profile-name" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Họ và tên <span class="text-red-500">*</span></label>
                      <InputText id="profile-name" v-model="infoForm.name" autocomplete="name" placeholder="Nhập họ và tên..." class="w-full !px-4 !rounded-2xl !border-outline-variant/40 !h-11 text-sm" required />
                    </div>

                    <!-- Số điện thoại -->
                    <div class="space-y-2">
                      <label for="profile-phone" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Số điện thoại</label>
                      <InputText id="profile-phone" v-model="infoForm.phone" autocomplete="tel" inputmode="tel" placeholder="Ví dụ: 0989999999" class="w-full !px-4 !rounded-2xl !border-outline-variant/40 !h-11 text-sm" />
                    </div>

                    <!-- Giới tính -->
                    <div class="space-y-2">
                      <label for="profile-gender" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Giới tính</label>
                      <select id="profile-gender" v-model="infoForm.gender" class="w-full h-11 px-4 bg-surface-container-lowest border border-outline-variant/40 rounded-2xl text-sm text-on-surface focus:outline-none focus:border-primary transition-all cursor-pointer"
                      >
                        <option value="male">Nam</option>
                        <option value="female">Nữ</option>
                        <option value="other">Khác</option>
                      </select>
                    </div>

                    <!-- Ngày sinh (Validation <= Today) -->
                    <div class="space-y-2">
                      <div class="flex justify-between items-center">
                        <label for="profile-birthday" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Ngày sinh</label>
                        <span class="text-xs text-outline">Dùng để cá nhân hóa gợi ý sách</span>
                      </div>
                      <input id="profile-birthday" type="date"
                        v-model="infoForm.birthday"                        :max="todayDate"
                        class="w-full h-11 px-4 bg-surface-container-lowest border border-outline-variant/40 rounded-2xl text-sm text-on-surface focus:outline-none focus:border-primary transition-all cursor-pointer"
                      />
                    </div>

                    <!-- Địa chỉ cá nhân -->
                    <div class="space-y-2 md:col-span-2">
                      <label for="profile-address" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Địa chỉ cá nhân</label>
                      <Textarea id="profile-address" v-model="infoForm.address" rows="2" autocomplete="street-address" placeholder="Nhập địa chỉ nhà của bạn..." class="w-full !px-4 !py-3 !rounded-2xl !border-outline-variant/40 resize-none text-sm" />
                    </div>
                    <div class="md:col-span-2 flex min-h-11 items-center gap-3 p-4 rounded-2xl bg-surface-container-low border border-outline-variant/20">
                      <Checkbox v-model="infoForm.marketing_consent" :binary="true" inputId="marketing_consent" />
                      <label for="marketing_consent" class="flex min-h-11 flex-col justify-center text-sm text-on-surface-variant leading-relaxed cursor-pointer"><strong class="text-on-surface">Nhận thông tin ưu đãi</strong>KomiBook chỉ đưa tài khoản vào chiến dịch marketing khi bạn chủ động đồng ý. Bạn có thể rút lại lựa chọn này bất cứ lúc nào.</label>
                    </div>
                  </div>
                </div>

                <!-- KHU VỰC 2: KHẢO SÁT SỞ THÍCH ĐỌC SÁCH (COLD START RECOMMENDATIONS) -->
                <div class="space-y-4 pt-4 border-t border-outline-variant/10">
                  <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 border-b border-outline-variant/10 pb-3">
                    <div>
                      <h2 class="text-base font-bold text-on-surface">2. Khảo sát sở thích đọc sách (Cold Start Recommendations)</h2>
                      <p class="text-xs text-outline">Chọn từ 3 đến 5 thể loại yêu thích để KomiBook đề xuất đúng gu đọc của bạn.</p>
                    </div>

                    <!-- Selected Badge Counter -->
                    <div                      class="px-3 py-1 rounded-full text-xs font-bold self-start md:self-auto shadow-xs border"
                      :class="selectedCategoryIds.length >= 3 && selectedCategoryIds.length <= 5                        ? 'bg-emerald-50 text-emerald-700 border-emerald-300'                        : 'bg-amber-50 text-amber-800 border-amber-300'"
                    >
                      Đã chọn {{ selectedCategoryIds.length }}/5 thể loại
                    </div>
                  </div>

                  <!-- Selectable Category Chips Grid -->
                  <div v-if="loadingCategories" class="py-4 flex justify-center">
                    <span class="pi pi-spin pi-spinner text-xl text-primary"></span>
                  </div>

                  <div v-else class="flex flex-wrap gap-2.5 p-4 bg-surface-container-low/40 rounded-2xl border border-outline-variant/20">
                    <button
                      v-for="cat in availableCategories"
                      :key="cat.id"
                      type="button"
                      @click="toggleCategoryPreference(cat.id)"
                      class="min-h-11 px-3.5 py-2 rounded-xl text-sm font-bold transition-colors duration-200 cursor-pointer flex items-center gap-1.5 border"
                      :class="selectedCategoryIds.includes(cat.id)
                        ? 'bg-gradient-to-r from-primary to-secondary text-white border-transparent shadow-md scale-[1.03]'
                        : 'bg-white text-slate-700 border-slate-200 hover:border-primary/50 hover:bg-slate-50'"
                    >
                      <span>{{ cat.name }}</span>
                    </button>
                  </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 flex justify-end">
                  <button                    type="submit"
                    :disabled="loadingInfo"
                    class="bg-primary text-on-primary px-8 py-3 rounded-xl text-sm font-bold shadow-md hover:bg-primary/90 transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2 border-none cursor-pointer"
                  >
                    <span v-if="loadingInfo" class="pi pi-spin pi-spinner text-xs"></span>
                    <span>Cập nhật thông tin & Sở thích</span>
                  </button>
                </div>

              </form>
            </div>

            <!-- TAB 2: HẠNG VIP & QUYỀN LỢI -->
            <div v-if="activeTab === 'membership'" id="profile-panel-membership" role="tabpanel" class="animate-fade-in space-y-lg">
              <div class="p-6 rounded-3xl text-white relative overflow-hidden shadow-xl"
                :class="authStore.user?.membership_tier ? 'bg-gradient-to-br from-amber-500 via-yellow-600 to-amber-700' : 'bg-gradient-to-br from-slate-600 to-slate-800'"
              >
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
                <div class="absolute right-10 top-10 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>

                <div class="flex justify-between items-start relative z-10">
                  <div>
                    <span class="text-xs uppercase tracking-widest font-black opacity-80">Thẻ Thành Viên</span>
                    <h2 class="text-2xl font-black mt-1">{{ authStore.user?.membership_tier?.name || 'Khách hàng Thân thiết' }}</h2>
                  </div>
                </div>

                <div class="mt-8 flex justify-between items-end relative z-10">
                  <div>
                    <span class="text-[11px] uppercase tracking-wider opacity-70">Điểm tích lũy</span>
                    <div class="text-xl font-bold mt-0.5">{{ authStore.user?.points || 0 }} <span class="text-xs opacity-80">KomiPoints</span></div>
                  </div>
                  <div class="text-right">
                    <span class="text-[11px] uppercase tracking-wider opacity-70">Ưu đãi giảm giá</span>
                    <div class="text-xl font-black mt-0.5">{{ authStore.user?.membership_tier?.discount_percent || 0 }}%</div>
                  </div>
                </div>
              </div>

              <!-- VIP Benefits List -->
              <div class="bg-surface-container-low p-6 rounded-2xl border border-outline-variant/20 space-y-4">
                <h3 class="font-bold text-on-surface">Quyền lợi đặc quyền của bạn</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <h4 class="text-sm font-bold text-on-surface">Chiết khấu trực tiếp</h4>
                    <p class="text-xs text-on-surface-variant">Giảm giá {{ authStore.user?.membership_tier?.discount_percent || 0 }}% trực tiếp trên mỗi hóa đơn khi thanh toán.</p>
                  </div>
                  <div>
                    <h4 class="text-sm font-bold text-on-surface">Tích lũy điểm tự động</h4>
                    <p class="text-xs text-on-surface-variant">Nhận 1 điểm tích lũy cho mỗi 10.000 VNĐ chi tiêu khi đơn hàng giao thành công.</p>
                  </div>
                </div>

                <div v-if="authStore.user?.membership_tier?.benefits" class="p-4 bg-primary/5 rounded-xl border border-primary/10 mt-2">
                  <p class="text-xs text-primary font-bold">Lợi ích bổ sung: {{ authStore.user?.membership_tier?.benefits }}</p>
                </div>
              </div>
            </div>

            <!-- TAB 3: KHU VỰC 4 - QUẢN LÝ SỔ ĐỊA CHỈ -->
            <div v-if="activeTab === 'addresses'" id="profile-panel-addresses" role="tabpanel" class="animate-fade-in space-y-md">
              <div class="flex justify-between items-center mb-lg">
                <h3 class="text-lg font-bold text-on-surface">Sổ địa chỉ nhận hàng</h3>
                <button type="button" @click="openAddressModal()" class="min-h-11 bg-primary text-on-primary px-4 py-2.5 rounded-xl font-bold text-sm hover:opacity-90 transition-colors border-none cursor-pointer shadow-xs">
                  Thêm địa chỉ mới
                </button>
              </div>

              <div v-if="loadingAddresses" class="py-xl flex justify-center">
                <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
              </div>
              <div v-else-if="addresses.length === 0" class="py-xl text-center space-y-md">
                <p class="text-sm text-outline font-medium">Bạn chưa có địa chỉ giao hàng nào.</p>
              </div>

              <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <div v-for="addr in addresses" :key="addr.id" class="p-lg rounded-2xl border-2 transition-all group" :class="addr.is_default ? 'border-primary bg-primary-container/5' : 'border-outline-variant/20 hover:border-outline-variant/60'">
                  <div class="flex justify-between items-start mb-md">
                    <div class="flex flex-col">
                      <div class="flex items-center gap-2 mb-1">
                        <span class="font-bold text-on-surface">{{ addr.receiver_name }}</span>
                        <span v-if="addr.is_default" class="px-2 py-0.5 bg-primary text-on-primary text-[10px] font-black uppercase tracking-wider rounded-md">Mặc định</span>
                      </div>
                      <span class="text-xs text-on-surface-variant font-bold">
                        {{ addr.phone }}
                      </span>
                    </div>
                    <div class="flex gap-2 opacity-100">
                      <button type="button" @click="openAddressModal(addr)" class="min-h-11 px-3 py-2 rounded-lg bg-surface-container-high text-sm font-bold text-on-surface-variant hover:text-primary transition-colors border-none cursor-pointer">
                        Sửa
                      </button>
                      <button v-if="!addr.is_default" type="button" @click="confirmDeleteAddress(addr.id)" class="min-h-11 px-3 py-2 rounded-lg bg-surface-container-high text-sm font-bold text-on-surface-variant hover:text-error transition-colors border-none cursor-pointer">
                        Xóa
                      </button>
                    </div>
                  </div>
                  <p class="text-sm text-on-surface-variant leading-relaxed line-clamp-2">{{ addr.address }}</p>
                  <button v-if="!addr.is_default" type="button" @click="setDefaultAddress(addr.id)" class="mt-md min-h-11 text-sm font-bold text-secondary hover:underline bg-transparent border-none cursor-pointer">Đặt làm mặc định</button>
                </div>
              </div>
            </div>

            <!-- TAB 4: KHU VỰC 5 - ĐỔI MẬT KHẨU BẢO MẬT (Strict Anti-Autofill & Empty Defaults) -->
            <div v-if="activeTab === 'security'" id="profile-panel-security" role="tabpanel" class="w-full block">
              <div class="animate-fade-in py-lg flex flex-col items-center">
                <div class="w-full max-w-[480px]">
                  <div class="text-center mb-xl space-y-1">
                    <h2 class="text-xl font-black text-on-surface">Thay đổi mật khẩu</h2>
                    <p class="text-xs text-on-surface-variant font-medium">Để bảo mật tài khoản, vui lòng tự nhập mật khẩu hiện tại trước khi tạo mật khẩu mới.</p>
                  </div>

                  <!-- Anti-Autofill Form with Dummy Hidden Fields -->
                  <form @submit.prevent="handleUpdatePassword" autocomplete="off" action="javascript:void(0);" class="space-y-6">
                    <!-- Dummy hidden inputs to stop browser password managers from auto-injecting saved passwords -->
                    <input type="text" class="hidden" name="prevent_autofill_username" tabindex="-1" autocomplete="off" />
                    <input type="password" class="hidden" name="prevent_autofill_password" tabindex="-1" autocomplete="new-password" />

                    <!-- Mật khẩu hiện tại -->
                    <div class="flex flex-col gap-2">
                      <label for="current-password" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Mật khẩu hiện tại <span class="text-red-500">*</span></label>
                      <Password inputId="current-password" v-model="passwordForm.current_password" toggleMask autocomplete="current-password"
                        placeholder="Nhập mật khẩu hiện tại"                        class="w-full"                        inputClass="w-full !rounded-2xl !border-outline-variant/40 !py-3 !px-4 text-sm"                        :feedback="false"                        required
                      />
                    </div>

                    <!-- Mật khẩu mới -->
                    <div class="flex flex-col gap-2">
                      <label for="new-password" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Mật khẩu mới <span class="text-red-500">*</span></label>
                      <Password inputId="new-password" v-model="passwordForm.new_password" toggleMask autocomplete="new-password"
                        placeholder="Tối thiểu 8 ký tự"                        class="w-full"                        inputClass="w-full !rounded-2xl !border-outline-variant/40 !py-3 !px-4 text-sm"                        required
                      />
                    </div>

                    <!-- Xác nhận mật khẩu mới -->
                    <div class="flex flex-col gap-2">
                      <label for="confirm-password" class="text-xs font-bold text-on-surface-variant ml-1 uppercase tracking-wider">Xác nhận mật khẩu mới <span class="text-red-500">*</span></label>
                      <Password inputId="confirm-password" v-model="passwordForm.new_password_confirmation" toggleMask autocomplete="new-password"
                        placeholder="Nhập lại mật khẩu mới"                        class="w-full"                        inputClass="w-full !rounded-2xl !border-outline-variant/40 !py-3 !px-4 text-sm"                        :feedback="false"                        required
                      />
                    </div>

                    <div class="pt-6">
                      <button                        type="submit"
                        :disabled="loadingPassword"
                        class="w-full bg-primary text-on-primary px-5 py-2.5 rounded-xl text-xs font-bold shadow-md hover:bg-primary/90 transition-all active:scale-95 disabled:opacity-50 flex items-center justify-center gap-2 border-none cursor-pointer"
                      >
                        <span v-if="loadingPassword" class="pi pi-spin pi-spinner text-xs"></span>
                        <span>Xác nhận cập nhật mật khẩu</span>
                      </button>
                    </div>
                  </form>

                  <div class="mt-10 pt-8 border-t border-outline-variant/20">
                    <div class="flex items-center justify-between gap-4 mb-4">
                      <div>
                        <h3 class="text-base font-black text-on-surface">Phiên đăng nhập</h3>
                        <p class="text-xs text-on-surface-variant">Kiểm tra và thu hồi thiết bị không còn sử dụng.</p>
                      </div>
                      <button type="button" @click="fetchSessions" class="text-xs font-bold text-primary bg-transparent border-none cursor-pointer">Làm mới</button>
                    </div>
                    <div v-if="loadingSessions" class="py-6 text-center text-sm text-outline">Đang tải...</div>
                    <div v-else-if="sessions.length === 0" class="py-6 text-center text-sm text-outline">Không có phiên database nào đang hoạt động.</div>
                    <div v-else class="space-y-3">
                      <div v-for="session in sessions" :key="session.id" class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/20 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                          <div class="text-sm font-bold text-on-surface">{{ session.is_current ? 'Thiết bị hiện tại' : 'Thiết bị khác' }}</div>
                          <div class="text-xs text-outline truncate mt-1">{{ session.user_agent || 'Không rõ trình duyệt' }}</div>
                          <div class="text-[11px] text-outline mt-1">{{ session.ip_address || 'Không rõ IP' }} · {{ formatSessionTime(session.last_active_at) }}</div>
                        </div>
                        <button type="button" @click="revokeSession(session)" class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold text-error bg-error/10 border-none cursor-pointer">Thu hồi</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </main>
    </div>

    <!-- Address Modal -->
    <Dialog v-model:visible="addressDialog" :header="isEditAddress ? 'Cập nhật địa chỉ' : 'Thêm địa chỉ mới'" :modal="true" class="!rounded-3xl !border-none !shadow-2xl" :style="{width: '450px'}">
      <div class="flex flex-col gap-6 mt-4">
        <div class="space-y-2">
          <label for="address-receiver-name" class="text-sm font-bold text-on-surface-variant ml-1">Tên người nhận</label>
          <InputText id="address-receiver-name" v-model="addressForm.receiver_name" autocomplete="name" placeholder="Ví dụ: Nguyễn Văn A" class="w-full !min-h-11 !rounded-xl !border-outline-variant/40" autofocus />
        </div>
        <div class="space-y-2">
          <label for="address-phone" class="text-sm font-bold text-on-surface-variant ml-1">Số điện thoại</label>
          <InputText id="address-phone" v-model="addressForm.phone" autocomplete="tel" inputmode="tel" placeholder="Ví dụ: 0901234567" class="w-full !min-h-11 !rounded-xl !border-outline-variant/40" />
        </div>
        <div class="space-y-2">
          <label for="address-detail" class="text-sm font-bold text-on-surface-variant ml-1">Địa chỉ chi tiết</label>
          <Textarea id="address-detail" v-model="addressForm.address" rows="3" autocomplete="street-address" placeholder="Số nhà, Tên đường..." class="w-full !rounded-xl !border-outline-variant/40 resize-none" />
        </div>
        <div class="flex min-h-11 items-center gap-3 p-md bg-surface-container-low rounded-2xl border border-outline-variant/20">
          <Checkbox v-model="addressForm.is_default" :binary="true" inputId="is_default" />
          <label for="is_default" class="flex min-h-11 items-center text-sm font-bold text-on-surface cursor-pointer">Đặt làm địa chỉ mặc định</label>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-md">
          <button @click="addressDialog = false" class="px-4 py-2 rounded-xl text-xs font-bold text-outline hover:bg-surface-container-high transition-all border-none bg-transparent cursor-pointer">Hủy</button>
          <button @click="saveAddress" :disabled="savingAddress" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-primary text-on-primary shadow-md hover:bg-primary/90 transition-all active:scale-95 border-none cursor-pointer flex items-center gap-2">
            <span v-if="savingAddress" class="pi pi-spin pi-spinner text-xs"></span>
            Lưu địa chỉ
          </button>
        </div>
      </template>
    </Dialog>

    <!-- Avatar Review / Drag & Crop Modal -->
    <Dialog v-model:visible="showAvatarModal" header="Chỉnh sửa & Xem trước ảnh đại diện" :modal="true" class="!rounded-3xl !border-none !shadow-2xl" :style="{width: '450px'}">
      <div class="flex flex-col items-center text-center py-4 space-y-4">
        <p class="text-xs text-on-surface-variant font-medium">Bấm và kéo ảnh để di chuyển vị trí mong muốn vào khung tròn.</p>

        <!-- Drag Viewport Container -->
        <div          class="w-44 h-44 rounded-full overflow-hidden border-4 border-primary shadow-xl ring-4 ring-primary/20 relative cursor-grab active:cursor-grabbing select-none bg-slate-900 flex items-center justify-center"
          @mousedown="startDrag"
          @mousemove="onDrag"
          @mouseup="endDrag"
          @mouseleave="endDrag"
          @touchstart="startDrag"
          @touchmove="onDrag"
          @touchend="endDrag"
        >
          <img            :src="avatarPreviewUrl"            alt="Avatar Preview"            @load="onPreviewImgLoaded"
            class="max-w-none max-h-none pointer-events-none origin-center absolute"
            :style="{
              width: imgRenderWidth ? imgRenderWidth + 'px' : 'auto',
              height: imgRenderHeight ? imgRenderHeight + 'px' : 'auto',
              transform: `translate(${dragX}px, ${dragY}px) scale(${zoomLevel})`,
              transition: isDragging ? 'none' : 'transform 0.1s ease-out'
            }"
          />
        </div>

        <!-- Zoom Slider & Reset -->
        <div class="flex items-center gap-3 w-full max-w-[280px] bg-surface-container-low px-4 py-2 rounded-2xl border border-outline-variant/20">
          <span class="material-symbols-outlined text-sm text-outline">zoom_out</span>
          <input            type="range"            min="1"            max="3"            step="0.05"            v-model.number="zoomLevel"            class="flex-1 accent-primary cursor-pointer"
          />
          <span class="material-symbols-outlined text-sm text-outline">zoom_in</span>
          <button            type="button"            @click="resetAvatarTransform"            class="text-[10px] font-bold text-primary hover:underline ml-1 bg-transparent border-none cursor-pointer uppercase"
          >
            Đặt lại
          </button>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-2 justify-end pt-2">
          <button @click="cancelAvatarPreview" class="px-4 py-2 rounded-xl text-xs font-bold text-outline hover:bg-surface-container-high transition-all border-none bg-transparent cursor-pointer">
            Hủy bỏ
          </button>
          <button @click="confirmAvatarUpload" :disabled="uploadingAvatar" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-primary text-on-primary shadow-md hover:bg-primary/90 transition-all active:scale-95 border-none cursor-pointer flex items-center gap-2">
            <span v-if="uploadingAvatar" class="pi pi-spin pi-spinner text-xs"></span>
            Xác nhận cập nhật ảnh
          </button>
        </div>
      </template>
    </Dialog>

    <Toast />
    <ConfirmDialog />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/services/axios'

import UserSidebar from '@/components/profile/UserSidebar.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'

const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const activeTab = ref('general')
const tabs = [
  { id: 'general', label: 'Thông tin chung & Sở thích' },
  { id: 'membership', label: 'Hạng VIP & Quyền lợi' },
  { id: 'addresses', label: 'Sổ địa chỉ' },
  { id: 'security', label: 'Bảo mật & Mật khẩu' }
]

const todayDate = computed(() => {
  return new Date().toISOString().split('T')[0]
})

const roleLabel = computed(() => {
  if (authStore.isAdmin) return 'Admin'
  if (authStore.isVendor) return 'Vendor'
  return 'Độc Giả'
})

// Forms
const loadingInfo = ref(false)
const infoForm = reactive({
  email: '',
  name: '',
  phone: '',
  gender: 'male',
  birthday: '',
  address: '',
  marketing_consent: false
})

const availableCategories = ref([])
const selectedCategoryIds = ref([])
const loadingCategories = ref(false)

const loadingPassword = ref(false)
const passwordForm = reactive({ current_password: '', new_password: '', new_password_confirmation: '' })
const sessions = ref([])
const loadingSessions = ref(false)

// Addresses
const addresses = ref([])
const loadingAddresses = ref(false)
const addressDialog = ref(false)
const isEditAddress = ref(false)
const savingAddress = ref(false)
const addressForm = ref({ id: null, receiver_name: '', phone: '', address: '', is_default: false })

const resetPasswordForm = () => {
  passwordForm.current_password = ''
  passwordForm.new_password = ''
  passwordForm.new_password_confirmation = ''
}

const switchTab = (tabId) => {
  activeTab.value = tabId
  if (tabId === 'security') {
    resetPasswordForm()
    fetchSessions()
  }
  window.scrollTo({ top: 0, behavior: 'instant' })
}

const fetchCategories = async () => {
  loadingCategories.value = true
  try {
    const res = await apiClient.get('/api/categories')
    availableCategories.value = res.data.data || res.data || []
  } catch (e) {
    console.error('Failed to load categories:', e)
  } finally {
    loadingCategories.value = false
  }
}

const formatSessionTime = (value) => value ? new Date(value).toLocaleString('vi-VN') : ''

const fetchSessions = async () => {
  loadingSessions.value = true
  try {
    const response = await apiClient.get('/api/profile/sessions')
    sessions.value = response.data.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: error.response?.data?.message || 'Không thể tải phiên đăng nhập.', life: 3500 })
  } finally {
    loadingSessions.value = false
  }
}

const revokeSession = async (session) => {
  try {
    await apiClient.delete(`/api/profile/sessions/${encodeURIComponent(session.id)}`)
    if (session.is_current) {
      await authStore.logout()
      window.location.assign('/login')
      return
    }
    sessions.value = sessions.value.filter(item => item.id !== session.id)
    toast.add({ severity: 'success', summary: 'Đã thu hồi', detail: 'Phiên đăng nhập đã bị thu hồi.', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể thu hồi', detail: error.response?.data?.message || 'Vui lòng đăng nhập lại rồi thử lại.', life: 4000 })
  }
}

const populateUserData = () => {
  if (authStore.user) {
    infoForm.email = authStore.user.email || ''
    infoForm.name = authStore.user.name || ''
    infoForm.phone = authStore.user.phone || ''
    infoForm.gender = authStore.user.gender || 'male'
    infoForm.birthday = authStore.user.birthday || ''
    infoForm.address = authStore.user.address || ''
    infoForm.marketing_consent = !!authStore.user.marketing_consent

    if (authStore.user.favorite_categories && Array.isArray(authStore.user.favorite_categories)) {
      selectedCategoryIds.value = authStore.user.favorite_categories.map(c => c.id)
    }
  }
}

onMounted(() => {
  populateUserData()
  fetchCategories()
  fetchAddresses()
  resetPasswordForm()
})

const toggleCategoryPreference = (catId) => {
  const index = selectedCategoryIds.value.indexOf(catId)
  if (index > -1) {
    selectedCategoryIds.value.splice(index, 1)
  } else {
    if (selectedCategoryIds.value.length >= 5) {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Bạn chỉ có thể chọn tối đa 5 thể loại yêu thích.', life: 3000 })
      return
    }
    selectedCategoryIds.value.push(catId)
  }
}

const fetchAddresses = async () => {
  loadingAddresses.value = true
  try {
    const res = await apiClient.get('/api/profile/addresses')
    addresses.value = res.data.data
  } catch(error) { console.error(error) }
  loadingAddresses.value = false
}

const openAddressModal = (addr = null) => {
  if (addr) {
    isEditAddress.value = true
    addressForm.value = { ...addr, is_default: !!addr.is_default }
  } else {
    isEditAddress.value = false
    addressForm.value = { id: null, receiver_name: '', phone: '', address: '', is_default: false }
  }
  addressDialog.value = true
}

const saveAddress = async () => {
  if (!addressForm.value.receiver_name || !addressForm.value.phone || !addressForm.value.address) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng điền đầy đủ thông tin', life: 3000 })
    return
  }
  savingAddress.value = true
  try {
    if (isEditAddress.value) {
      await apiClient.put(`/api/profile/addresses/${addressForm.value.id}`, addressForm.value)
    } else {
      await apiClient.post('/api/profile/addresses', addressForm.value)
    }
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã lưu địa chỉ', life: 3000 })
    addressDialog.value = false
    fetchAddresses()
  } catch(e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  } finally { savingAddress.value = false }
}

const confirmDeleteAddress = (id) => {
  confirm.require({
    message: 'Bạn có chắc chắn muốn xóa địa chỉ này?',
    header: 'Xác nhận xóa',
    icon: 'pi pi-exclamation-triangle text-error',
    acceptLabel: 'Xóa',
    rejectLabel: 'Hủy',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await apiClient.delete(`/api/profile/addresses/${id}`)
        toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Xóa địa chỉ thành công', life: 3000 })
        fetchAddresses()
      } catch(e) { toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa', life: 3000 }) }
    }
  })
}

const setDefaultAddress = async (id) => {
  try {
    await apiClient.patch(`/api/profile/addresses/${id}/default`)
    fetchAddresses()
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật mặc định', life: 3000 })
  } catch(e) { toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 }) }
}

const handleUpdateInfo = async () => {
  if (infoForm.birthday && infoForm.birthday > todayDate.value) {
    toast.add({ severity: 'error', summary: 'Lỗi ngày sinh', detail: 'Ngày sinh không được vượt quá ngày hiện tại.', life: 4000 })
    return
  }

  if (infoForm.phone && !/^(0[3|5|7|8|9])+([0-9]{8})$/.test(infoForm.phone)) {
    toast.add({ severity: 'error', summary: 'Lỗi số điện thoại', detail: 'Số điện thoại không đúng định dạng Việt Nam (ví dụ 0989999999).', life: 4000 })
    return
  }

  loadingInfo.value = true
  try {
    await authStore.updateProfile({
      ...infoForm,
      favorite_category_ids: selectedCategoryIds.value
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Cập nhật thông tin & sở thích thành công!', life: 3000 })
  } catch (error) {
    console.error('Update profile error:', error)
    let msg = 'Không thể cập nhật thông tin'
    if (error.response?.data?.message) msg = error.response.data.message
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {    loadingInfo.value = false  }
}

const handleUpdatePassword = async () => {
  if (!passwordForm.current_password) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập mật khẩu hiện tại.', life: 3000 })
    return
  }
  if (!passwordForm.new_password) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập mật khẩu mới.', life: 3000 })
    return
  }
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Mật khẩu xác nhận không khớp.', life: 3000 })
    return
  }
  loadingPassword.value = true
  try {
    await authStore.updatePassword({ ...passwordForm })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đổi mật khẩu thành công!', life: 3000 })
    resetPasswordForm()
  } catch (error) {
    console.error('Update password error:', error)
    let msg = 'Mật khẩu hiện tại không chính xác'
    if (error.response?.data?.message) msg = error.response.data.message
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {    loadingPassword.value = false  }
}

const selectedAvatarFile = ref(null)
const avatarPreviewUrl = ref('')
const showAvatarModal = ref(false)
const uploadingAvatar = ref(false)

// Drag, Pan, and Zoom interactive state
const dragX = ref(0)
const dragY = ref(0)
const zoomLevel = ref(1)
const isDragging = ref(false)
const startX = ref(0)
const startY = ref(0)

const imgRenderWidth = ref(176)
const imgRenderHeight = ref(176)

const onPreviewImgLoaded = (e) => {
  const img = e.target
  const natW = img.naturalWidth || 176
  const natH = img.naturalHeight || 176
  const viewportSize = 176
  const aspect = natW / natH

  if (aspect >= 1) {
    imgRenderHeight.value = viewportSize
    imgRenderWidth.value = viewportSize * aspect
  } else {
    imgRenderWidth.value = viewportSize
    imgRenderHeight.value = viewportSize / aspect
  }

  dragX.value = 0
  dragY.value = 0
  zoomLevel.value = 1
}

const onAvatarSelected = (event) => {
  const file = event.target.files[0]
  if (!file) return
  event.target.value = ''
  selectedAvatarFile.value = file
  avatarPreviewUrl.value = URL.createObjectURL(file)
  dragX.value = 0
  dragY.value = 0
  zoomLevel.value = 1
  showAvatarModal.value = true
}

const startDrag = (e) => {
  isDragging.value = true
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  startX.value = clientX - dragX.value
  startY.value = clientY - dragY.value
}

const onDrag = (e) => {
  if (!isDragging.value) return
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  dragX.value = clientX - startX.value
  dragY.value = clientY - startY.value
}

const endDrag = () => {
  isDragging.value = false
}

const resetAvatarTransform = () => {
  dragX.value = 0
  dragY.value = 0
  zoomLevel.value = 1
}

const cancelAvatarPreview = () => {
  showAvatarModal.value = false
  if (avatarPreviewUrl.value) {
    URL.revokeObjectURL(avatarPreviewUrl.value)
    avatarPreviewUrl.value = ''
  }
  selectedAvatarFile.value = null
  resetAvatarTransform()
}

const confirmAvatarUpload = async () => {
  if (!avatarPreviewUrl.value) return
  uploadingAvatar.value = true

  try {
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.src = avatarPreviewUrl.value
    await new Promise((resolve, reject) => {
      img.onload = resolve
      img.onerror = reject
    })

    const canvas = document.createElement('canvas')
    const canvasSize = 400
    canvas.width = canvasSize
    canvas.height = canvasSize
    const ctx = canvas.getContext('2d')

    const viewportSize = 176
    const scaleFactor = canvasSize / viewportSize

    // Clip to circle
    ctx.beginPath()
    ctx.arc(canvasSize / 2, canvasSize / 2, canvasSize / 2, 0, Math.PI * 2)
    ctx.closePath()
    ctx.clip()

    // Calculated aspect fill sizing
    const drawWidth = imgRenderWidth.value * zoomLevel.value
    const drawHeight = imgRenderHeight.value * zoomLevel.value

    const posX = (viewportSize - drawWidth) / 2 + dragX.value
    const posY = (viewportSize - drawHeight) / 2 + dragY.value

    ctx.drawImage(img, posX * scaleFactor, posY * scaleFactor, drawWidth * scaleFactor, drawHeight * scaleFactor)

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.95))

    const formData = new FormData()
    formData.append('avatar', blob, 'avatar.jpg')

    await apiClient.post('/api/profile/avatar', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    await authStore.fetchUser()
    populateUserData()
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật ảnh đại diện mới!', life: 3000 })
    cancelAvatarPreview()
  } catch(e) {
    console.error(e)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải lên ảnh đại diện.', life: 5000 })
  } finally {
    uploadingAvatar.value = false
  }
}
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.4s ease-out forwards;
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

button:not([tabindex="-1"]) {
  min-height: 44px;
}

@media (prefers-reduced-motion: reduce) {
  .animate-fade-in {
    animation: none !important;
  }
}
</style>
