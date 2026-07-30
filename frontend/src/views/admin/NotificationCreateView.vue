<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const router = useRouter()
const toast = useToast()

const form = ref({
  title: '',
  message: '',
  image_url: '',
  target_audience: 'all',
  send_option: 'now', // 'now', 'schedule', 'draft'
  scheduled_at: '',
})

const submitting = ref(false)

const targetAudienceOptions = [
  { value: 'all', label: 'Tất cả độc giả', desc: 'Gửi tới toàn bộ người dùng đã có tài khoản trên sàn' },
  { value: 'active_readers', label: 'Độc giả tích cực', desc: 'Có ít nhất 1 đơn hàng thanh toán thành công' },
  { value: 'fiction_enthusiasts', label: 'Độc giả đam mê viễn tưởng', desc: 'Yêu thích hoặc có hành vi đọc thể loại viễn tưởng' },
  { value: 'lapsed_users', label: 'Độc giả cũ (30 ngày)', desc: 'Người dùng chưa thực hiện giao dịch nào trong 30 ngày qua' },
]

const currentDateTimeString = computed(() => {
  const tzoffset = (new Date()).getTimezoneOffset() * 60000
  const localISOTime = (new Date(Date.now() - tzoffset)).toISOString().slice(0, 16)
  return localISOTime
})

const validateForm = () => {
  if (!form.value.title.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Tiêu đề thông báo không được để trống.', life: 3000 })
    return false
  }
  if (!form.value.message.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Nội dung thông báo không được để trống.', life: 3000 })
    return false
  }
  if (form.value.send_option === 'schedule') {
    if (!form.value.scheduled_at) {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn thời gian lên lịch gửi.', life: 3000 })
      return false
    }
    if (new Date(form.value.scheduled_at) <= new Date()) {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Thời gian lên lịch phải ở tương lai.', life: 3000 })
      return false
    }
  }
  return true
}

