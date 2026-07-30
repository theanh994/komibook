<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import Button from 'primevue/button'

const route = useRoute()
const organization = ref(null)
const loading = ref(true)
const error = ref('')

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
          <div><div class="flex flex-wrap items-center gap-2"><span v-for="type in organization.organization_types" :key="type" class="rounded-full bg-secondary-container px-3 py-1 text-sm font-semibold text-on-secondary-container">{{ type }}</span><span class="rounded-full bg-primary-container px-3 py-1 text-sm font-semibold text-on-primary-container"><i class="pi pi-verified mr-1" aria-hidden="true"></i>Đã xác minh</span></div><h1 class="mt-3 text-3xl font-bold text-on-surface md:text-4xl">{{ organization.display_name }}</h1><p v-if="organization.description" class="mt-3 max-w-3xl leading-relaxed text-on-surface-variant">{{ organization.description }}</p></div>
        </div>
      </header>
      <section class="mt-8">
        <h2 class="text-2xl font-bold text-on-surface">Gian hàng đối tác</h2>
        <div v-if="!organization.partner_shops?.length" class="mt-4 rounded-xl border border-outline-variant p-6 text-on-surface-variant">Chưa có gian hàng đối tác đang hoạt động.</div>
        <div v-else class="mt-4 grid gap-4 md:grid-cols-2">
          <router-link v-for="shop in organization.partner_shops" :key="`${shop.shop_slug}-${shop.role}`" to="/catalog" class="flex min-h-24 items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5 transition-shadow duration-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
            <img v-if="shop.shop_logo" :src="shop.shop_logo" :alt="`Logo ${shop.shop_name}`" width="56" height="56" class="h-14 w-14 object-contain" />
            <div><h3 class="font-bold text-on-surface">{{ shop.shop_name }}</h3><p class="mt-1 text-sm text-on-surface-variant">{{ shop.role }}</p></div>
          </router-link>
        </div>
      </section>
    </template>
  </main>
</template>
