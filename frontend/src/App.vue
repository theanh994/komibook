<script setup>
import Button from 'primevue/button'
import { ref } from 'vue'

const clickCount = ref(0)
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center gap-6">
    <h1 class="text-3xl font-bold text-gray-800">🎉 KomiBook Frontend</h1>

    <p class="text-gray-500 text-sm">
      Tailwind CSS + PrimeVue đã được cấu hình thành công!
    </p>

    <div class="flex gap-4">
      <Button
        label="Click tôi!"
        icon="pi pi-check"
        @click="clickCount++"
      />
      <Button
        label="Reset"
        icon="pi pi-refresh"
        severity="secondary"
        @click="clickCount = 0"
      />
    </div>

    <p v-if="clickCount > 0" class="text-indigo-600 font-semibold">
      Bạn đã click {{ clickCount }} lần 🚀
    </p>
  </div>
</template>

<style scoped></style>

