<template>
  <article class="book-card group flex h-full flex-col overflow-hidden rounded-b-lg rounded-2xl border-0 bg-transparent transition-all duration-300 hover:-translate-y-1">
    <div class="relative aspect-[2/3] w-full overflow-hidden rounded-xl bg-transparent flex items-center justify-center">
      <router-link
        :to="{ name: 'book-detail', params: { slug: book.slug } }"
        class="absolute inset-0 block focus-visible:z-10 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim"
        :aria-label="`Xem chi tiết ${displayTitle}`"
      >
        <img
          v-if="coverUrl && !coverFailed"
          :src="coverUrl"
          :alt="`Bìa sách ${displayTitle}`"
          class="book-cover h-full w-full rounded-lg object-contain"
          loading="lazy"
          @error="markCoverFailed"
        />
        <span
          v-else
          class="flex h-full w-full flex-col items-center justify-center gap-2 bg-surface-container-low px-3 text-center text-outline"
          role="img"
          :aria-label="`Chưa có ảnh bìa cho ${displayTitle}`"
        >
          <span class="material-symbols-outlined text-4xl" aria-hidden="true">menu_book</span>
          <span class="text-xs font-medium">Chưa có ảnh bìa</span>
        </span>
      </router-link>

      <span
        v-if="book.sale_price && book.price > book.sale_price"
        class="absolute left-2 top-2 z-10 rounded-md bg-secondary px-2 py-1 text-xs font-bold text-on-secondary shadow-sm"
      >
        -{{ Math.round((1 - book.sale_price / book.price) * 100) }}%
      </span>
      <span
        v-if="book.type === 'ebook'"
        class="absolute bottom-2 left-2 z-10 rounded-md bg-primary/95 px-2 py-1 text-xs font-bold text-on-primary shadow-sm"
      >
        Ebook
      </span>
      <span
        v-else-if="book.provenance === 'used_resale'"
        class="absolute bottom-2 left-2 z-10 rounded-md bg-secondary px-2 py-1 text-xs font-bold text-on-secondary shadow-sm"
      >
        Sách cũ
      </span>
      <span
        v-if="!isPurchasable"
        class="absolute right-2 top-2 z-10 rounded-md bg-slate-800/90 px-2 py-1 text-xs font-bold text-white shadow-sm"
      >
        Hết hàng
      </span>

      <button
        v-if="showWishlist"
        type="button"
        class="absolute right-2 top-2 z-20 flex h-11 w-11 items-center justify-center rounded-full border border-white/70 bg-slate-950/45 text-white shadow-sm transition-colors hover:bg-slate-950/65 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim"
        :aria-label="isFavorite ? `Bỏ ${displayTitle} khỏi yêu thích` : `Thêm ${displayTitle} vào yêu thích`"
        :aria-pressed="isFavorite"
        @click="$emit('toggle-wishlist', book.id)"
      >
        <span
          class="material-symbols-outlined text-[26px] transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.85)]"
          :class="isFavorite ? 'text-error fill-1' : 'text-white hover:text-error'"
          aria-hidden="true">favorite</span>
      </button>

      <div v-if="isPurchasable" class="card-actions absolute bottom-3 right-2 z-20 flex flex-col gap-2">
        <button
          type="button"
          class="card-action"
          :aria-label="`Xem nhanh ${displayTitle}`"
          @click="$emit('quick-view', book)"
        >
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
        </button>
        <button
          type="button"
          class="card-action"
          :aria-label="`Thêm ${displayTitle} vào giỏ`"
          @click="$emit('add-to-cart', book)"
        >
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">shopping_bag</span>
        </button>
        <button
          type="button"
          class="card-action"
          :aria-label="`Mua ngay ${displayTitle}`"
          @click="$emit('buy-now', book)"
        >
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">shopping_cart</span>
        </button>
      </div>
    </div>

    <div class="flex flex-grow flex-col px-2 pb-2 pt-2.5 text-center">
      <h3 class="mb-1.5 flex min-h-[38px] items-center justify-center text-center text-sm font-medium leading-tight text-on-surface break-words line-clamp-2">
        <router-link
          :to="{ name: 'book-detail', params: { slug: book.slug } }"
          class="text-inherit no-underline transition-colors hover:text-primary focus-visible:rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-fixed-dim"
        >
          {{ displayTitle }}
        </router-link>
      </h3>
      <p v-if="book.type === 'ebook'" class="mb-1 text-center text-xs font-bold text-primary">
        {{ book.latest_ebook_version?.version
          ? `Phiên bản ${book.latest_ebook_version.version}`
          : 'Phiên bản đang cập nhật' }}
      </p>

      <div v-if="isPurchasable" class="mt-auto flex flex-wrap items-baseline justify-center gap-2">
        <span class="text-[15px] font-bold text-green-600 dark:text-green-400">
          {{ formatCurrency(book.sale_price || book.price) }}
        </span>
        <span v-if="book.sale_price && book.price > book.sale_price" class="text-xs text-outline line-through">
          {{ formatCurrency(book.price) }}
        </span>
      </div>
      <div v-else class="mt-auto flex min-h-11 items-center justify-center gap-1 rounded-lg border border-outline-variant/30 bg-surface-container-high px-3 text-center text-xs font-bold text-outline">
        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">remove_shopping_cart</span>
        Sách đã hết hàng
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  book: {
    type: Object,
    required: true,
  },
  showWishlist: {
    type: Boolean,
    default: false,
  },
  isFavorite: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['quick-view', 'add-to-cart', 'buy-now', 'toggle-wishlist'])

