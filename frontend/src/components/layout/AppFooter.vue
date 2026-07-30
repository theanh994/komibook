<template>
  <footer
    class="mt-xl w-full border-t border-outline-variant/20 bg-surface-container-low pb-8 pt-xl"
    aria-labelledby="footer-heading"
  >
    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-xl px-4 md:px-gutter">
      <div class="grid grid-cols-1 gap-lg md:grid-cols-2 lg:grid-cols-6">
        <div class="flex flex-col gap-sm lg:col-span-2">
          <h2 id="footer-heading" class="font-inter text-2xl font-bold text-primary">Komibook</h2>
          <p class="text-base text-on-surface-variant">Premium Digital Library</p>
          <p class="mt-sm text-sm leading-relaxed text-on-surface-variant">
            KomiBook là nền tảng thương mại sách đa gian hàng, kết nối độc giả với sách giấy
            và E-book từ các nhà bán uy tín.
          </p>

          <div class="mt-md flex flex-col gap-2 rounded-2xl border border-outline-variant/20 bg-surface-container-lowest/60 p-4">
            <span class="text-sm font-bold text-on-surface">Đăng ký nhận tin</span>
            <span class="text-sm leading-relaxed text-on-surface-variant">
              Tính năng đăng ký nhận tin chưa khả dụng.
            </span>
            <button
              type="button"
              disabled
              class="min-h-11 w-fit cursor-not-allowed rounded-lg bg-outline-variant/30 px-4 py-2 text-sm font-semibold text-on-surface-variant opacity-70"
            >
              Chưa hỗ trợ
            </button>
          </div>
        </div>

        <div class="flex flex-col gap-sm">
          <h3 class="mb-2 text-base font-bold text-on-surface">Dịch vụ</h3>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/about">Về KomiBook</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/used-books/manage">Bán sách cũ</RouterLink>
          <span class="text-sm leading-6 text-on-surface-variant">Thành viên Premium đang được hoàn thiện.</span>
        </div>

        <div class="flex flex-col gap-sm">
          <h3 class="mb-2 text-base font-bold text-on-surface">Hỗ trợ</h3>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/help-center">
            Trung tâm trợ giúp
          </RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/contact">Liên hệ</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/faq">Câu hỏi thường gặp</RouterLink>
        </div>

        <div class="flex flex-col gap-sm">
          <h3 class="mb-2 text-base font-bold text-on-surface">Chính sách</h3>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/terms">Điều khoản sử dụng</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/privacy">Chính sách bảo mật</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/policies/ebooks">Ebook và phiên bản</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/policies/used-books">Sách cũ & hoàn tiền</RouterLink>
          <RouterLink class="inline-flex min-h-11 items-center text-base text-on-surface-variant transition-colors hover:text-secondary" to="/policies/copyright">Bản quyền & hàng giả</RouterLink>
        </div>

        <div class="flex flex-col gap-sm">
          <h3 class="mb-2 text-base font-bold text-on-surface">Theo dõi</h3>
          <p class="text-sm leading-relaxed text-on-surface-variant">
            KomiBook chưa công bố liên kết mạng xã hội chính thức.
          </p>
        </div>
      </div>

      <div class="flex items-center justify-center border-t border-outline-variant/30 py-md">
        <p class="text-center text-sm text-on-surface-variant">
          © {{ currentYear }} Komibook Digital Library. All rights reserved.
        </p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed } from 'vue'

const currentYear = computed(() => new Date().getFullYear())
</script>
