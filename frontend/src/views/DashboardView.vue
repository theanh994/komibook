<template>
  <div class="min-h-screen bg-surface-50 dark:bg-surface-900 p-8 flex justify-center items-start">
    <Card class="w-full max-w-4xl shadow-md border border-surface-200 dark:border-surface-700">
      <template #title>
        <div class="text-2xl font-bold flex justify-between items-center">
          <span>Bảng điều khiển (Dashboard)</span>
          <Button icon="pi pi-sign-out" label="Đăng xuất" severity="danger" size="small" @click="handleLogout" />
        </div>
      </template>
      <template #content>
        <div v-if="authStore.user" class="mt-4 p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
          <h2 class="text-xl font-semibold mb-2">Xin chào, {{ authStore.user.name }}</h2>
          <ul class="flex flex-col gap-2 mt-4 text-surface-600 dark:text-surface-300">
            <li><strong>Email:</strong> {{ authStore.user.email }}</li>
            <li><strong>Vai trò:</strong> <Badge :value="authStore.user.role?.toUpperCase() || 'UNKNOWN'" severity="info"></Badge></li>
            <li><strong>Ngày tạo:</strong> {{ new Date(authStore.user.created_at).toLocaleDateString('vi-VN') }}</li>
          </ul>
        </div>
        <div v-else class="flex justify-center p-8">
          <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

import Card from 'primevue/card'
import Button from 'primevue/button'
import Badge from 'primevue/badge'

const authStore = useAuthStore()
const router = useRouter()

const handleLogout = async () => {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>
