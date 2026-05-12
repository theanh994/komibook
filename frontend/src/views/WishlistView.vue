<template>
  <div class="min-h-screen bg-background py-xl px-gutter">
    <div class="max-w-[1200px] mx-auto flex flex-col lg:flex-row gap-xl">
      
      <!-- Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content -->
      <main class="flex-1 space-y-lg">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden min-h-[600px]">
          <div class="p-lg md:p-xl border-b border-outline-variant/10">
            <h1 class="text-2xl font-black text-on-surface tracking-tight mb-1">Danh sách yêu thích</h1>
            <p class="text-sm text-on-surface-variant font-medium">Lưu giữ những cuốn sách bạn đang quan tâm.</p>
          </div>

          <div class="p-lg md:p-xl">
            <!-- Empty State (No Backend Yet) -->
            <div class="py-20 text-center animate-fade-in">
              <div class="w-24 h-24 bg-error-container/20 rounded-full flex items-center justify-center mx-auto mb-lg text-error">
                <span class="material-symbols-outlined text-5xl">favorite</span>
              </div>
              <h3 class="text-xl font-bold text-on-surface mb-2">Chưa có cuốn sách nào</h3>
              <p class="text-on-surface-variant mb-xl max-w-sm mx-auto text-sm leading-relaxed">
                Hãy thả tim cho những cuốn sách bạn yêu thích để chúng xuất hiện ở đây nhé!
              </p>
              <button @click="$router.push('/catalog')" class="bg-primary text-on-primary px-xl py-md rounded-2xl font-bold shadow-md hover:bg-primary/90 transition-all active:scale-95">
                Khám phá ngay
              </button>
            </div>

            <!-- Placeholder Section: Gợi ý cho bạn -->
            <div class="mt-xl pt-xl border-t border-outline-variant/10">
               <h3 class="text-lg font-black text-on-surface mb-lg">Gợi ý cho bạn</h3>
               <div class="grid grid-cols-2 sm:grid-cols-4 gap-lg">
                  <div v-for="i in 4" :key="i" class="space-y-md animate-pulse">
                     <div class="aspect-[3/4] bg-surface-container-high rounded-2xl"></div>
                     <div class="h-4 bg-surface-container-high rounded-full w-3/4"></div>
                     <div class="h-3 bg-surface-container-high rounded-full w-1/2"></div>
                  </div>
               </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import UserSidebar from '@/components/profile/UserSidebar.vue'

const authStore = useAuthStore()
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.4s ease-out forwards;
}
</style>
