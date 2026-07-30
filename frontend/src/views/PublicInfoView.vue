<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const pages = {
  about: {
    eyebrow: 'Về KomiBook',
    title: 'Không gian kết nối độc giả và người làm sách',
    intro: 'KomiBook kết hợp hiệu sách đa gian hàng, thư viện ebook và khu vực sách cũ trong một trải nghiệm minh bạch.',
    sections: [
      {
        icon: 'menu_book',
        title: 'Đọc theo cách phù hợp với bạn',
        body: 'Độc giả có thể khám phá ebook, sách vật lý và sách cũ theo nguồn gốc, định dạng và nhu cầu đọc.',
      },
      {
        icon: 'inventory_2',
        title: 'Vận hành theo vai trò rõ ràng',
        body: 'Nhà bán quản lý danh mục và kho hàng; Quản kho thao tác trong phạm vi kho được phân công; người bán sách cũ có khu vực riêng.',
      },
      {
        icon: 'verified_user',
        title: 'Thương mại có dấu vết',
        body: 'Đơn hàng, chính sách trả hàng, kiểm duyệt và các quyết định vận hành được lưu theo trạng thái thay vì hiển thị thông tin ước đoán.',
      },
    ],
    note: 'KomiBook đang tiếp tục hoàn thiện sản phẩm. Những tính năng chưa khả dụng sẽ được ghi rõ thay vì tạo thao tác không có hiệu lực.',
    cta: { label: 'Khám phá sách', to: '/catalog', icon: 'arrow_forward' },
  },
  contact: {
    eyebrow: 'Liên hệ',
    title: 'Tìm đúng kênh hỗ trợ cho vấn đề của bạn',
    intro: 'KomiBook không công bố hotline hoặc địa chỉ email hỗ trợ khi các kênh đó chưa được cấu hình chính thức.',
    sections: [
      {
        icon: 'help_center',
        title: 'Tra cứu trước tại Trung tâm trợ giúp',
        body: 'Các hướng dẫn phổ biến về tài khoản, mua sách, ebook, đơn hàng và trả hàng được tập trung tại Trung tâm trợ giúp.',
        link: { label: 'Mở Trung tâm trợ giúp', to: '/help-center' },
      },
      {
        icon: 'support_agent',
        title: 'Gửi yêu cầu hỗ trợ',
        body: 'Người dùng đã đăng nhập có thể tạo ticket, theo dõi trạng thái và trao đổi trong cùng một luồng hỗ trợ.',
        link: { label: 'Đi tới trang hỗ trợ', to: '/support' },
      },
      {
        icon: 'description',
        title: 'Chuẩn bị thông tin cần thiết',
        body: 'Hãy cung cấp mã đơn hàng hoặc mã ticket liên quan và mô tả vấn đề. Không gửi mật khẩu, mã OTP hoặc thông tin thanh toán nhạy cảm.',
      },
    ],
    note: 'Thời gian phản hồi phụ thuộc loại yêu cầu và trạng thái vận hành; KomiBook không hiển thị cam kết thời gian khi chưa có cấu hình chính thức.',
    cta: { label: 'Xem câu hỏi thường gặp', to: '/faq', icon: 'quiz' },
  },
  faq: {
    eyebrow: 'Câu hỏi thường gặp',
    title: 'Những điều cần biết trước khi mua, đọc hoặc xuất bản',
    intro: 'Các câu trả lời dưới đây tóm tắt hành vi hiện có của hệ thống. Chính sách chi tiết được liên kết tại cuối trang.',
    sections: [
      {
        icon: 'download',
        title: 'Ebook có được trả lại không?',
        body: 'Không. Ebook là nội dung số và không thuộc luồng trả hàng sau khi mua. Điều khoản này phải được xác nhận trước khi đặt đơn có ebook.',
      },
      {
        icon: 'history',
        title: 'Ebook cập nhật sau khi tôi mua thì sao?',
        body: 'Bạn vẫn giữ quyền đọc từ phiên bản đã mua và có thể chọn các phiên bản mới hơn trong trình đọc khi chúng được phát hành.',
      },
      {
        icon: 'assignment_return',
        title: 'Sản phẩm nào có thể yêu cầu trả hàng?',
        body: 'Luồng trả hàng hiện dành cho sách cũ vật lý đủ điều kiện, theo thời hạn và trạng thái được lưu trong chính sách của sản phẩm.',
      },
      {
        icon: 'gpp_maybe',
        title: 'Nếu sách cũ có dấu hiệu giả thì sao?',
        body: 'Người cung cấp chịu trách nhiệm về tính xác thực. Khách hàng có thể mở tranh chấp hàng giả và cung cấp bằng chứng qua luồng hỗ trợ.',
      },
      {
        icon: 'person_add',
        title: 'Tôi muốn bán sách trên KomiBook thì làm gì?',
        body: 'Đơn vị kinh doanh đăng ký Nhà bán và khai báo chuỗi cung ứng. Cá nhân bán lại sách đã qua sử dụng có thể dùng khu vực Người bán sách cũ.',
      },
    ],
    note: 'Nếu tình huống của bạn không nằm trong danh sách, hãy tra cứu Trung tâm trợ giúp hoặc gửi ticket sau khi đăng nhập.',
    cta: { label: 'Mở Trung tâm trợ giúp', to: '/help-center', icon: 'help' },
  },
}

const page = computed(() => pages[route.meta.pageKey] || pages.about)
</script>

<template>
  <article class="mx-auto w-full max-w-[1120px] px-4 py-10 sm:py-14 md:px-8 lg:py-20">
    <header class="max-w-3xl">
      <p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary">{{ page.eyebrow }}</p>
      <h1 class="mt-3 text-3xl font-black leading-tight text-primary sm:text-4xl lg:text-5xl">{{ page.title }}</h1>
      <p class="mt-5 text-base leading-7 text-on-surface-variant sm:text-lg">{{ page.intro }}</p>
    </header>

    <div class="mt-10 grid gap-4 md:grid-cols-2 lg:mt-14">
      <section
        v-for="section in page.sections"
        :key="section.title"
        class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-soft sm:p-6"
      >
        <span class="material-symbols-outlined flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary" aria-hidden="true">
          {{ section.icon }}
        </span>
        <h2 class="mt-4 text-xl font-bold text-on-surface">{{ section.title }}</h2>
        <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ section.body }}</p>
        <RouterLink
          v-if="section.link"
          :to="section.link.to"
          class="mt-4 inline-flex min-h-11 items-center font-bold text-primary hover:underline"
        >
          {{ section.link.label }}
          <span class="material-symbols-outlined ml-1 text-lg" aria-hidden="true">arrow_forward</span>
        </RouterLink>
      </section>
    </div>

    <aside class="mt-8 rounded-2xl border border-tertiary/20 bg-tertiary-container/30 p-5 text-sm leading-6 text-on-tertiary-container sm:p-6">
      {{ page.note }}
    </aside>

    <div class="mt-8">
      <RouterLink
        :to="page.cta.to"
        class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-5 py-3 font-bold text-on-primary shadow-sm transition hover:bg-primary/90"
      >
        {{ page.cta.label }}
        <span class="material-symbols-outlined" aria-hidden="true">{{ page.cta.icon }}</span>
      </RouterLink>
    </div>
  </article>
</template>