const handleSubmit = async () => {
  if (!validateForm()) return

  submitting.value = true

  // Set correct status string matching backend expects
  let status = 'draft'
  if (form.value.send_option === 'now') status = 'sent'
  else if (form.value.send_option === 'schedule') status = 'scheduled'

  try {
    await apiClient.post('/api/admin/notifications/campaigns', {
      title: form.value.title,
      message: form.value.message,
      image_url: form.value.image_url || null,
      target_audience: form.value.target_audience,
      scheduled_at: form.value.send_option === 'schedule' ? form.value.scheduled_at : null,
      status: status
    })

    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: status === 'sent' ? 'Chiến dịch đã bắt đầu gửi.' : 'Đã lên lịch/lưu nháp chiến dịch.',
      life: 3000
    })
    
    router.push('/admin/notifications')
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Lưu chiến dịch thất bại. Vui lòng kiểm tra lại.', life: 3000 })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="pb-12 w-full pt-6">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-8 animate-fade-in">
      <router-link
        to="/admin/notifications"
        aria-label="Quay lại danh sách chiến dịch"
        class="ui-btn ui-btn-secondary"
      >
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
      </router-link>
      <div>
        <h1 class="text-2xl font-extrabold text-on-surface sm:text-3xl">
          Tạo chiến dịch mới
        </h1>
        <p class="mt-1 text-sm text-on-surface-variant">
          Thiết lập nội dung tin nhắn và cấu hình kênh truyền thông (Email/Push).
        </p>
      </div>
    </div>

    <!-- Main split layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start animate-slide-up">
      <!-- Left Configuration Panel -->
      <div class="ui-panel space-y-6 lg:col-span-7">
        <!-- Section 1: Audience Selection -->
        <div>
          <h3 class="text-md mb-4 flex items-center gap-2 font-bold text-on-surface">
            <span class="material-symbols-outlined text-[20px] text-primary">group</span>
            1. Chọn nhóm đối tượng mục tiêu
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <label
              v-for="option in targetAudienceOptions"
              :key="option.value"
              class="cursor-pointer rounded-xl border p-4 transition-colors hover:border-primary"
              :class="form.target_audience === option.value
                ? 'border-primary bg-primary-fixed ring-1 ring-primary'
                : 'border-outline-variant bg-surface-container-lowest'"
            >
              <input v-model="form.target_audience" class="sr-only" type="radio" name="target_audience" :value="option.value" />
              <div class="flex items-center justify-between">
                <span class="text-sm font-bold" :class="form.target_audience === option.value ? 'text-on-primary-fixed' : 'text-on-surface'">
                  {{ option.label }}
                </span>
                <span
                  class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0"
                  :class="form.target_audience === option.value ? 'border-primary bg-primary text-on-primary' : 'border-outline'"
                >
                  <span v-if="form.target_audience === option.value" class="w-1.5 h-1.5 bg-white rounded-full"></span>
                </span>
              </div>
              <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-on-surface-variant">
                {{ option.desc }}
              </p>
            </label>
          </div>
        </div>

        <hr class="border-slate-100 dark:border-zinc-800" />

        <!-- Section 2: Message Template -->
        <div class="space-y-4">
          <h3 class="text-md flex items-center gap-2 font-bold text-on-surface">
            <span class="material-symbols-outlined text-[20px] text-primary">edit_note</span>
            2. Soạn nội dung thông điệp
          </h3>

          <!-- Title -->
          <div class="flex flex-col gap-1.5">
            <label for="campaign-title-input" class="text-sm font-semibold text-on-surface-variant">Tiêu đề thông báo / Tiêu đề Email</label>
            <input
              id="campaign-title-input"
              v-model="form.title"
              type="text"
              placeholder="Ví dụ: Độc Quyền Cuối Tuần: Giảm 50% Sách Best-Seller!"
              aria-label="Tiêu đề chiến dịch"
              class="ui-field"
            />
          </div>

          <!-- Body Message -->
          <div class="flex flex-col gap-1.5">
            <label for="campaign-message" class="text-sm font-semibold text-on-surface-variant">Nội dung chi tiết</label>
            <textarea
              id="campaign-message"
              v-model="form.message"
              rows="4"
              placeholder="Nhập nội dung thông điệp của bạn. Độc giả sẽ nhận được thông báo đẩy trên web/mobile và email gửi trực tiếp về hòm thư..."
              class="ui-field"
            ></textarea>
          </div>

          <!-- Image URL -->
          <div class="flex flex-col gap-1.5">
            <label for="campaign-image" class="text-sm font-semibold text-on-surface-variant">Đường dẫn hình ảnh (Banner URL - tùy chọn)</label>
            <input
              id="campaign-image"
              v-model="form.image_url"
              type="url"
              placeholder="https://example.com/banner-khuyen-mai.jpg"
              class="ui-field"
            />
          </div>
        </div>

        <hr class="border-slate-100 dark:border-zinc-800" />

        <!-- Section 3: Send Setting -->
        <div class="space-y-4">
          <h3 class="text-md flex items-center gap-2 font-bold text-on-surface">
            <span class="material-symbols-outlined text-[20px] text-primary">rocket_launch</span>
            3. Thiết lập chế độ gửi
          </h3>

          <div class="flex flex-col gap-3">
            <!-- Now -->
            <label
              class="flex items-center gap-3 border rounded-xl p-3 cursor-pointer hover:bg-slate-50/50 dark:hover:bg-zinc-950/10"
              :class="form.send_option === 'now' ? 'border-indigo-600/30 bg-indigo-50/10' : 'border-slate-100 dark:border-zinc-800'"
            >
              <input type="radio" value="now" v-model="form.send_option" class="accent-indigo-600" />
              <div>
                <span class="font-bold text-sm text-slate-700 dark:text-zinc-300">Gửi ngay lập tức</span>
                <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">Xử lý gửi hàng loạt thông báo & Email ngay sau khi tạo</p>
              </div>
            </label>

            <!-- Schedule -->
            <label
              class="flex items-center gap-3 border rounded-xl p-3 cursor-pointer hover:bg-slate-50/50 dark:hover:bg-zinc-950/10"
              :class="form.send_option === 'schedule' ? 'border-indigo-600/30 bg-indigo-50/10' : 'border-slate-100 dark:border-zinc-800'"
            >
              <input type="radio" value="schedule" v-model="form.send_option" class="accent-indigo-600" />
              <div class="grow">
                <span class="font-bold text-sm text-slate-700 dark:text-zinc-300">Lên lịch gửi tự động</span>
                <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">Hệ thống tự động kích hoạt chiến dịch vào mốc giờ lựa chọn</p>
              </div>
            </label>

            <!-- Schedule DateTime Input -->
            <div v-if="form.send_option === 'schedule'" class="pl-6 animate-slide-down">
              <input
                v-model="form.scheduled_at"
                type="datetime-local"
                :min="currentDateTimeString"
                class="px-4 py-2.5 bg-slate-50 dark:bg-zinc-950/30 border border-slate-200 dark:border-zinc-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-slate-800 dark:text-zinc-100 text-sm w-full md:w-64"
              />
            </div>

            <!-- Draft -->
            <label
              class="flex items-center gap-3 border rounded-xl p-3 cursor-pointer hover:bg-slate-50/50 dark:hover:bg-zinc-950/10"
              :class="form.send_option === 'draft' ? 'border-indigo-600/30 bg-indigo-50/10' : 'border-slate-100 dark:border-zinc-800'"
            >
              <input type="radio" value="draft" v-model="form.send_option" class="accent-indigo-600" />
              <div>
                <span class="font-bold text-sm text-slate-700 dark:text-zinc-300">Lưu nháp</span>
                <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">Lưu trữ nội dung và cấu hình của bạn để chỉnh sửa/gửi sau</p>
              </div>
            </label>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
          <router-link
            to="/admin/notifications"
            class="ui-btn ui-btn-secondary"
          >
            Hủy bỏ
          </router-link>
          <button
            @click="handleSubmit"
            type="button"
            class="ui-btn ui-btn-primary"
            :disabled="submitting"
          >
            <i v-if="submitting" class="pi pi-spin pi-spinner text-sm"></i>
            {{ form.send_option === 'now' ? 'Gửi Chiến Dịch' : form.send_option === 'schedule' ? 'Lên Lịch Gửi' : 'Lưu Bản Thảo' }}
          </button>
        </div>
      </div>

      <!-- Right Interactive Preview Panel -->
      <div class="lg:col-span-5 flex flex-col gap-6 items-center w-full min-w-0">
        <!-- Card Title -->
        <h3 class="text-sm font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-1.5 self-start">
          <span class="material-symbols-outlined text-[18px]">preview</span>
          Xem trước hiển thị thời gian thực
        </h3>

        <!-- Mockup 1: Mobile Lockscreen Push -->
        <div class="bg-zinc-950 rounded-[40px] p-4 shadow-xl border-4 border-zinc-800 w-full max-w-[320px] h-[568px] mx-auto relative overflow-hidden flex flex-col justify-between shrink-0">
          <!-- Notch -->
          <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-6 bg-zinc-800 rounded-b-2xl z-10"></div>
          
          <!-- Mockup Wallpaper background -->
          <div class="absolute inset-0 bg-gradient-to-tr from-purple-900 via-indigo-950 to-emerald-950 opacity-90 blur-sm"></div>

          <!-- Screen content -->
          <div class="relative z-10 w-full pt-10 text-center text-white">
            <!-- Time -->
            <div class="text-5xl font-light tracking-wide">09:41</div>
            <div class="text-xs font-semibold tracking-widest uppercase mt-1 opacity-70">Thứ Ba, 20 Tháng 5</div>

            <!-- Push notification bubble -->
            <div class="mx-2 mt-12 bg-white/10 dark:bg-black/35 backdrop-blur-md rounded-2xl p-3.5 border border-white/10 shadow-lg text-left animate-pulse-subtle">
              <div class="flex items-center justify-between text-white/60 text-[10px] font-semibold">
                <div class="flex items-center gap-1.5">
                  <span class="w-4 h-4 rounded bg-indigo-600 text-white flex items-center justify-center font-bold text-[9px]">K</span>
                  <span>KomiBook</span>
                </div>
                <span>bây giờ</span>
              </div>
              <h4 class="font-bold text-white text-xs mt-1.5 line-clamp-1">
                {{ form.title || 'Nhập tiêu đề thông báo đẩy...' }}
              </h4>
              <p class="text-white/80 text-[11px] mt-0.5 line-clamp-3 leading-snug animate-fade-in">
                {{ form.message || 'Nội dung thông điệp sẽ hiển thị đầy đủ tại đây trên điện thoại người dùng...' }}
              </p>
              
              <!-- Push Image Rich preview -->
              <div v-if="form.image_url" class="mt-2.5 rounded-lg overflow-hidden border border-white/5 max-h-24">
                <img :src="form.image_url" class="w-full h-full object-cover" alt="Push banner preview" />
              </div>
            </div>
          </div>

          <!-- Bottom Lock slider -->
          <div class="relative z-10 w-full text-center pb-4 text-white/50 text-[10px] tracking-wider font-semibold">
            <span class="material-symbols-outlined text-[20px] block animate-bounce">expand_less</span>
            Vuốt lên để mở khóa
          </div>
        </div>

        <!-- Mockup 2: Email Client Preview -->
        <div class="bg-slate-100 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-2xl p-4 shadow-md w-full max-w-[380px] mx-auto shrink-0">
          <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-800 pb-2.5 mb-3 text-xs text-slate-500">
            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <span class="ml-2 font-semibold">Hòm thư khách hàng (Email)</span>
          </div>

          <div class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-zinc-800 p-4 font-sans text-left">
            <!-- Header bar -->
            <div class="border-b border-slate-100 dark:border-zinc-800 pb-3 flex items-center justify-between">
              <span class="text-sm font-black text-indigo-600">Komi<span class="text-slate-800 dark:text-zinc-100">Book</span></span>
              <span class="text-[9px] text-slate-400 uppercase tracking-widest font-semibold">Bản tin khuyến mãi</span>
            </div>

            <!-- Subject -->
            <div class="mt-3 bg-slate-50 dark:bg-zinc-950/40 p-2 rounded-lg text-xs text-slate-600 dark:text-zinc-300">
              <span class="font-bold text-slate-400">Tiêu đề:</span> {{ form.title || 'Nhập tiêu đề...' }}
            </div>

            <!-- Email Body Content -->
            <div class="mt-4 space-y-3">
              <h3 class="text-xs font-bold text-slate-800 dark:text-zinc-200">Xin chào [Tên khách hàng],</h3>
              
              <!-- Rich Banner image -->
              <div v-if="form.image_url" class="rounded-lg overflow-hidden border border-slate-100 max-h-32">
                <img :src="form.image_url" class="w-full h-full object-cover" alt="Email banner preview" />
              </div>

              <p class="text-[11px] text-slate-600 dark:text-zinc-400 leading-relaxed white-space-pre-line">
                {{ form.message || 'Nội dung thông điệp chi tiết...' }}
              </p>

              <!-- Mock Button link -->
              <div class="text-center pt-2">
                <span class="inline-flex min-h-11 items-center bg-indigo-600 text-white font-bold text-[10px] px-4 py-2 rounded-lg shadow-sm" aria-hidden="true">
                  Khám phá ngay tại KomiBook
                </span>
              </div>
            </div>

            <!-- Footer -->
            <div class="border-t border-slate-100 dark:border-zinc-800 mt-6 pt-3 text-center text-[9px] text-slate-400">
              Bạn nhận được email này vì đã đăng ký tài khoản tại KomiBook.<br/>
              © 2026 KomiBook Inc. Mọi quyền được bảo lưu.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
