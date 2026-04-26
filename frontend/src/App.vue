<script setup>
import { RouterView } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
import Toast from 'primevue/toast'
</script>

<template>
  <Toast />
  <div class="min-h-screen bg-surface-50 dark:bg-surface-900 flex flex-col">
    <!-- Thanh Header Toàn cục -->
    <AppHeader />
    
    <!-- Render các trang bên dưới Navbar -->
    <main class="flex-grow">
      <RouterView />
    </main>
  </div>
</template>

<style scoped></style>
