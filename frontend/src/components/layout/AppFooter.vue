<template>
  <footer
    class="mt-10 w-full border-t border-outline-variant/20 bg-surface-container-low pb-6 pt-8"
    aria-labelledby="footer-heading"
  >
    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-6 px-4 md:px-gutter">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-6">
        <div class="flex flex-col gap-2.5 lg:col-span-2">
          <h2 id="footer-heading" class="font-inter text-xl font-bold text-primary">Komibook</h2>
          <p class="text-xs font-semibold text-on-surface-variant">Premium Digital Library</p>
          <p class="text-xs leading-relaxed text-on-surface-variant">
            KomiBook là nền tảng thương mại sách đa gian hàng, kết nối độc giả với sách giấy
            và E-book từ các nhà bán uy tín.
          </p>

          <div class="mt-2 flex flex-col gap-1.5 rounded-xl border border-outline-variant/20 bg-surface-container-lowest/60 p-3.5">
            <span class="text-xs font-bold text-on-surface">Đăng ký nhận tin</span>
            <span class="text-xs leading-relaxed text-on-surface-variant">
              Tính năng đăng ký nhận tin chưa khả dụng.
            </span>
            <button
              type="button"
              disabled
              class="hidden"
            >
              Chưa hỗ trợ
            </button>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <h3 class="mb-1 text-sm font-bold text-on-surface">Dịch vụ</h3>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/about">Về KomiBook</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/used-books/manage">Bán sách cũ</RouterLink>
          <span class="mt-1 text-xs leading-relaxed text-on-surface-variant">Thành viên Premium đang hoàn thiện.</span>
        </div>

        <div class="flex flex-col gap-2">
          <h3 class="mb-1 text-sm font-bold text-on-surface">Hỗ trợ</h3>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/help-center">Trung tâm trợ giúp</RouterLink>
          <button v-if="authStore.isAuthenticated" type="button" class="inline-flex min-h-11 cursor-pointer items-center border-0 bg-transparent p-0 text-left text-sm text-on-surface-variant transition-colors hover:text-primary" @click="chatStore.openConversationList()">Trò chuyện hỗ trợ</button>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/faq">Câu hỏi thường gặp</RouterLink>
        </div>

        <div class="flex flex-col gap-2">
          <h3 class="mb-1 text-sm font-bold text-on-surface">Chính sách</h3>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/terms">Điều khoản sử dụng</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/privacy">Chính sách bảo mật</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/policies/ebooks">Ebook và phiên bản</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/policies/used-books">Sách cũ & hoàn tiền</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-sm text-on-surface-variant transition-colors hover:text-primary no-underline" to="/policies/copyright">Bản quyền & hàng giả</RouterLink>
        </div>

        <div class="flex flex-col gap-2">
          <h3 class="mb-1 text-sm font-bold text-on-surface">Theo dõi</h3>
          <p class="text-xs leading-relaxed text-on-surface-variant">
            KomiBook chưa công bố liên kết mạng xã hội chính thức.
          </p>
        </div>
      </div>

      <div class="mt-2 flex w-full items-center justify-center border-t border-outline-variant/20 pt-4 pb-2 text-center">
        <p class="m-0 w-full text-center text-xs text-on-surface-variant">
          © {{ currentYear }} Komibook Digital Library. All rights reserved.
        </p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed } from 'vue'
import { useChatStore } from '@/stores/chatStore'
import { useAuthStore } from '@/stores/auth'

const currentYear = computed(() => new Date().getFullYear())
const chatStore = useChatStore()
const authStore = useAuthStore()
</script>
