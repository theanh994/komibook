<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import ChatWidget from '@/components/chat/ChatWidget.vue'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'

const route = useRoute()
const showBackToTop = ref(false)

const updateBackToTop = () => {
  showBackToTop.value = window.scrollY > 480
}

const scrollToTop = () => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' })
}

onMounted(() => {
  updateBackToTop()
  window.addEventListener('scroll', updateBackToTop, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', updateBackToTop)
})

watch(
  () => route.fullPath,
  async () => {
    await nextTick()
    window.requestAnimationFrame(() => {
      document.querySelector('[data-route-focus]')?.focus({ preventScroll: true })
    })
  },
)

</script>

<template>
  <Toast />
  <ConfirmDialog />
  <div class="min-h-screen bg-background text-on-background flex flex-col font-inter">
    <a v-if="!$route.meta.hideHeader" class="skip-link" href="#main-content">
      Bỏ qua điều hướng
    </a>

    <!-- Thanh Header Toàn cục -->
    <AppHeader v-if="!$route.meta.hideHeader" />
    
    <!-- Render các trang bên dưới Navbar -->
    <main
      v-if="!$route.meta.hideHeader"
      id="main-content"
      class="flex-grow"
      data-route-focus
      tabindex="-1"
    >
      <RouterView />
    </main>
    <div v-else class="flex-grow">
      <RouterView />
    </div>

    <!-- Chatbot AI Floating Widget -->
    <ChatWidget v-if="!$route.meta.hideHeader" />

    <!-- Footer Toàn cục (ẩn ở Admin/Vendor Dashboard & Reader) -->
    <AppFooter v-if="!$route.meta.hideHeader" />

    <Transition name="back-to-top">
      <button
        v-if="showBackToTop && !$route.meta.hideHeader"
        type="button"
        class="fixed bottom-6 right-4 z-50 flex h-11 w-11 items-center justify-center rounded-lg bg-primary text-on-primary shadow-elevated transition-colors hover:bg-primary-container hover:text-on-primary-container focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim sm:bottom-8 sm:right-8"
        aria-label="Lên đầu trang"
        @click="scrollToTop"
      >
        <span class="material-symbols-outlined" aria-hidden="true">arrow_upward</span>
      </button>
    </Transition>
  </div>
</template>

<style scoped>
.back-to-top-enter-active,
.back-to-top-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}

.back-to-top-enter-from,
.back-to-top-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

@media (prefers-reduced-motion: reduce) {
  .back-to-top-enter-active,
  .back-to-top-leave-active {
    transition: none;
  }
}
</style>
