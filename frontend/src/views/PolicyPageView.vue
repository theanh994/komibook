<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import { useChatStore } from '@/stores/chatStore'

const chatStore = useChatStore()
const route = useRoute()

const policies = ref({})
const policyLoading = ref(false)
const policyLoadFailed = ref(false)
const sectionQuery = ref('')

const policyTabNav = [
  { key: 'terms', label: 'Điều khoản sử dụng', to: '/terms', icon: 'gavel' },
  { key: 'privacy', label: 'Chính sách bảo mật', icon: 'shield_lock', to: '/privacy' },
  { key: 'copyright', label: 'Bản quyền & Hàng giả', icon: 'gpp_maybe', to: '/policies/copyright' },
  { key: 'ebooks', label: 'Chính sách Ebook', icon: 'devices', to: '/policies/ebooks' },
  { key: 'usedBooks', label: 'Sách cũ & Trả hàng', icon: 'cyclone', to: '/policies/used-books' },
]

const pages = {
  terms: {
    eyebrow: 'Điều khoản sử dụng',
    title: 'Nguyên Tắc & Điều Khoản Sử Dụng KomiBook',
    summary: 'Phiên bản hệ thống 1.0 · Cập nhật mới nhất',
    intro: 'Điều khoản này quy định quyền, trách nhiệm và nghĩa vụ của người dùng khi tạo tài khoản, mua bán sách giấy, đọc E-book và giao dịch trên nền tảng KomiBook.',
    sections: [
      ['Tài khoản và Bảo mật thông tin', 'Bạn chịu trách nhiệm bảo vệ mật khẩu, mã OTP và phiên đăng nhập cá nhân. Tuyệt đối không chia sẻ thông tin xác thực cho người khác. Hệ thống tự động ghi vết và áp dụng bảo mật 2 lớp cho các thao tác quan trọng.'],
      ['Quy định Mua sách và Thanh toán', 'Thông tin sản phẩm, đơn giá, định dạng (Sách giấy/E-book/Sách cũ), nhà cung cấp và chính sách áp dụng được niêm yết công khai trước khi chốt đơn. Giao dịch chỉ hợp lệ khi cổng thanh toán hoặc hệ thống xác nhận thành công.'],
      ['Ebook và Quyền truy cập số', 'E-book là quyền đọc nội dung số gắn liền với tài khoản mua hàng, không phải quyền chuyển nhượng tệp hay phát tán công khai. Quyền đọc và phiên bản khả dụng tuân thủ đúng quyền bản quyền (entitlement) được snapshot khi tạo đơn.'],
      ['Nội dung & Đánh giá do người dùng đóng góp', 'Mọi bài viết, bình luận, đánh giá hoặc tài liệu tải lên hệ thống phải tuân thủ pháp luật Việt Nam, đúng bản quyền sở hữu và không chứa thông tin vi phạm thuần phong mỹ tục hoặc thông tin sai sự thật.'],
      ['Thay đổi và Duy trì dịch vụ', 'KomiBook có quyền điều chỉnh giao diện, cập nhật tính năng hoặc tạm dừng dịch vụ để bảo trì an ninh. Mọi điều chỉnh chính sách lớn đều được thông báo công khai trước khi có hiệu lực.'],
    ],
    links: [
      ['Chính sách bảo mật', '/privacy'],
      ['Chính sách Ebook', '/policies/ebooks'],
      ['Sách cũ & Trả hàng', '/policies/used-books'],
    ],
  },
  privacy: {
    eyebrow: 'Chính sách bảo mật',
    title: 'Bảo Vệ & Sử Dụng Dữ Liệu An Toàn',
    summary: 'Phiên bản hệ thống 1.0 · Cập nhật mới nhất',
    intro: 'KomiBook cam kết bảo vệ dữ liệu cá nhân của người dùng, chỉ thu thập và xử lý các thông tin cần thiết nhằm phục vụ hoạt động vận hành, giao dịch và đọc sách.',
    sections: [
      ['Dữ liệu Tài khoản & Hồ sơ', 'Tên hiển thị, email, số điện thoại, ảnh đại diện và trạng thái xác minh được lưu trữ bảo mật nhằm mục đích quản lý tài khoản, phân quyền và liên lạc khi cần thiết.'],
      ['Dữ liệu Giao dịch & Đơn hàng', 'Lịch sử mua sách, chính sách snapshot, cổng thanh toán và thông tin hoàn tiền được lưu vết mã hóa để phục vụ thực hiện hợp đồng, kiểm toán và giải quyết khiếu nại.'],
      ['Dữ liệu Đọc số & Trải nghiệm cá nhân', 'Tiến độ đọc E-book, tủ sách cá nhân và danh mục yêu thích được lưu để đồng bộ giữa các thiết bị. KomiBook không tự dựng hay cung cấp dữ liệu giả mạo.'],
      ['Bảo mật Địa chỉ Người bán sách cũ (C2C)', 'Địa chỉ lấy hàng của cá nhân bán sách cũ được lưu trữ riêng tư, mã hóa nội bộ chỉ phục vụ cho đơn vị vận chuyển và không bao giờ hiển thị cho công chúng hay người mua.'],
      ['Chia sẻ & Cam kết An toàn thông tin', 'Dữ liệu chỉ được chia sẻ cho các đối tác vận chuyển và thanh toán trong phạm vi bắt buộc để hoàn thành dịch vụ. Nhân viên KomiBook không bao giờ yêu cầu bạn cung cấp mật khẩu hay OTP.'],
    ],
    links: [
      ['Điều khoản sử dụng', '/terms'],
      ['Bản quyền & Hàng giả', '/policies/copyright'],
      ['Trung tâm trợ giúp', '/help-center'],
    ],
  },
  ebooks: {
    eyebrow: 'Chính sách Ebook',
    title: 'Quyền Đọc Số & Quy Định Không Trả Hàng',
    policyKey: 'ebook_non_returnable',
    summary: 'Phiên bản hệ thống 1.0 · Có hiệu lực tức thì',
    intro: 'Chính sách này áp dụng cho toàn bộ E-book kỹ thuật số trên KomiBook và được snapshot tự động khi giỏ hàng có chứa sản phẩm E-book.',
    sections: [
      ['Phiên bản tại thời điểm đặt mua', 'Khi mua E-book, khách hàng luôn sở hữu quyền đọc phiên bản mới nhất đang được nhà xuất bản/tác giả phát hành chính thức tại thời điểm giao dịch.'],
      ['Không áp dụng Trả hàng & Hoàn tiền', 'Do đặc thù nội dung số có thể truy cập lập tức sau thanh toán, E-book không thuộc luồng trả hàng hay hoàn tiền. Khách hàng phải xác nhận quy định này trước khi hoàn tất thanh toán.'],
      ['Quyền cập nhật phiên bản Ebook', 'Khi nhà xuất bản phát hành bản sửa đổi hoặc tái bản mới hơn, tài khoản đã mua vẫn giữ bản tại thời điểm mua và được quyền chuyển đổi đọc các bản cập nhật mới trong trình đọc mà không mất phí.'],
      ['Bảo vệ Bản quyền Số (DRM)', 'Nội dung E-book được bảo vệ bằng công nghệ mã hóa. Mọi hành vi sao chép, trích xuất tệp, chia sẻ tài khoản hoặc vượt cơ chế bảo vệ đều bị coi là vi phạm điều khoản và có thể bị khóa tài khoản.'],
    ],
    links: [
      ['Điều khoản sử dụng', '/terms'],
      ['Bản quyền & Hàng giả', '/policies/copyright'],
    ],
  },
  usedBooks: {
    eyebrow: 'Sách cũ, Trả hàng & Hoàn tiền',
    title: 'Quy Định Trả Hàng & Xử Lý Tranh Chấp Sách Cũ',
    policyKey: 'used_book_return',
    summary: 'Phiên bản hệ thống 1.0 · Snapshot theo dòng đơn',
    intro: 'Chính sách trả hàng và hoàn tiền hiện áp dụng riêng cho các sản phẩm Sách cũ vật lý (C2C) đủ điều kiện.',
    sections: [
      ['Phạm vi & Thời hạn áp dụng', 'Quyền trả hàng áp dụng cho Sách cũ vật lý khi sản phẩm nhận được không đúng mô tả, hư hỏng hoặc sai lệch tình trạng. Thời hạn gửi yêu cầu tuân thủ đúng khung thời gian snapshot trong đơn hàng.'],
      ['Yêu cầu Bằng chứng & Tình trạng', 'Người mua cần cung cấp hình ảnh/video mở gói hàng và mô tả rõ lý do. Số lượng yêu cầu hoàn tiền không được vượt quá số lượng sản phẩm thực tế trong đơn.'],
      ['Trách nhiệm của Người bán sách cũ', 'Người đăng bán sách cũ chịu trách nhiệm hoàn toàn về tính chính xác của hình ảnh, mô tả tình trạng sách và tính hợp pháp của cuốn sách bán ra.'],
      ['Xử lý Tranh chấp Sách giả', 'Nếu phát hiện sách cũ có dấu hiệu in lậu hay giả mạo, người mua có quyền mở tranh chấp hàng giả. KomiBook sẽ niêm phong giao dịch, hoàn tiền cho người mua và xử lý tài khoản vi phạm.'],
      ['Bảo vệ Địa chỉ cá nhân Người bán', 'Địa chỉ người bán đăng ký chỉ phục vụ việc lấy hàng vận chuyển, được lưu trữ bảo mật và không bị tiết lộ trên hóa đơn hay giao diện công khai.'],
    ],
    links: [
      ['Bản quyền & Hàng giả', '/policies/copyright'],
      ['Trung tâm trợ giúp', '/help-center'],
    ],
  },
  copyright: {
    eyebrow: 'Bản quyền & Hàng giả',
    title: 'Tôn Trọng Quyền Tác Giả & Cam Kết Sách Thật',
    summary: 'Phiên bản hệ thống 1.0 · Bảo hộ quyền sở hữu trí tuệ',
    intro: 'KomiBook kiên quyết nói không với sách lậu, sách giả và mọi hành vi vi phạm bản quyền tác giả theo Luật Sở hữu trí tuệ Việt Nam.',
    sections: [
      ['Trách nhiệm của Nhà xuất bản & Người đăng', 'Tất cả đơn vị phát hành, nhà xuất bản và cá nhân đăng tải sách/bản thảo phải đảm bảo có đầy đủ bản quyền tác giả, hợp đồng dịch thuật và giấy phép xuất bản hợp pháp.'],
      ['Khai báo Minh bạch Nguồn gốc', 'Gian hàng và Người bán phải khai báo chính xác Nhà xuất bản, công ty phát hành và xuất xứ sách. Mọi khai báo sai lệch đều bị xử lý theo quy chế vận hành.'],
      ['Quy trình Xử lý Nghi vấn Sách giả', 'Khi nhận được phản ánh nghi ngờ sách lậu/sách giả, KomiBook sẽ tạm dừng giao dịch mặt hàng đó để xác minh. Nếu vi phạm, sản phẩm sẽ bị gỡ bỏ vĩnh viễn và hoàn tiền cho người mua.'],
      ['Báo cáo Vi phạm Bản quyền (DMCA / IP Notice)', 'Chủ sở hữu bản quyền có thể gửi thông báo vi phạm kèm bằng chứng sở hữu qua kênh Hỗ trợ. Đội ngũ pháp lý của KomiBook sẽ xử lý trong vòng 24-48 giờ làm việc.'],
      ['Xử lý Thận trọng & Bảo mật thông tin', 'KomiBook phối hợp xử lý trên tinh thần tôn trọng pháp luật, bảo mật thông tin các bên và bảo vệ quyền lợi chính đáng của độc giả lẫn tác giả.'],
    ],
    links: [
      ['Trung tâm trợ giúp', '/help-center'],
      ['Chính sách sách cũ', '/policies/used-books'],
    ],
  },
}

