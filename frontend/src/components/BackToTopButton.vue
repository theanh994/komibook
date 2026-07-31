<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const visible = ref(false)
const sync = () => { visible.value = window.scrollY > 500 }
const goTop = () => window.scrollTo({ top: 0, behavior: 'smooth' })
onMounted(() => window.addEventListener('scroll', sync, { passive: true }))
onBeforeUnmount(() => window.removeEventListener('scroll', sync))
</script>

<template>
  <button v-show="visible" class="fixed bottom-6 right-6 z-40 grid h-12 w-12 place-items-center rounded-lg bg-primary text-on-primary shadow-lg" type="button" aria-label="Lên đầu trang" @click="goTop">
    <span class="material-symbols-outlined" aria-hidden="true">arrow_upward</span>
  </button>
</template>
