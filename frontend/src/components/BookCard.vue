<template>
  <div
    class="group bg-surface-container-lowest rounded-lg soft-shadow overflow-hidden cursor-pointer border border-outline-variant/30 flex flex-col h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-card-hover"
    @click="goToDetail"
  >
    <!-- Cover Image -->
    <div class="relative pt-[140%] bg-surface-variant/30">
      <img
        v-if="book.cover_image"
        :src="book.cover_image"
        :alt="book.title"
        class="absolute inset-0 w-full h-full object-cover p-2 rounded-t-lg transition-transform duration-500 group-hover:scale-105"
        loading="lazy"
      />
      <div v-else class="absolute inset-0 flex items-center justify-center">
        <span class="material-symbols-outlined text-outline text-4xl">image</span>
      </div>
      <!-- Sale Badge -->
      <span
        v-if="book.sale_price && book.price > book.sale_price"
        class="absolute top-2 left-2 bg-secondary text-on-secondary text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm"
      >-{{ Math.round((1 - book.sale_price / book.price) * 100) }}%</span>

      <!-- Out of Stock Badge -->
      <span
        v-if="book.type !== 'ebook' && (Number(book.stock) <= 0 || (book.status && book.status !== 'published'))"
        class="absolute top-2 right-2 bg-slate-800/90 text-white text-[10px] font-bold px-2 py-0.5 rounded-md shadow-sm z-10"
      >Hết hàng</span>
    </div>

    <!-- Info -->
    <div class="p-md flex flex-col flex-grow">
      <span class="text-[10px] font-bold text-outline uppercase tracking-wider mb-1">{{ book.category?.name || 'Sách' }}</span>
      <h3 class="text-sm font-medium text-on-surface line-clamp-2 mb-1 leading-snug group-hover:text-primary transition-colors">
        {{ book.title }}
      </h3>
      <p class="text-[13px] text-on-surface-variant mb-md flex-grow">
        {{ book.author || 'Đang cập nhật' }}
      </p>
      <div class="flex flex-col gap-2 mt-auto">
        <template v-if="book.type !== 'ebook' && (Number(book.stock) <= 0 || (book.status && book.status !== 'published'))">
          <div class="w-full py-2 px-md bg-surface-container-high text-outline rounded-lg text-xs font-bold text-center border border-outline-variant/30 flex items-center justify-center gap-1 cursor-not-allowed">
            <span class="material-symbols-outlined text-[16px]">remove_shopping_cart</span>
            Sách đã hết hàng
          </div>
        </template>
        <template v-else>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-primary">
                {{ formatCurrency(book.sale_price || book.price) }}
              </span>
              <span v-if="book.sale_price && book.price > book.sale_price" class="text-xs text-outline line-through">
                {{ formatCurrency(book.price) }}
              </span>
            </div>
            <button
              class="text-primary hover:text-secondary transition-colors p-1 bg-surface-container rounded-full hover:bg-surface-variant cursor-pointer border-none"
              @click.stop="$emit('add-to-cart', book)"
              title="Thêm vào giỏ"
            >
              <span class="material-symbols-outlined text-[20px]">add_shopping_cart</span>
            </button>
          </div>
          <button
            class="w-full py-2 px-md bg-primary text-on-primary rounded-lg text-xs font-bold hover:bg-primary/90 transition-all shadow-sm active:scale-95 cursor-pointer border-none"
            @click.stop="buyNow"
          >
            Mua ngay
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  book: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['add-to-cart', 'buy-now'])

const router = useRouter()

const goToDetail = () => {
  router.push({ name: 'book-detail', params: { slug: props.book.slug } })
}

const buyNow = () => {
  emit('buy-now', props.book)
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}
</script>
