<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chatStore'
import apiClient from '@/services/axios'

const authStore = useAuthStore()
const chatStore = useChatStore()

const totalBooks = ref(0)
const totalEbooks = ref(0)
const totalOrganizations = ref(0)
const statsLoading = ref(true)

const fetchRealStats = async () => {
  statsLoading.value = true
  try {
    const [booksRes, ebooksRes, orgsRes] = await Promise.allSettled([
      apiClient.get('/api/books', { params: { per_page: 1 } }),
      apiClient.get('/api/books', { params: { type: 'ebook', per_page: 1 } }),
      apiClient.get('/api/organizations'),
    ])

    if (booksRes.status === 'fulfilled') {
      const res = booksRes.value.data
      totalBooks.value = res?.meta?.total ?? res?.total ?? res?.data?.total ?? (Array.isArray(res?.data) ? res.data.length : 0)
    }

    if (ebooksRes.status === 'fulfilled') {
      const res = ebooksRes.value.data
      totalEbooks.value = res?.meta?.total ?? res?.total ?? res?.data?.total ?? (Array.isArray(res?.data) ? res.data.length : 0)
    }

    if (orgsRes.status === 'fulfilled') {
      const res = orgsRes.value.data
      totalOrganizations.value = res?.data?.total ?? res?.total ?? (Array.isArray(res?.data) ? res.data.length : 0)
    }
  } catch (e) {
    console.error('Failed to load real stats:', e)
  } finally {
    statsLoading.value = false
  }
}

onMounted(fetchRealStats)

const formattedBookStats = computed(() => {
  if (statsLoading.value) return '...'
  if (totalBooks.value > 0) {
    return `${totalBooks.value.toLocaleString('vi-VN')}+`
  }
  return '0'
})

const formattedOrgStats = computed(() => {
  if (statsLoading.value) return '...'
  if (totalOrganizations.value > 0) {
    return `${totalOrganizations.value.toLocaleString('vi-VN')}+`
  }
  return '0'
})

const stats = computed(() => [
  { value: formattedBookStats.value, label: 'Đầu sách & Ebook trong hệ thống', icon: 'menu_book', color: 'text-primary' },
  { value: formattedOrgStats.value, label: 'NXB & Tổ chức đối tác', icon: 'storefront', color: 'text-emerald-600' },
  { value: '100%', label: 'Minh bạch xuất xứ & Vận hành', icon: 'verified', color: 'text-blue-600' },
  { value: '24/7', label: 'Trợ lý AI & Hỗ trợ độc giả', icon: 'support_agent', color: 'text-amber-600' },
])

const pillars = [
  {
    icon: 'auto_stories',
    title: 'Đọc đa định dạng thông minh',
    body: 'Trải nghiệm đọc E-book mượt mà với trình đọc tích hợp ghi chú, chỉnh font, lưu tiến độ tự động. Kết hợp đặt mua sách giấy trực tiếp từ nhà phát hành.',
    badge: 'Trải nghiệm Đọc',
  },
  {
    icon: 'shield_person',
    title: 'Vận hành phân quyền minh bạch',
    body: 'Hệ thống phân định rõ ràng 5 vai trò: Độc giả, Nhà bán (Vendor), Quản kho (Warehouse Manager), Người bán sách cũ và Quản trị viên (Admin).',
    badge: 'Quản trị Hệ thống',
  },
  {
    icon: 'cyclone',
    title: 'Thị trường Sách cũ C2C',
    body: 'Kết nối độc giả mua bán lại sách đã qua sử dụng. Khai báo minh bạch tình trạng sách, ảnh thực tế và địa chỉ gửi hàng bảo mật.',
    badge: 'Kinh tế Tuần hoàn',
  },
]

const features = [
  {
    icon: 'devices',
    title: 'Thư viện E-book Bản quyền',
    description: 'Quyền đọc được gắn liền với tài khoản và đơn hàng snapshot. Độc giả luôn giữ quyền đọc bản đã mua kể cả khi tác phẩm ra phiên bản mới.',
  },
  {
    icon: 'workspace_premium',
    title: 'Chương trình VIP & Tích điểm',
    description: 'Tích lũy điểm từ mọi đơn mua sách để nâng hạng VIP (Đồng, Bạc, Vàng, Kim Cương) và hưởng các ưu đãi chiết khấu Ebook, phí giao hàng.',
  },
  {
    icon: 'account_balance_wallet',
    title: 'Ví KomiBook an toàn',
    description: 'Quản lý số dư tiền hoàn, doanh thu bán sách cũ và doanh thu Nhà bán. Hỗ trợ tạo yêu cầu rút tiền về ngân hàng minh bạch.',
  },
  {
    icon: 'gpp_good',
    title: 'Bảo vệ Người mua & Tranh chấp',
    description: 'Chính sách trả hàng sách vật lý và quy trình khiếu nại hàng giả có dấu vết bảo vệ quyền lợi độc giả tối đa.',
  },
]
</script>

