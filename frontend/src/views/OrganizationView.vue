<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import Button from 'primevue/button'

const route = useRoute()
const organization = ref(null)
const loading = ref(true)
const error = ref('')
const typeLabels = { publisher: 'Nhà xuất bản', supplier: 'Nhà cung cấp', distributor: 'Nhà phân phối', bookstore: 'Nhà sách' }
const relationshipLabels = { self_legal_entity: 'Pháp nhân trực tiếp', publisher_partner: 'Đối tác xuất bản', supplier_partner: 'Đối tác cung ứng', authorized_distributor: 'Nhà phân phối được ủy quyền' }

const formatCurrency = (val) => {
  if (!val) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get(`/api/organizations/${route.params.slug}`)
    organization.value = response.data.data
  } catch (exception) {
    error.value = exception.response?.status === 404 ? 'Không tìm thấy tổ chức đã xác minh.' : 'Không thể tải hồ sơ tổ chức.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="min-h-screen bg-slate-50/60 px-4 py-8 sm:px-6 lg:px-8" tabindex="-1">
    <div class="mx-auto max-w-6xl space-y-8">
      <!-- Loading Skeleton -->
      <div v-if="loading" class="h-80 animate-pulse rounded-3xl bg-slate-200/60" role="status"></div>

      <!-- Error Alert -->
      <section v-else-if="error" class="rounded-3xl bg-rose-50 p-8 border border-rose-200 text-rose-900 text-center space-y-4" role="alert">
        <span class="material-symbols-outlined text-4xl text-rose-500">error</span>
        <p class="font-bold text-base">{{ error }}</p>
        <Button label="Thử lại" icon="pi pi-refresh" severity="danger" class="min-h-11 font-bold text-xs" @click="load" />
      </section>

      <!-- Main Showcase Content -->
      <template v-else-if="organization">
        <!-- Hero Publisher Banner -->
        <header class="relative overflow-hidden rounded-3xl bg-white border border-slate-200/90 p-6 sm:p-8 shadow-sm">
          <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
            <!-- Organization Logo -->
            <div class="flex h-28 w-28 shrink-0 items-center justify-center rounded-2xl bg-slate-50 border border-slate-100 p-2 shadow-2xs">
              <img v-if="organization.logo" :src="organization.logo" :alt="`Logo ${organization.display_name}`" class="h-full w-full object-contain" />
              <span v-else class="material-symbols-outlined text-5xl text-[#00b14f]">apartment</span>
            </div>

            <!-- Organization Details -->
            <div class="space-y-2 max-w-3xl">
              <div class="flex flex-wrap items-center gap-2">
                <span v-for="type in organization.organization_types" :key="type" class="rounded-full bg-emerald-50 border border-emerald-200/70 px-3 py-0.5 text-xs font-extrabold text-[#00b14f]">
                  {{ typeLabels[type] || type }}
                </span>
                <span v-if="organization.data_mode === 'demo'" class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-3 py-0.5 text-xs font-bold text-amber-900">
                  <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                  Đã xác minh Demo
                </span>
                <span v-else class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-0.5 text-xs font-bold text-emerald-900">
                  <span class="material-symbols-outlined text-sm">verified</span>
                  Đã xác minh chính thức
                </span>
              </div>

              <h1 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight">{{ organization.display_name }}</h1>
              <p v-if="organization.legal_name" class="text-xs font-bold text-slate-500">Tên pháp lý: <strong class="text-slate-800">{{ organization.legal_name }}</strong></p>
              <p v-if="organization.description" class="text-xs sm:text-sm leading-relaxed text-slate-600 pt-1">{{ organization.description }}</p>
            </div>
          </div>

          <!-- Public Transparency Strip -->
          <div class="mt-6 pt-4 border-t border-slate-100 grid gap-3 sm:grid-cols-3 text-xs">
            <div v-if="organization.tax_code" class="flex items-center gap-2 text-slate-600">
              <span class="material-symbols-outlined text-base text-slate-400">badge</span>
              <span>MST: <strong class="font-mono text-slate-800">{{ organization.tax_code }}</strong></span>
            </div>
            <div v-if="organization.license_number" class="flex items-center gap-2 text-slate-600">
              <span class="material-symbols-outlined text-base text-slate-400">policy</span>
              <span>Số GP: <strong class="font-mono text-slate-800">{{ organization.license_number }}</strong></span>
            </div>
            <div v-if="organization.website" class="flex items-center gap-2 text-slate-600">
              <span class="material-symbols-outlined text-base text-slate-400">language</span>
              <a :href="organization.website" target="_blank" rel="noopener" class="font-bold text-[#00b14f] hover:underline truncate">{{ organization.website }}</a>
            </div>
          </div>
        </header>

        <!-- Demo Note Disclaimer -->
        <aside v-if="organization.data_mode === 'demo'" class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs leading-relaxed text-amber-950 flex items-start gap-3">
          <span class="material-symbols-outlined text-xl text-amber-600 shrink-0">info</span>
          <div>
            <strong class="font-extrabold block mb-0.5">Dữ liệu mô phỏng phục vụ trình diễn hệ thống (Demo Mode)</strong>
            <span>Hồ sơ này đại diện cho pháp nhân NXB/Nhà cung cấp đã được xác nhận quyền ủy quyền xuất bản trên hệ thống KomiBook.</span>
            <a v-if="organization.public_source_url" :href="organization.public_source_url" target="_blank" rel="noopener" class="ml-1 font-bold text-amber-900 underline">Xem nguồn công khai</a>
          </div>
        </aside>

        <!-- SECTION 1: PUBLISHED & AUTHORIZED BOOKS SHOWCASE -->
        <section class="space-y-4" aria-labelledby="published-books-title">
          <div class="flex items-center justify-between border-b border-slate-200/80 pb-3">
            <div>
              <h2 id="published-books-title" class="text-xl font-black text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#00b14f]">menu_book</span>
                <span>Ấn phẩm Sách Xuất bản & Ủy quyền Phát hành</span>
              </h2>
              <p class="mt-0.5 text-xs text-slate-500">Các đầu sách chính thức do {{ organization.display_name }} giữ bản quyền xuất bản hoặc cung cấp.</p>
            </div>
            <RouterLink to="/catalog" class="inline-flex items-center gap-1 text-xs font-bold text-[#00b14f] hover:underline no-underline">
              <span>Xem tất cả trên Catalog</span>
              <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </RouterLink>
          </div>

          <div v-if="!organization.published_books?.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-xs text-slate-400">
            Chưa có ấn phẩm sách nào gắn với tổ chức này trên hệ thống.
          </div>

          <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <RouterLink
              v-for="book in organization.published_books"
              :key="book.id"
              :to="`/books/${book.slug}`"
              class="group flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-4 shadow-xs transition-all hover:border-[#00b14f]/50 hover:shadow-md no-underline"
            >
              <div class="space-y-3">
                <div class="relative aspect-3/4 overflow-hidden rounded-xl bg-slate-100 border border-slate-100">
                  <img v-if="book.cover_image" :src="book.cover_image" :alt="book.title" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" />
                  <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                    <span class="material-symbols-outlined text-4xl">book</span>
                  </div>
                </div>
                <div>
                  <h3 class="font-extrabold text-slate-900 text-sm line-clamp-2 leading-tight group-hover:text-[#00b14f] transition-colors">
                    {{ book.title }}
                  </h3>
                  <p class="mt-1 text-xs text-slate-500 font-medium">{{ book.author || 'Nhiều tác giả' }}</p>
                </div>
              </div>

              <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between">
                <span class="font-black text-[#00b14f] text-sm">{{ formatCurrency(book.price) }}</span>
                <span v-if="book.rating" class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">
                  ★ {{ book.rating }}
                </span>
              </div>
            </RouterLink>
          </div>
        </section>

        <!-- SECTION 2: AUTHORIZED PARTNER SHOPS NETWORK -->
        <section class="space-y-4" aria-labelledby="partner-shops-title">
          <div class="border-b border-slate-200/80 pb-3">
            <h2 id="partner-shops-title" class="text-xl font-black text-slate-900 flex items-center gap-2">
              <span class="material-symbols-outlined text-[#00b14f]">storefront</span>
              <span>Mạng lưới Gian hàng Phân phối Chính thức</span>
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">Các gian hàng bán lẻ được cấp quyền phân phối sách của {{ organization.display_name }}.</p>
          </div>

          <div v-if="!organization.partner_shops?.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-xs text-slate-400">
            Chưa có gian hàng đối tác ủy quyền đang hoạt động.
          </div>

          <div v-else class="grid gap-4 md:grid-cols-2">
            <div
              v-for="shop in organization.partner_shops"
              :key="`${shop.shop_slug}-${shop.role}`"
              class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs"
            >
              <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-[#00b14f] border border-emerald-100">
                  <img v-if="shop.shop_logo" :src="shop.shop_logo" :alt="shop.shop_name" class="h-full w-full object-contain rounded-xl" />
                  <span v-else class="material-symbols-outlined text-2xl">store</span>
                </div>
                <div>
                  <h3 class="font-extrabold text-slate-900 text-sm">{{ shop.shop_name }}</h3>
                  <span class="text-xs text-slate-500 font-medium">{{ relationshipLabels[shop.role] || shop.role }}</span>
                </div>
              </div>

              <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-[#00b14f] border border-emerald-200/60">
                <span class="material-symbols-outlined text-xs">verified</span>
                <span>Ủy quyền chính thức</span>
              </span>
            </div>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>
