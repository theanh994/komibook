<template>
  <footer class="mt-xl w-full border-t border-outline-variant/20 bg-surface-container-low pb-20 pt-xl md:pb-0">
    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-xl px-gutter">
      <div class="grid grid-cols-1 gap-lg md:grid-cols-2 lg:grid-cols-6">
        <div class="flex flex-col gap-sm lg:col-span-2">
          <span class="font-inter text-2xl font-bold text-primary">Komibook</span>
          <p class="text-base text-on-surface-variant">Premium Digital Library</p>
          <p class="mt-sm text-sm leading-relaxed text-on-surface-variant">
            KomiBook là nền tảng thương mại sách đa gian hàng, kết nối độc giả với sách giấy
            và E-book từ các nhà bán uy tín.
          </p>

          <div class="mt-md flex flex-col gap-2 rounded-2xl border border-outline-variant/20 bg-surface-container-lowest/60 p-4">
            <span class="text-sm font-bold text-on-surface">Đăng ký nhận tin</span>
            <span class="text-xs leading-relaxed text-on-surface-variant">
              Tính năng đăng ký nhận tin chưa khả dụng.
            </span>
            <button
              type="button"
              disabled
              class="w-fit cursor-not-allowed rounded-lg bg-outline-variant/30 px-4 py-2 text-xs font-semibold text-on-surface-variant opacity-70"
            >
              Chưa hỗ trợ
            </button>
          </div>
        </div>

        <div class="flex flex-col gap-sm">
          <h4 class="mb-2 text-sm font-bold text-on-surface">Dịch vụ</h4>
          <span class="text-base text-on-surface-variant">Về chúng tôi — Chưa có trang</span>
          <span class="text-base text-on-surface-variant">Tác giả — Chưa có trang</span>
          <span class="text-base text-on-surface-variant">Thành viên Premium — Chưa có trang</span>
        </div>

        <div class="flex flex-col gap-sm">
          <h4 class="mb-2 text-sm font-bold text-on-surface">Hỗ trợ</h4>
          <RouterLink class="text-base text-on-surface-variant transition-colors hover:text-secondary" to="/help-center">
            Trung tâm trợ giúp
          </RouterLink>
          <span class="text-base text-on-surface-variant">Liên hệ — Chưa có trang</span>
          <span class="text-base text-on-surface-variant">Câu hỏi thường gặp — Xem Trung tâm trợ giúp</span>
        </div>

        <div class="flex flex-col gap-sm">
          <h4 class="mb-2 text-sm font-bold text-on-surface">Chính sách</h4>
          <span class="text-base text-on-surface-variant">Điều khoản sử dụng — Chưa có trang</span>
          <span class="text-base text-on-surface-variant">Chính sách bảo mật — Chưa có trang</span>
          <span class="text-base text-on-surface-variant">Chính sách hoàn tiền — Chưa có trang</span>
        </div>

        <div class="flex flex-col gap-sm">
          <h4 class="mb-2 text-sm font-bold text-on-surface">Theo dõi</h4>
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
