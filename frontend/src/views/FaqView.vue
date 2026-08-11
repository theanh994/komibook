<script setup>
import { computed, ref } from 'vue'
import { useChatStore } from '@/stores/chatStore'

const chatStore = useChatStore()

const searchQuery = ref('')
const selectedCategory = ref('all')
const openFaqIds = ref(new Set([1])) // Open first question by default

const faqCategories = [
  { id: 'all', label: 'Tất cả câu hỏi', icon: 'grid_view' },
  { id: 'ebook', label: 'E-book & Đọc số', icon: 'devices' },
  { id: 'payment', label: 'Mua sách & Thanh toán', icon: 'payments' },
  { id: 'used_books', label: 'Sách cũ & Trả hàng', icon: 'cyclone' },
  { id: 'copyright', label: 'Bản quyền & Sách giả', icon: 'gpp_maybe' },
  { id: 'seller', label: 'Đăng ký Nhà bán', icon: 'storefront' },
]

const faqItems = [
  {
    id: 1,
    categoryId: 'ebook',
    categoryName: 'E-book & Đọc số',
    question: 'E-book có được trả lại sau khi mua không?',
    answer: 'Không. E-book là nội dung số có thể truy cập ngay sau khi thanh toán và không thuộc luồng trả hàng hay hoàn tiền. Điều khoản này được hiển thị rõ ràng và phải được xác nhận trước khi khách hàng hoàn tất đặt đơn có chứa E-book.',
    relatedLink: { label: 'Xem Chính sách Ebook', to: '/policies/ebooks' },
  },
  {
    id: 2,
    categoryId: 'ebook',
    categoryName: 'E-book & Đọc số',
    question: 'Nếu E-book được cập nhật phiên bản mới sau khi tôi mua thì sao?',
    answer: 'Bạn vẫn giữ nguyên quyền đọc đối với phiên bản E-book đã mua tại thời điểm đặt hàng. Nếu nhà xuất bản phát hành các phiên bản cập nhật hoặc chỉnh sửa sau đó, bạn có thể chọn chuyển đổi giữa các phiên bản trong giao diện trình đọc mà không mất thêm phí.',
    relatedLink: { label: 'Xem Điều khoản đọc số', to: '/terms' },
  },
  {
    id: 3,
    categoryId: 'used_books',
    categoryName: 'Sách cũ & Trả hàng',
    question: 'Sản phẩm nào trên KomiBook có thể yêu cầu trả hàng?',
    answer: 'Luồng trả hàng và hoàn tiền hiện dành cho Sách cũ vật lý (C2C) đủ điều kiện. Yêu cầu phải được gửi trong thời hạn quy định (được lưu snapshot theo đơn hàng) kèm theo hình ảnh/bằng chứng mô tả đúng lý do trả hàng.',
    relatedLink: { label: 'Xem Chính sách trả sách cũ', to: '/policies/used-books' },
  },
  {
    id: 4,
    categoryId: 'copyright',
    categoryName: 'Bản quyền & Sách giả',
    question: 'Nếu phát hiện sách cũ có dấu hiệu giả/sao chép trái phép thì xử lý ra sao?',
    answer: 'KomiBook tôn trọng tuyệt đối quyền tác giả. Người đăng bán chịu trách nhiệm hoàn toàn về tính xác thực của cuốn sách. Nếu khách hàng nghi ngờ nhận phải sách giả, có thể mở tranh chấp và gửi bằng chứng qua luồng Hỗ trợ. Nếu xác minh đúng, đơn hàng sẽ được hoàn tiền và khóa listing vi phạm.',
    relatedLink: { label: 'Xem Quy trình khiếu nại bản quyền', to: '/policies/copyright' },
  },
  {
    id: 5,
    categoryId: 'seller',
    categoryName: 'Đăng ký Nhà bán',
    question: 'Tôi muốn đăng bán sách trên KomiBook thì cần chuẩn bị những gì?',
    answer: 'Đối với các đơn vị kinh doanh hoặc Nhà xuất bản, bạn có thể đăng ký mở Gian hàng chính thức tại trang Đăng ký Nhà bán. Đối với cá nhân muốn nhượng lại sách đã qua sử dụng, bạn có thể sử dụng khu vực Người bán sách cũ mà không cần thủ tục doanh nghiệp.',
    relatedLink: { label: 'Trang Đăng ký Nhà bán', to: '/vendor/register' },
  },
  {
    id: 6,
    categoryId: 'payment',
    categoryName: 'Mua sách & Thanh toán',
    question: 'KomiBook hỗ trợ những phương thức thanh toán nào?',
    answer: 'Hiện tại hệ thống hỗ trợ 2 phương thức thanh toán: COD (Thanh toán tiền mặt khi nhận hàng) áp dụng cho sách vật lý, và VNPAY (Chuyển khoản / Mã QR / Thẻ ATM & Thẻ quốc tế) áp dụng cho cả E-book lẫn sách vật lý.',
  },
  {
    id: 7,
    categoryId: 'payment',
    categoryName: 'Mua sách & Thanh toán',
    question: 'Điểm tích lũy và Hạng VIP được tính như thế nào?',
    answer: 'Mỗi đơn hàng thanh toán thành công đều tự động quy đổi thành điểm thưởng tích lũy. Đạt các mốc điểm quy định sẽ giúp tài khoản của bạn tự động thăng hạng VIP (Đồng, Bạc, Vàng, Kim Cương) để hưởng các đặc quyền chiết khấu E-book và ưu đãi phí vận chuyển.',
    relatedLink: { label: 'Xem Hạng VIP & Quyền lợi', to: '/profile?tab=membership' },
  },
  {
    id: 8,
    categoryId: 'used_books',
    categoryName: 'Sách cũ & Trả hàng',
    question: 'Địa chỉ gửi hàng của người bán sách cũ có bị lộ cho người mua không?',
    answer: 'Không. Địa chỉ gửi hàng của người bán sách cũ được lưu trữ mã hóa riêng tư và chỉ dùng nội bộ cho đơn vị vận chuyển đến lấy hàng. Người mua không thể nhìn thấy địa chỉ cá nhân của người bán trên giao diện công khai.',
    relatedLink: { label: 'Chính sách bảo mật địa chỉ', to: '/privacy' },
  },
]

