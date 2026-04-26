<template>
  <nav class="bg-white dark:bg-surface-800 shadow-sm border-b border-surface-200 dark:border-surface-700 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-16">
        <!-- Logo / Brand -->
        <div class="flex items-center cursor-pointer" @click="$router.push('/')">
          <i class="pi pi-book text-primary text-2xl mr-2"></i>
          <span class="font-bold text-xl tracking-tight text-surface-900 dark:text-surface-50">KomiBook</span>
        </div>

        <!-- Desktop Menu -->
        <div class="flex items-center gap-4">
          <Button label="Trang chủ" icon="pi pi-home" text class="p-button-sm" @click="$router.push('/')" />
          
          <template v-if="authStore.isAuthenticated">
            <Button label="Dashboard" icon="pi pi-th-large" text class="p-button-sm" @click="$router.push('/dashboard')" />
            <div class="h-6 w-px bg-surface-300 dark:bg-surface-600 mx-2"></div>
            <Button
              :label="authStore.user ? authStore.user.name : 'Đang tải...'"
              icon="pi pi-user"
              severity="secondary"
              outlined
              class="p-button-sm"
              @click="$router.push('/dashboard')"
            />
          </template>
          
          <template v-else>
            <Button label="Đăng nhập" icon="pi pi-sign-in" outlined class="p-button-sm" @click="$router.push('/login')" />
          </template>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'

const authStore = useAuthStore()
</script>