const page = computed(() => pages[route.meta.pageKey] || pages.terms)
const activePolicy = computed(() => page.value.policyKey ? policies.value[page.value.policyKey] : null)

const versionLabel = computed(() => {
  if (activePolicy.value) {
    return `Chính sách hệ thống v${activePolicy.value.version} · Có hiệu lực từ ${new Date(activePolicy.value.active_from).toLocaleDateString('vi-VN')}`
  }
  return page.value.summary
})

const filteredSections = computed(() => {
  const q = sectionQuery.value.trim().toLowerCase()
  if (!q) return page.value.sections
  return page.value.sections.filter(([title, body]) => {
    return title.toLowerCase().includes(q) || body.toLowerCase().includes(q)
  })
})

const loadPolicies = async () => {
  if (!page.value.policyKey) return
  policyLoading.value = true
  policyLoadFailed.value = false
  try {
    const response = await apiClient.get('/api/policies/returns')
    policies.value = response.data?.data || {}
  } catch {
    policyLoadFailed.value = true
  } finally {
    policyLoading.value = false
  }
}

const printPolicy = () => {
  window.print()
}

onMounted(loadPolicies)
</script>

<template>
  <div class="policy-page min-h-screen bg-background pb-16 pt-6">
    <div class="mx-auto w-full max-w-[1280px] px-4 md:px-gutter space-y-8">
      
      <!-- Top Policy Switcher Nav Tabs -->
      <nav class="flex overflow-x-auto gap-2 pb-2 scrollbar-none border-b border-outline-variant/20" aria-label="Danh mục chính sách">
        <RouterLink
          v-for="tab in policyTabNav"
          :key="tab.key"
          :to="tab.to"
          class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all border cursor-pointer no-underline"
          :class="route.meta.pageKey === tab.key
            ? 'bg-primary text-on-primary border-primary shadow-xs'
            : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant/30 hover:border-primary/40 hover:bg-surface-container-low'"
        >
          <span class="material-symbols-outlined text-base" aria-hidden="true">{{ tab.icon }}</span>
          <span>{{ tab.label }}</span>
        </RouterLink>
      </nav>

      <!-- Main Policy Container Card -->
      <div class="rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-6 sm:p-10 lg:p-12 shadow-soft space-y-8">
        
        <!-- Header Banner -->
        <header class="space-y-4 border-b border-outline-variant/15 pb-8">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3.5 py-1 text-xs font-bold text-primary uppercase tracking-wider">
              <span class="material-symbols-outlined text-base" aria-hidden="true">verified_user</span>
              <span>{{ page.eyebrow }}</span>
            </span>

            <button
              type="button"
              class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-outline-variant/30 bg-surface-container-low px-3.5 py-1.5 text-xs font-bold text-on-surface-variant hover:bg-surface-container-high cursor-pointer print:hidden"
              @click="printPolicy"
            >
              <span class="material-symbols-outlined text-base" aria-hidden="true">print</span>
              <span>In tài liệu</span>
            </button>
          </div>

          <h1 class="text-2xl font-black text-on-surface sm:text-3xl lg:text-4xl leading-tight">
            {{ page.title }}
          </h1>

          <p class="text-sm sm:text-base text-on-surface-variant leading-relaxed max-w-3xl">
            {{ page.intro }}
          </p>

          <div class="flex flex-wrap items-center gap-3 pt-2">
            <span v-if="versionLabel" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary bg-primary/5 px-3 py-1 rounded-full border border-primary/15">
              <span class="material-symbols-outlined text-sm" aria-hidden="true">info</span>
              {{ versionLabel }}
            </span>
            <span v-else-if="policyLoading" class="text-xs text-on-surface-variant animate-pulse">Đang kiểm tra phiên bản chính sách hệ thống...</span>
          </div>

          <!-- Section Filter Input Bar -->
          <div class="pt-4 max-w-md print:hidden">
            <div class="flex items-center gap-2 rounded-2xl border border-outline-variant/30 bg-surface-container-low px-3 py-2 text-xs">
              <span class="material-symbols-outlined text-slate-400 text-lg" aria-hidden="true">search</span>
              <input
                v-model="sectionQuery"
                type="text"
                placeholder="Lọc nội dung điều khoản..."
                class="flex-grow bg-transparent text-on-surface placeholder-on-surface-variant/60 border-none outline-none focus:ring-0"
              />
              <button v-if="sectionQuery" type="button" class="font-bold text-slate-500 hover:text-slate-800" @click="sectionQuery = ''">Xóa</button>
            </div>
          </div>
        </header>

        <!-- Specialized Feature Workflow Callout Card for E-books -->
        <section v-if="route.meta.pageKey === 'ebooks'" class="rounded-2xl border border-primary/20 bg-gradient-to-r from-primary/5 to-surface-container-low p-6 space-y-4">
          <div class="flex items-center gap-2 text-primary font-bold text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">devices</span>
            <span>Vòng đời E-book & Quyền đọc số trên KomiBook</span>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-1">
              <div class="font-bold text-on-surface flex items-center gap-1.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-on-primary text-[10px]">1</span>
                <span>Mua quyền đọc</span>
              </div>
              <p class="text-on-surface-variant text-[11px]">Sở hữu bản E-book mới nhất tại thời điểm đặt hàng. Không thuộc luồng đổi trả sau khi hoàn tất thanh toán.</p>
            </div>
            <div class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-1">
              <div class="font-bold text-on-surface flex items-center gap-1.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-on-primary text-[10px]">2</span>
                <span>Tủ sách & Đọc Offline</span>
              </div>
              <p class="text-on-surface-variant text-[11px]">Truy cập ngay trên Web/App KomiBook. Đọc ngoại tuyến không cần mạng sau khi tải về tệp mã hóa.</p>
            </div>
            <div class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-1">
              <div class="font-bold text-on-surface flex items-center gap-1.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-on-primary text-[10px]">3</span>
                <span>Tự động Cập nhật</span>
              </div>
              <p class="text-on-surface-variant text-[11px]">Nhận thông báo và chọn đọc các bản chỉnh lý, tái bản phát hành mới hơn từ nhà xuất bản hoàn toàn miễn phí.</p>
            </div>
          </div>
        </section>

        <!-- Specialized Feature Workflow Callout Card for Used Books -->
        <section v-if="route.meta.pageKey === 'usedBooks'" class="rounded-2xl border border-primary/20 bg-gradient-to-r from-primary/5 to-surface-container-low p-6 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2 text-primary font-bold text-sm">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined" aria-hidden="true">cyclone</span>
              <span>Quy trình 4 bước Trả sách cũ & Hoàn tiền bảo mật</span>
            </div>
            <span v-if="activePolicy?.return_window_days" class="inline-flex items-center gap-1 text-xs bg-primary/10 px-3 py-1 rounded-full text-primary">
              ⏱️ Thời hạn áp dụng: {{ activePolicy.return_window_days }} ngày
            </span>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-1">
              <div class="font-bold text-on-surface flex items-center gap-1.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-on-primary text-[10px]">1</span>
                <span>Tạo yêu cầu</span>
              </div>
              <p class="text-on-surface-variant text-[11px]">Mở yêu cầu trong thời hạn chính sách snapshot của dòng đơn khi sách sai mô tả hoặc hỏng.</p>
            </div>
            <div class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-1">
              <div class="font-bold text-on-surface flex items-center gap-1.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-on-primary text-[10px]">2</span>
                <span>Tải bằng chứng</span>
              </div>
              <p class="text-on-surface-variant text-[11px]">Đính kèm hình ảnh/video bóc hàng. Nếu nghi ngờ hàng giả, hệ thống mở luồng niêm phong tranh chấp.</p>
            </div>
            <div class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-1">
              <div class="font-bold text-on-surface flex items-center gap-1.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-on-primary text-[10px]">3</span>
                <span>Thu hồi lấy hàng</span>
              </div>
              <p class="text-on-surface-variant text-[11px]">Đơn vị vận chuyển lấy hàng tại nhà. Địa chỉ người bán hoàn toàn riêng tư và không tiết lộ.</p>
            </div>
            <div class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 space-y-1">
              <div class="font-bold text-on-surface flex items-center gap-1.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-on-primary text-[10px]">4</span>
                <span>Hoàn tiền tự động</span>
              </div>
              <p class="text-on-surface-variant text-[11px]">Tự động hoàn tiền vào Ví KomiBook hoặc phương thức VNPAY ngay sau khi nghiệm thu hợp lệ.</p>
            </div>
          </div>
        </section>

        <!-- Active System Policy Snapshot Alert Box -->
        <aside
          v-if="activePolicy"
          class="rounded-2xl border border-primary/30 bg-primary/5 p-5 text-xs sm:text-sm leading-relaxed text-on-surface space-y-2"
        >
          <div class="flex items-center gap-2 font-bold text-primary">
            <span class="material-symbols-outlined text-lg" aria-hidden="true">gavel</span>
            <span>Quy định hệ thống đang áp dụng thực tế:</span>
          </div>
          <p class="text-on-surface-variant">{{ activePolicy.terms }}</p>
          <div class="flex flex-wrap gap-4 pt-1 text-xs font-semibold text-primary">
            <span v-if="activePolicy.return_window_days">
              ⏱️ Thời hạn trả hàng: <strong>{{ activePolicy.return_window_days }} ngày</strong>
            </span>
            <span>
              🔒 Trạng thái trả hàng: <strong>{{ activePolicy.is_returnable ? 'Có hỗ trợ' : 'Không thuộc luồng trả hàng' }}</strong>
            </span>
          </div>
        </aside>

        <!-- Policy Sections List -->
        <section class="space-y-4">
          <div v-if="filteredSections.length === 0" class="rounded-2xl border border-dashed border-outline-variant/40 p-8 text-center text-xs text-on-surface-variant">
            Không tìm thấy điều khoản nào khớp với từ khóa "{{ sectionQuery }}".
          </div>

          <article
            v-for="([title, body], index) in filteredSections"
            :key="title"
            class="rounded-2xl border border-outline-variant/25 bg-surface-container-lowest p-5 sm:p-6 space-y-2 hover:border-primary/30 transition-colors"
          >
            <div class="flex items-center gap-3">
              <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary/10 font-black text-primary text-xs shrink-0" aria-hidden="true">
                {{ index + 1 }}
              </span>
              <h2 class="text-base sm:text-lg font-bold text-on-surface">{{ title }}</h2>
            </div>
            <p class="text-xs sm:text-sm leading-relaxed text-on-surface-variant pl-11">
              {{ body }}
            </p>
          </article>
        </section>

        <!-- Related Policies Links -->
        <nav class="pt-6 border-t border-outline-variant/15 space-y-3 print:hidden" aria-label="Chính sách liên quan">
          <h3 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Văn bản chính sách liên quan</h3>
          <div class="flex flex-wrap gap-3">
            <RouterLink
              v-for="([label, to]) in page.links"
              :key="to"
              :to="to"
              class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-outline-variant/30 bg-surface-container-low px-4 py-2 text-xs font-bold text-primary hover:bg-surface-container-high transition-colors no-underline"
            >
              <span>{{ label }}</span>
              <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
            </RouterLink>
          </div>
        </nav>

      </div>

      <!-- Bottom Direct Support & Assistance Card -->
      <section class="rounded-3xl bg-gradient-to-r from-surface-container-low to-surface-container-lowest border border-outline-variant/30 p-8 shadow-soft print:hidden">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
          <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary shrink-0">
              <span class="material-symbols-outlined text-3xl" aria-hidden="true">contact_support</span>
            </div>
            <div>
              <h3 class="font-bold text-on-surface text-lg">Cần giải đáp về các điều khoản & chính sách?</h3>
              <p class="text-xs text-on-surface-variant leading-relaxed mt-0.5">
                Nếu bạn có thắc mắc hoặc cần khiếu nại liên quan đến các quy định trên, hãy liên hệ ngay với đội ngũ hỗ trợ KomiBook.
              </p>
            </div>
          </div>

          <div class="flex flex-wrap gap-3 shrink-0">
            <RouterLink
              to="/help-center"
              class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-outline-variant/40 bg-surface-container-lowest px-5 py-3 font-bold text-on-surface hover:bg-surface-container-low no-underline text-xs"
            >
              <span class="material-symbols-outlined" aria-hidden="true">help</span>
              <span>Trung tâm Trợ giúp</span>
            </RouterLink>

            <button
              type="button"
              class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-6 py-3 font-bold text-on-primary text-xs shadow-xs transition hover:bg-primary-container cursor-pointer"
              @click="chatStore.openConversationList()"
            >
              <span class="material-symbols-outlined" aria-hidden="true">forum</span>
              <span>Gửi thắc mắc cho Trợ lý AI</span>
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

@media print {
  body {
    background: white;
    color: black;
  }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>
