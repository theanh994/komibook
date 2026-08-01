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
const relationshipLabels = { self_legal_entity: 'Pháp nhân của gian hàng', publisher_partner: 'Đối tác xuất bản', supplier_partner: 'Đối tác cung ứng', authorized_distributor: 'Nhà phân phối được ủy quyền' }

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
  <main class="mx-auto min-h-dvh max-w-6xl px-4 py-10 md:px-8" tabindex="-1">
    <div v-if="loading" class="h-72 animate-pulse rounded-2xl bg-surface-container"></div>
    <section v-else-if="error" class="rounded-xl bg-error-container p-6 text-on-error-container" role="alert"><p>{{ error }}</p><Button label="Thử lại" class="mt-4 min-h-11" @click="load" /></section>
    <template v-else-if="organization">
      <header class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 md:p-10">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
          <img v-if="organization.logo" :src="organization.logo" :alt="`Logo ${organization.display_name}`" width="112" height="112" class="h-28 w-28 rounded-xl object-contain" />
          <div><div class="flex flex-wrap items-center gap-2"><span v-for="type in organization.organization_types" :key="type" class="rounded-full bg-secondary-container px-3 py-1 text-sm font-semibold text-on-secondary-container">{{ typeLabels[type] || type }}</span><span v-if="organization.data_mode === 'demo'" class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-900"><i class="pi pi-info-circle mr-1" aria-hidden="true"></i>Đã chấp nhận dữ liệu mô phỏng</span><span v-else class="rounded-full bg-primary-container px-3 py-1 text-sm font-semibold text-on-primary-container"><i class="pi pi-verified mr-1" aria-hidden="true"></i>Đã xác minh</span></div><h1 class="mt-3 text-3xl font-bold text-on-surface md:text-4xl">{{ organization.display_name }}</h1><p v-if="organization.description" class="mt-3 max-w-3xl leading-relaxed text-on-surface-variant">{{ organization.description }}</p></div>
        </div>
      </header>
      <aside v-if="organization.data_mode === 'demo'" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950" role="note">
        <strong>Dữ liệu mô phỏng phục vụ trình diễn hệ thống.</strong> Hồ sơ này đã được chấp nhận để mô phỏng chuỗi cung ứng trên KomiBook. Thông tin quan hệ thương mại trên trang này không phải tuyên bố hợp tác có giá trị pháp lý.
        <a v-if="organization.public_source_url" :href="organization.public_source_url" target="_blank" rel="noopener noreferrer" class="ml-1 font-semibold underline">Xem nguồn thông tin công khai</a>
        <span v-if="organization.public_source_checked_at"> (kiểm tra ngày {{ organization.public_source_checked_at }})</span>
      </aside>
      <section class="mt-8">
        <h2 class="text-2xl font-bold text-on-surface">Gian hàng đối tác</h2>
        <div v-if="!organization.partner_shops?.length" class="mt-4 rounded-xl border border-outline-variant p-6 text-on-surface-variant">Chưa có gian hàng đối tác đang hoạt động.</div>
        <div v-else class="mt-4 grid gap-4 md:grid-cols-2">
          <router-link v-for="shop in organization.partner_shops" :key="`${shop.shop_slug}-${shop.role}`" to="/catalog" class="flex min-h-24 items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5 transition-shadow duration-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
            <img v-if="shop.shop_logo" :src="shop.shop_logo" :alt="`Logo ${shop.shop_name}`" width="56" height="56" class="h-14 w-14 object-contain" />
            <div><h3 class="font-bold text-on-surface">{{ shop.shop_name }}</h3><p class="mt-1 text-sm text-on-surface-variant">{{ relationshipLabels[shop.role] || shop.role }}</p><p v-if="shop.is_demo" class="mt-1 text-xs font-semibold text-amber-900">Liên kết mô phỏng đã chấp nhận</p></div>
          </router-link>
        </div>
      </section>
    </template>
  </main>
</template>