const isPurchasable = computed(() => (
  props.book.is_purchasable ?? (
    props.book.type === 'ebook'
    || (Number(props.book.stock) > 0 && (!props.book.status || props.book.status === 'published'))
  )
))
const displayTitle = computed(() => props.book.display_title || props.book.title)

const coverUrl = computed(() => {
  const path = props.book.cover_image
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
})

const coverFailed = ref(false)

watch(coverUrl, () => {
  coverFailed.value = false
})

const markCoverFailed = () => {
  coverFailed.value = true
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}
</script>

<style scoped>
.card-action {
  display: flex;
  width: 44px;
  height: 44px;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 9999px;
  color: var(--color-on-commerce, #ffffff);
  background: var(--color-commerce, #15803d);
  box-shadow: 0 4px 12px rgba(15, 39, 64, 0.2);
  transition: filter 180ms ease, transform 180ms ease;
}

.card-action:hover,
.card-action:focus-visible {
  filter: brightness(1.08);
  transform: scale(1.05);
  outline: 3px solid color-mix(in srgb, var(--color-commerce, #15803d) 35%, transparent);
  outline-offset: 2px;
}

.card-actions {
  opacity: 1;
  pointer-events: auto;
  transform: translateX(0);
  transition: opacity 200ms ease, transform 200ms ease;
}

.book-card {
  transform: translateY(0);
  transition: transform 220ms ease, box-shadow 220ms ease, border-color 220ms ease;
}

.book-cover {
  transform: scale(1);
  transition: transform 240ms ease;
}

@media (min-width: 640px) and (hover: hover) and (pointer: fine) {
  .card-actions {
    opacity: 0;
    pointer-events: none;
    transform: translateX(8px);
  }

  .book-card:hover .card-actions,
  .book-card:focus-within .card-actions {
    opacity: 1;
    pointer-events: auto;
    transform: translateX(0);
  }

  .book-card:hover,
  .book-card:focus-within {
    border-color: color-mix(in srgb, var(--color-primary, #17324d) 34%, transparent);
    box-shadow: 0 14px 30px rgba(15, 39, 64, 0.14);
    transform: translateY(-4px);
  }

  .book-card:hover .book-cover,
  .book-card:focus-within .book-cover {
    transform: scale(1.018);
  }
}

@media (prefers-reduced-motion: reduce) {
  .book-card,
  .book-cover,
  .card-actions,
  .card-action {
    transition: none;
    transform: none;
  }
}
</style>
