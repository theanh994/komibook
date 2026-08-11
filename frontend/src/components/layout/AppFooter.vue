<template>
  <footer
    class="mt-12 w-full border-t border-outline-variant/20 bg-surface-container-low pb-6 pt-10"
    aria-labelledby="footer-heading"
  >
    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 px-4 md:px-gutter">
      <!-- Main Grid Layout (6 Columns on LG so all sections sit on the same row) -->
      <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">

        <!-- Column 1: Thương hiệu & Liên hệ (Span 2) -->
        <div class="flex flex-col gap-3 border-b border-outline-variant/15 pb-6 sm:col-span-2 md:col-span-3 lg:col-span-2 lg:border-b-0 lg:pb-0">
          <RouterLink to="/" class="group inline-flex items-center gap-3 no-underline self-start">
            <div class="h-12 w-12 flex items-center justify-center shrink-0 rounded-2xl bg-surface-container-lowest p-1 shadow-xs transition-[box-shadow,transform] duration-300 group-hover:scale-105 group-hover:shadow-md border border-outline-variant/20">
              <img v-if="logoExists" src="@/assets/logo.png" alt="KomiBook Logo" class="h-full w-full object-contain" />
              <div v-else class="flex h-full w-full items-center justify-center rounded-xl bg-[#00b14f]">
                <span class="material-symbols-outlined text-xl text-white" aria-hidden="true">auto_stories</span>
              </div>
            </div>
            <div class="flex flex-col justify-center">
              <h2 id="footer-heading" class="font-inter text-xl font-bold text-primary leading-none transition-colors group-hover:text-primary-container">Komibook</h2>
              <span class="mt-1 font-inter text-[10px] font-extrabold tracking-[0.2em] uppercase text-on-surface-variant leading-tight">Premium Digital Library</span>
            </div>
          </RouterLink>

          <p class="max-w-md text-xs leading-relaxed text-on-surface-variant/90 mt-0.5">
            KomiBook là nền tảng thương mại sách đa gian hàng, kết nối độc giả với sách giấy
            và E-book từ các nhà bán uy tín.
          </p>

          <!-- Email Liên hệ -->
          <div class="mt-1">
            <div class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest/80 px-3.5 py-3 text-xs text-on-surface-variant" aria-disabled="true">
              <p class="font-semibold">Tính năng đăng ký nhận tin chưa khả dụng</p>
              <input class="mt-2 min-h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3" type="email" disabled aria-label="Đăng ký nhận tin chưa khả dụng" />
            </div>
          </div>
        </div>

        <!-- Column 2: Dịch vụ (Span 1) -->
        <div class="flex flex-col gap-2 border-b border-outline-variant/15 pb-6 sm:border-b-0 sm:pb-0">
          <h3 class="mb-1 text-sm font-bold text-on-surface">Dịch vụ</h3>
          <RouterLink class="footer-link" to="/about">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Về KomiBook
          </RouterLink>
          <RouterLink class="footer-link" to="/used-books/manage">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Bán sách cũ
          </RouterLink>
          <RouterLink class="footer-link" to="/profile?tab=membership">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Hạng VIP & Quyền lợi
          </RouterLink>
        </div>

        <!-- Column 3: Hỗ trợ (Span 1) -->
        <div class="flex flex-col gap-2 border-b border-outline-variant/15 pb-6 sm:border-b-0 sm:pb-0">
          <h3 class="mb-1 text-sm font-bold text-on-surface">Hỗ trợ</h3>
          <RouterLink class="footer-link" to="/help-center">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Trung tâm trợ giúp
          </RouterLink>
          <button v-if="authStore.isAuthenticated" type="button" class="footer-link-btn" @click="chatStore.openConversationList()">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Trò chuyện hỗ trợ
          </button>
          <RouterLink class="footer-link" to="/faq">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Câu hỏi thường gặp
          </RouterLink>
        </div>

        <!-- Column 4: Chính sách (Span 1) -->
        <div class="flex flex-col gap-2 border-b border-outline-variant/15 pb-6 sm:border-b-0 sm:pb-0">
          <h3 class="mb-1 text-sm font-bold text-on-surface">Chính sách</h3>
          <RouterLink class="footer-link" to="/terms">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Điều khoản sử dụng
          </RouterLink>
          <RouterLink class="footer-link" to="/privacy">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Chính sách bảo mật
          </RouterLink>
          <RouterLink class="footer-link" to="/policies/ebooks">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Ebook và phiên bản
          </RouterLink>
          <RouterLink class="footer-link" to="/policies/used-books">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Sách cũ & hoàn tiền
          </RouterLink>
          <RouterLink class="footer-link" to="/policies/copyright">
            <span class="material-symbols-outlined link-arrow" aria-hidden="true">chevron_right</span>
            Bản quyền & hàng giả
          </RouterLink>
        </div>

        <!-- Column 5: Thanh toán & Theo dõi (Span 1 - Nằm trên cùng một hàng trên Desktop) -->
        <div class="flex flex-col gap-5 sm:col-span-2 md:col-span-1 lg:col-span-1">
          <!-- Phương thức thanh toán -->
          <div class="flex flex-col gap-2">
            <h3 class="mb-1 text-sm font-bold text-on-surface">Thanh toán</h3>
            <div class="flex flex-wrap items-center gap-2">
              <!-- COD Badge -->
              <div
                class="group flex min-h-11 items-center gap-1.5 rounded-xl border border-outline-variant/25 bg-surface-container-lowest px-3 py-1.5 shadow-2xs transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-xs cursor-default"
                title="Thanh toán khi nhận hàng"
              >
                <span class="material-symbols-outlined text-base text-primary transition-transform duration-200 group-hover:scale-110" aria-hidden="true">local_shipping</span>
                <span class="text-xs font-bold text-on-surface">COD</span>
              </div>
              <!-- VNPAY Badge -->
              <div
                class="group flex min-h-11 items-center gap-1.5 rounded-xl border border-outline-variant/25 bg-surface-container-lowest px-3 py-1.5 shadow-2xs transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:border-[#0066b3]/40 hover:shadow-xs cursor-default"
                title="Thanh toán qua VNPAY"
              >
                <svg class="h-4 w-4 text-[#0066b3] transition-transform duration-200 group-hover:scale-110" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l7 4.5-7 4.5z"/>
                </svg>
                <span class="text-xs font-bold text-on-surface">VNPAY</span>
              </div>
            </div>
          </div>

          <!-- Mạng xã hội -->
          <div class="flex flex-col gap-2">
            <h3 class="mb-1 text-sm font-bold text-on-surface">Theo dõi chúng tôi</h3>
            <div class="flex items-center gap-2.5">
              <a
                v-if="false"
                target="_blank"
                rel="noopener noreferrer"
                class="flex h-11 w-11 items-center justify-center rounded-full bg-[#1877F2] text-white shadow-xs transition-[background-color,box-shadow,transform] duration-300 hover:-translate-y-1 hover:bg-[#166fe5] hover:shadow-md hover:scale-105 active:scale-95 no-underline"
                aria-label="Theo dõi trên Facebook"
                title="Theo dõi trên Facebook"
              >
                <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
              </a>
            </div>
          </div>
        </div>

      </div>

      <!-- Copyright Bottom Bar -->
      <div class="flex w-full items-center justify-center border-t border-outline-variant/20 pt-5 pb-2 text-center">
        <p class="m-0 text-xs font-medium text-on-surface-variant/80">
          © {{ currentYear }} KomiBook. Bảo lưu mọi quyền.
        </p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useChatStore } from '@/stores/chatStore'
import { useAuthStore } from '@/stores/auth'

const currentYear = computed(() => new Date().getFullYear())
const chatStore = useChatStore()
const authStore = useAuthStore()

const logoExists = ref(false)

onMounted(() => {
  const img = new Image()
  img.onload = () => logoExists.value = true
  img.onerror = () => logoExists.value = false
  img.src = new URL('@/assets/logo.png', import.meta.url).href
})
</script>

<style scoped>
.footer-link,
.footer-link-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  min-height: 2.75rem;
  font-size: 0.875rem;
  color: var(--color-text-muted);
  text-decoration: none;
  background: transparent;
  border: 0;
  padding: 0;
  text-align: left;
  cursor: pointer;
  transition: color 0.2s cubic-bezier(0.4, 0, 0.2, 1), transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.footer-link:hover,
.footer-link-btn:hover {
  color: var(--color-primary);
  transform: translateX(0.375rem);
}

.link-arrow {
  font-size: 1rem;
  opacity: 0;
  transform: translateX(-4px);
  transition: opacity 0.2s ease, transform 0.2s ease;
  color: var(--color-primary);
}

.footer-link:hover .link-arrow,
.footer-link-btn:hover .link-arrow {
  opacity: 1;
  transform: translateX(0);
}
</style>
