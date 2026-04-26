<template>
  <Card class="h-full flex flex-col justify-between hover:shadow-lg transition-shadow duration-300">
    <template #header>
      <div class="relative w-full pt-[140%] overflow-hidden bg-surface-100 dark:bg-surface-800 cursor-pointer" @click="goToDetail">
        <img
          v-if="book.cover_image"
          :src="book.cover_image"
          :alt="book.title"
          class="absolute top-0 left-0 w-full h-full object-cover transition-transform duration-500 hover:scale-105"
        />
        <div v-else class="absolute inset-0 flex items-center justify-center text-surface-400">
          <i class="pi pi-image text-4xl"></i>
        </div>
      </div>
    </template>
    
    <template #title>
      <div class="text-lg font-bold line-clamp-2 leading-tight cursor-pointer hover:text-primary" @click="goToDetail">
        {{ book.title }}
      </div>
    </template>
    
    <template #subtitle>
      <div class="text-sm text-surface-500 dark:text-surface-400 mt-1 line-clamp-1">
        Bởi {{ book.author || 'Đang cập nhật' }}
      </div>
    </template>
    
    <template #content>
      <div class="mt-2 flex items-center justify-between">
        <div class="text-primary font-bold text-xl">
          {{ formatCurrency(book.sale_price || book.price) }}
        </div>
        <div v-if="book.sale_price && book.price > book.sale_price" class="text-sm text-surface-400 line-through">
          {{ formatCurrency(book.price) }}
        </div>
      </div>
      <div class="mt-3 flex items-center gap-2">
        <i class="pi pi-shop text-surface-500"></i>
        <span class="text-xs font-semibold text-surface-600 dark:text-surface-300 line-clamp-1">
          {{ book.vendor?.name || 'KomiBook' }}
        </span>
      </div>
    </template>
  </Card>
</template>

<script setup>
import { useRouter } from 'vue-router'
import Card from 'primevue/card'

const props = defineProps({
  book: {
    type: Object,
    required: true
  }
})

const router = useRouter()

const goToDetail = () => {
  router.push({ name: 'book-detail', params: { slug: props.book.slug } })
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}
</script>