const filteredFaqs = computed(() => {
  return faqItems.filter(item => {
    const matchesCategory = selectedCategory.value === 'all' || item.categoryId === selectedCategory.value
    const q = searchQuery.value.trim().toLowerCase()
    const matchesSearch = !q || item.question.toLowerCase().includes(q) || item.answer.toLowerCase().includes(q) || item.categoryName.toLowerCase().includes(q)
    return matchesCategory && matchesSearch
  })
})

const toggleFaq = (id) => {
  if (openFaqIds.value.has(id)) {
    openFaqIds.value.delete(id)
  } else {
    openFaqIds.value.add(id)
  }
}

const isOpen = (id) => openFaqIds.value.has(id)
</script>

<template>
  <div class="min-h-screen bg-background pb-16 pt-6">
    <div class="mx-auto w-full max-w-[1280px] px-4 md:px-gutter space-y-10">
      
      <!-- Hero Header & Search -->
      <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-primary via-primary-container to-secondary p-8 text-on-primary shadow-elevated sm:p-12 text-center">
        <div class="relative z-10 max-w-2xl mx-auto space-y-4">
          <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1 text-xs font-bold text-white uppercase tracking-wider">
            <span class="material-symbols-outlined text-base">quiz</span>
            <span>Giải đáp thắc mắc</span>
          </div>

          <h1 class="text-3xl font-black text-white sm:text-4xl lg:text-5xl leading-tight">
            Câu Hỏi Thường Gặp (FAQ)
          </h1>

          <p class="text-sm sm:text-base text-primary-fixed-dim leading-relaxed">
            Tổng hợp các câu trả lời ngắn gọn về luồng mua sách, E-book số, chính sách trả hàng và quy trình vận hành KomiBook.
          </p>

          <!-- Search Input Box -->
          <div class="mt-4 flex items-center gap-2 bg-white rounded-2xl p-2 shadow-md max-w-xl mx-auto border border-outline-variant/20">
            <span class="material-symbols-outlined text-slate-400 text-2xl ml-3" aria-hidden="true">search</span>
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Nhập câu hỏi hoặc từ khóa bạn muốn tìm..." 
              class="flex-grow min-w-0 bg-transparent text-slate-900 placeholder-slate-400 border-none outline-none text-sm px-2 focus:ring-0"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="text-xs text-slate-500 font-bold px-3 hover:text-slate-800 cursor-pointer"
              @click="searchQuery = ''"
            >
              Xóa
            </button>
          </div>
        </div>
      </section>

      <!-- Category Filter Chips -->
      <section class="space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Lọc câu hỏi theo chủ đề</h2>
        <div class="flex flex-wrap gap-2.5">
          <button
            v-for="cat in faqCategories"
            :key="cat.id"
            type="button"
            class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all border cursor-pointer"
            :class="selectedCategory === cat.id
              ? 'bg-primary text-on-primary border-primary shadow-xs'
              : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant/30 hover:border-primary/40 hover:bg-surface-container-low'"
            @click="selectedCategory = cat.id"
          >
            <span class="material-symbols-outlined text-base" aria-hidden="true">{{ cat.icon }}</span>
            <span>{{ cat.label }}</span>
          </button>
        </div>
      </section>

      <!-- Accordion FAQ List (2-Column Grid on Desktop) -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-black text-on-surface flex items-center gap-2">
            <span class="material-symbols-outlined text-primary" aria-hidden="true">question_answer</span>
            Danh sách câu hỏi & giải đáp
          </h2>
          <span class="text-xs font-medium text-on-surface-variant">Hiển thị {{ filteredFaqs.length }} câu hỏi</span>
        </div>

        <!-- Empty State -->
        <div v-if="filteredFaqs.length === 0" class="rounded-3xl border border-dashed border-outline-variant/40 bg-surface-container-lowest p-10 text-center space-y-2">
          <span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">search_off</span>
          <h3 class="text-base font-bold text-on-surface">Không tìm thấy câu hỏi phù hợp</h3>
          <p class="text-xs text-on-surface-variant">Thử tìm kiếm từ khóa khác hoặc chuyển sang chủ đề khác.</p>
        </div>

        <!-- 2-Column Grid Accordion Items -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <article
            v-for="faq in filteredFaqs"
            :key="faq.id"
            class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest overflow-hidden shadow-2xs transition-all duration-200"
            :class="isOpen(faq.id) ? 'border-primary/40 shadow-soft ring-1 ring-primary/20' : 'hover:border-outline-variant/60'"
          >
            <!-- Question Toggle Header -->
            <button
              type="button"
              class="w-full flex items-start justify-between gap-3 p-4 sm:p-5 text-left cursor-pointer transition-colors hover:bg-surface-container-low/50"
              @click="toggleFaq(faq.id)"
            >
              <div class="space-y-1.5 min-w-0 flex-1">
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary shrink-0 uppercase tracking-wider">
                  {{ faq.categoryName }}
                </span>
                <h3 class="font-bold text-on-surface text-sm leading-snug">{{ faq.question }}</h3>
              </div>

              <span
                class="material-symbols-outlined text-primary text-xl shrink-0 transition-transform duration-300 mt-0.5"
                :class="{ 'rotate-180': isOpen(faq.id) }"
                aria-hidden="true"
              >
                expand_more
              </span>
            </button>

            <!-- Collapsible Answer Content -->
            <div
              v-show="isOpen(faq.id)"
              class="px-4 pb-5 pt-2 sm:px-5 border-t border-outline-variant/15 text-xs text-on-surface-variant leading-relaxed space-y-2.5 bg-surface-container-low/30"
            >
              <p>{{ faq.answer }}</p>

              <div v-if="faq.relatedLink" class="pt-1">
                <RouterLink
                  :to="faq.relatedLink.to"
                  class="inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline no-underline"
                >
                  <span>{{ faq.relatedLink.label }}</span>
                  <span class="material-symbols-outlined text-xs" aria-hidden="true">arrow_forward</span>
                </RouterLink>
              </div>
            </div>
          </article>
        </div>
      </section>

      <!-- Bottom Assistance Callout Card -->
      <section class="rounded-3xl bg-gradient-to-r from-surface-container-low to-surface-container-lowest border border-outline-variant/30 p-8 shadow-soft">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
          <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary shrink-0">
              <span class="material-symbols-outlined text-3xl" aria-hidden="true">contact_support</span>
            </div>
            <div>
              <h3 class="font-bold text-on-surface text-lg">Bạn vẫn còn thắc mắc khác?</h3>
              <p class="text-xs text-on-surface-variant leading-relaxed mt-0.5">
                Tra cứu Trung tâm trợ giúp để xem thêm nhiều bài viết hoặc trò chuyện trực tiếp với Trợ lý AI KomiBook.
              </p>
            </div>
          </div>

          <div class="flex flex-wrap gap-3 shrink-0">
            <RouterLink
              to="/help-center"
              class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-outline-variant/40 bg-surface-container-lowest px-5 py-3 font-bold text-on-surface hover:bg-surface-container-low no-underline"
            >
              <span class="material-symbols-outlined" aria-hidden="true">help</span>
              <span>Trung tâm Trợ giúp</span>
            </RouterLink>

            <button
              type="button"
              class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-6 py-3 font-bold text-on-primary shadow-xs transition hover:bg-primary-container cursor-pointer"
              @click="chatStore.openConversationList()"
            >
              <span class="material-symbols-outlined" aria-hidden="true">forum</span>
              <span>Trò chuyện ngay</span>
            </button>
          </div>
        </div>
      </section>

    </div>
  </div>
</template>

<style scoped>
button:not([tabindex="-1"]) {
  min-height: 44px;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>