<template>
  <div class="min-h-screen bg-background">
    <!-- Hero Section -->
    <section class="relative overflow-hidden border-b border-outline-variant/20 bg-gradient-to-b from-surface-container-low via-surface-container-lowest to-background py-12 sm:py-16 lg:py-20">
      <div class="mx-auto w-full max-w-[1280px] px-4 md:px-gutter">
        <div class="grid items-center gap-10 lg:grid-cols-12">
          
          <!-- Hero Text -->
          <div class="flex flex-col gap-5 lg:col-span-7">
            <div class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3.5 py-1.5 self-start">
              <span class="material-symbols-outlined text-base text-primary" aria-hidden="true">auto_awesome</span>
              <span class="text-xs font-bold uppercase tracking-wider text-primary">Về KomiBook</span>
            </div>

            <h1 class="font-inter text-3xl font-black leading-tight text-on-surface sm:text-4xl lg:text-5xl">
              Không gian kết nối độc giả & người làm sách
            </h1>

            <p class="text-base leading-relaxed text-on-surface-variant sm:text-lg">
              KomiBook kết hợp hiệu sách đa gian hàng, thư viện E-book bản quyền và thị trường sách cũ C2C trong một trải nghiệm minh bạch, có dấu vết và chuẩn mực.
            </p>

            <!-- Quick Action Links -->
            <div class="mt-2 flex flex-wrap gap-3">
              <RouterLink
                to="/catalog"
                class="group inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-6 py-3 font-bold text-on-primary shadow-sm transition-all duration-200 hover:bg-primary-container hover:shadow-md active:scale-95 no-underline"
              >
                <span>Khám phá Tủ sách</span>
                <span class="material-symbols-outlined transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">arrow_forward</span>
              </RouterLink>

              <RouterLink
                to="/vendor/register"
                class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-outline-variant/40 bg-surface-container-lowest px-5 py-3 font-bold text-on-surface no-underline shadow-2xs transition-all duration-200 hover:border-primary/40 hover:bg-surface-container-low hover:text-primary active:scale-95"
              >
                <span class="material-symbols-outlined text-primary" aria-hidden="true">storefront</span>
                <span>Đăng ký Nhà bán</span>
              </RouterLink>

              <RouterLink
                to="/used-books/manage"
                class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-outline-variant/40 bg-surface-container-lowest px-5 py-3 font-bold text-on-surface no-underline shadow-2xs transition-all duration-200 hover:border-primary/40 hover:bg-surface-container-low hover:text-primary active:scale-95"
              >
                <span class="material-symbols-outlined text-emerald-600" aria-hidden="true">cyclone</span>
                <span>Bán sách cũ</span>
              </RouterLink>
            </div>
          </div>

          <!-- Hero Visual Card -->
          <div class="lg:col-span-5">
            <div class="relative rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-soft sm:p-8">
              <div class="flex items-center gap-4 border-b border-outline-variant/15 pb-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                  <span class="material-symbols-outlined text-3xl" aria-hidden="true">menu_book</span>
                </div>
                <div>
                  <h3 class="font-bold text-on-surface text-lg">Hệ Sinh Thái KomiBook</h3>
                  <p class="text-xs text-on-surface-variant">Thương mại sách chuẩn hóa 2026</p>
                </div>
              </div>

              <div class="mt-5 space-y-3">
                <div class="flex items-start gap-3 rounded-2xl bg-surface-container-low p-3.5">
                  <span class="material-symbols-outlined text-primary text-xl mt-0.5" aria-hidden="true">check_circle</span>
                  <div>
                    <strong class="text-xs text-on-surface block font-bold">Thương mại có dấu vết</strong>
                    <span class="text-xs text-on-surface-variant">Đơn hàng, snapshot điều khoản & xử lý tranh chấp lưu đầy đủ lịch sử.</span>
                  </div>
                </div>

                <div class="flex items-start gap-3 rounded-2xl bg-surface-container-low p-3.5">
                  <span class="material-symbols-outlined text-emerald-600 text-xl mt-0.5" aria-hidden="true">check_circle</span>
                  <div>
                    <strong class="text-xs text-on-surface block font-bold">Không dữ liệu ước đoán</strong>
                    <span class="text-xs text-on-surface-variant">Mọi tính năng hiển thị đúng trạng thái thực, không tạo nút giả.</span>
                  </div>
                </div>

                <div class="flex items-start gap-3 rounded-2xl bg-surface-container-low p-3.5">
                  <span class="material-symbols-outlined text-blue-600 text-xl mt-0.5" aria-hidden="true">check_circle</span>
                  <div>
                    <strong class="text-xs text-on-surface block font-bold">Bảo vệ quyền tác giả</strong>
                    <span class="text-xs text-on-surface-variant">Cam kết nguồn gốc nhà xuất bản & hỗ trợ giải quyết sách giả.</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Real Stats Section (Fetched dynamically from API) -->
    <section class="py-10 border-b border-outline-variant/15 bg-surface-container-lowest">
      <div class="mx-auto w-full max-w-[1280px] px-4 md:px-gutter">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 sm:gap-6">
          <div
            v-for="stat in stats"
            :key="stat.label"
            class="flex flex-col items-center rounded-2xl border border-outline-variant/20 bg-surface-container-low/50 p-5 text-center transition-all duration-200 hover:-translate-y-1 hover:bg-surface-container-low hover:shadow-xs"
          >
            <span class="material-symbols-outlined text-3xl mb-2" :class="stat.color" aria-hidden="true">{{ stat.icon }}</span>
            <span class="font-inter text-2xl font-black text-on-surface sm:text-3xl">{{ stat.value }}</span>
            <span class="mt-1 text-xs font-semibold text-on-surface-variant">{{ stat.label }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- 3 Core Pillars -->
    <section class="py-12 sm:py-16 lg:py-20">
      <div class="mx-auto w-full max-w-[1280px] px-4 md:px-gutter">
        <div class="mx-auto max-w-2xl text-center">
          <span class="text-xs font-bold uppercase tracking-widest text-primary">Giá Trị Cốt Lõi</span>
          <h2 class="mt-2 text-2xl font-black text-on-surface sm:text-3xl lg:text-4xl">Ba Trụ Cột Vận Hành Của KomiBook</h2>
          <p class="mt-3 text-sm text-on-surface-variant leading-relaxed">Xây dựng trải nghiệm đọc và mua bán sách minh bạch, tin cậy cho toàn bộ cộng đồng.</p>
        </div>

        <div class="mt-10 grid gap-6 md:grid-cols-3">
          <article
            v-for="pillar in pillars"
            :key="pillar.title"
            class="group relative flex flex-col justify-between rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-soft transition-all duration-300 hover:-translate-y-1.5 hover:shadow-md sm:p-8"
          >
            <div>
              <div class="flex items-center justify-between">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-110">
                  <span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ pillar.icon }}</span>
                </span>
                <span class="rounded-full bg-surface-container-high px-3 py-1 text-[11px] font-bold text-on-surface-variant">
                  {{ pillar.badge }}
                </span>
              </div>

              <h3 class="mt-6 text-xl font-bold text-on-surface">{{ pillar.title }}</h3>
              <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ pillar.body }}</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- Feature Ecosystem Grid -->
    <section class="border-t border-outline-variant/20 bg-surface-container-low/40 py-12 sm:py-16 lg:py-20">
      <div class="mx-auto w-full max-w-[1280px] px-4 md:px-gutter">
        <div class="mx-auto max-w-2xl text-center">
          <span class="text-xs font-bold uppercase tracking-widest text-primary">Tính Năng Hệ Thống</span>
          <h2 class="mt-2 text-2xl font-black text-on-surface sm:text-3xl">Hệ Sinh Thái Độc Giả & Thương Mại</h2>
        </div>

        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="item in features"
            :key="item.title"
            class="flex flex-col gap-3 rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-2xs transition-all hover:border-primary/30 hover:shadow-xs"
          >
            <span class="material-symbols-outlined text-2xl text-primary" aria-hidden="true">{{ item.icon }}</span>
            <h3 class="text-base font-bold text-on-surface">{{ item.title }}</h3>
            <p class="text-xs leading-relaxed text-on-surface-variant">{{ item.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Bottom CTA Card -->
    <section class="py-12 sm:py-16">
      <div class="mx-auto w-full max-w-[1280px] px-4 md:px-gutter">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-primary via-primary-container to-secondary p-8 text-on-primary shadow-elevated sm:p-12">
          <div class="relative z-10 max-w-2xl space-y-4">
            <h2 class="text-2xl font-black sm:text-3xl lg:text-4xl text-white">Sẵn sàng trải nghiệm KomiBook?</h2>
            <p class="text-sm sm:text-base text-primary-fixed-dim leading-relaxed">
              Khám phá hàng nghìn đầu sách hay, đọc E-book bản quyền hoặc tham gia cộng đồng bán lại sách cũ ngay hôm nay.
            </p>
            <div class="pt-2 flex flex-wrap gap-3">
              <RouterLink
                to="/catalog"
                class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-white px-6 py-3 font-bold text-primary shadow-xs transition hover:bg-slate-100 no-underline"
              >
                <span>Vào Tủ Sách</span>
                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
              </RouterLink>

              <button
                type="button"
                class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-6 py-3 font-bold text-white transition hover:bg-white/20 cursor-pointer"
                @click="chatStore.openConversationList()"
              >
                <span class="material-symbols-outlined" aria-hidden="true">forum</span>
                <span>Hỗ trợ tư vấn</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
button:not([tabindex="-1"]) {
  min-height: 44px;
}
</style>
