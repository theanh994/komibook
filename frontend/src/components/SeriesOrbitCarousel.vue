<template>
  <section
    v-if="books.length"
    class="series-orbit overflow-hidden rounded-[32px] border border-outline-variant/15 bg-surface-container-lowest px-4 py-6 shadow-sm sm:px-6 lg:px-8"
    aria-labelledby="series-orbit-title"
  >
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-2xl text-primary" aria-hidden="true">auto_stories</span>
          <h2 id="series-orbit-title" class="text-lg font-bold tracking-tight text-on-surface">
            Trọn bộ {{ seriesTitle || 'sách' }}
          </h2>
        </div>
        <p class="mt-1 text-sm text-on-surface-variant">
          Có {{ books.length }} cuốn · Chọn thẻ hoặc dùng phím mũi tên để lướt vòng quanh bộ sách.
        </p>
      </div>

      <div v-if="books.length > 1" class="flex items-center gap-2 self-end sm:self-auto">
        <span class="mr-1 min-w-16 text-center text-xs font-bold tabular-nums text-on-surface-variant">
          {{ activeIndex + 1 }} / {{ books.length }}
        </span>
        <button type="button" class="orbit-control" aria-label="Xem cuốn trước trong bộ sách" @click="previous">
          <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
        </button>
        <button type="button" class="orbit-control" aria-label="Xem cuốn tiếp theo trong bộ sách" @click="next">
          <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        </button>
      </div>
    </header>

    <div
      class="orbit-stage mt-5 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim"
      role="region"
      aria-roledescription="carousel"
      aria-label="Các cuốn thuộc cùng bộ sách"
      tabindex="0"
      @keydown.left.prevent="previous"
      @keydown.right.prevent="next"
      @pointerdown="startSwipe"
      @pointerup="finishSwipe"
      @pointercancel="cancelSwipe"
    >
      <RouterLink
        v-for="(seriesBook, index) in books"
        :key="seriesBook.id"
        :to="{ name: 'book-detail', params: { slug: seriesBook.slug } }"
        class="series-orbit-card group no-underline"
        :class="{ 'is-active': index === activeIndex, 'is-current': seriesBook.id === currentBookId }"
        :style="cardStyle(index)"
        :data-distance="Math.abs(circularOffset(index))"
        :tabindex="index === activeIndex ? 0 : -1"
        :aria-label="cardLabel(seriesBook, index)"
        @click="selectBeforeNavigate($event, index)"
        @focus="activeIndex = index"
      >
        <div class="relative aspect-[2/3] overflow-hidden bg-white">
          <img
            v-if="seriesBook.cover_image && !brokenCoverIds.includes(seriesBook.id)"
            :src="coverUrl(seriesBook.cover_image)"
            :alt="`Bìa ${displayTitle(seriesBook)}`"
            class="h-full w-full object-contain"
            loading="lazy"
            @error="markCoverBroken(seriesBook.id)"
          />
          <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-surface-container-low p-3 text-center text-outline">
            <span class="material-symbols-outlined text-4xl" aria-hidden="true">image_not_supported</span>
            <span class="text-xs font-semibold">Ảnh đang cập nhật</span>
          </div>
          <span v-if="seriesBook.id === currentBookId" class="current-badge">Đang xem</span>
          <span v-else-if="!isPurchasable(seriesBook)" class="stock-badge">Hết hàng</span>
        </div>
        <div class="flex min-h-[92px] flex-col bg-surface-container-lowest p-3">
          <p class="line-clamp-2 text-xs font-bold leading-snug text-on-surface group-hover:text-primary">
            {{ displayTitle(seriesBook) }}
          </p>
          <p class="mt-auto pt-2 text-sm font-extrabold text-secondary">
            {{ formatCurrency(seriesBook.sale_price || seriesBook.price) }}
          </p>
        </div>
      </RouterLink>
    </div>

    <div class="mx-auto -mt-1 max-w-xl text-center" aria-live="polite" aria-atomic="true">
      <p class="text-sm font-bold text-on-surface">{{ displayTitle(activeBook) }}</p>
      <p class="mt-1 text-xs text-on-surface-variant">
        {{ activeBook?.id === currentBookId ? 'Bạn đang xem cuốn này' : 'Nhấn vào thẻ trung tâm để xem chi tiết' }}
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  books: {
    type: Array,
    default: () => [],
  },
  currentBookId: {
    type: Number,
    default: null,
  },
  seriesTitle: {
    type: String,
    default: '',
  },
})

const activeIndex = ref(0)
const swipeStartX = ref(null)
const swipeConsumed = ref(false)
const brokenCoverIds = ref([])

const activeBook = computed(() => props.books[activeIndex.value] || null)

watch(
  () => [props.currentBookId, props.books.map((book) => book.id).join(',')],
  () => {
    const currentIndex = props.books.findIndex((book) => book.id === props.currentBookId)
    activeIndex.value = currentIndex >= 0 ? currentIndex : 0
    brokenCoverIds.value = []
  },
  { immediate: true },
)

const circularOffset = (index) => {
  const count = props.books.length
  if (!count) return 0
  let offset = (index - activeIndex.value + count) % count
  if (offset > count / 2) offset -= count
  return offset
}

const cardStyle = (index) => {
  const offset = circularOffset(index)
  const distance = Math.abs(offset)
  const scale = Math.max(0.64, 1 - distance * 0.11)
  const opacity = distance > 3 ? 0 : Math.max(0.34, 1 - distance * 0.2)

  return {
    '--orbit-offset': offset,
    '--orbit-distance': distance,
    '--orbit-scale': scale,
    '--orbit-opacity': opacity,
    '--orbit-z': 20 - distance,
  }
}

const previous = () => {
  if (props.books.length > 1) {
    activeIndex.value = (activeIndex.value - 1 + props.books.length) % props.books.length
  }
}

const next = () => {
  if (props.books.length > 1) {
    activeIndex.value = (activeIndex.value + 1) % props.books.length
  }
}

const selectBeforeNavigate = (event, index) => {
  if (swipeConsumed.value) {
    event.preventDefault()
    swipeConsumed.value = false
    return
  }

  if (index !== activeIndex.value) {
    event.preventDefault()
    activeIndex.value = index
  }
}

const startSwipe = (event) => {
  swipeStartX.value = event.clientX
  swipeConsumed.value = false
}

const finishSwipe = (event) => {
  if (swipeStartX.value === null) return
  const distance = event.clientX - swipeStartX.value
  swipeStartX.value = null
  if (Math.abs(distance) < 42) return
  swipeConsumed.value = true
  if (distance > 0) previous()
  else next()
}

const cancelSwipe = () => {
  swipeStartX.value = null
  swipeConsumed.value = false
}

const coverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

const markCoverBroken = (bookId) => {
  if (!brokenCoverIds.value.includes(bookId)) brokenCoverIds.value.push(bookId)
}

const displayTitle = (seriesBook) => seriesBook?.display_title || seriesBook?.title || ''

const isPurchasable = (seriesBook) => seriesBook?.is_purchasable ?? Number(seriesBook?.stock) > 0

const cardLabel = (seriesBook, index) => {
  const action = index === activeIndex.value ? 'Mở chi tiết' : 'Đưa vào vị trí trung tâm'
  return `${action}: ${displayTitle(seriesBook)}`
}

const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: 'VND',
}).format(Number(value) || 0)
</script>

<style scoped>
.orbit-stage {
  position: relative;
  height: clamp(340px, 42vw, 430px);
  perspective: 1200px;
  touch-action: pan-y;
  border-radius: 24px;
  background:
    radial-gradient(circle at 50% 58%, color-mix(in srgb, var(--color-primary, #17324d) 12%, transparent), transparent 42%),
    linear-gradient(180deg, color-mix(in srgb, var(--color-surface-container, #f2f4f7) 72%, transparent), transparent);
}

.series-orbit-card {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: var(--orbit-z);
  width: clamp(142px, 17vw, 188px);
  overflow: hidden;
  border: 1px solid color-mix(in srgb, var(--color-outline-variant, #c5c7ca) 45%, transparent);
  border-radius: 18px;
  opacity: var(--orbit-opacity);
  box-shadow: 0 12px 32px rgba(15, 39, 64, 0.12);
  transform:
    translate(-50%, -50%)
    translateX(calc(var(--orbit-offset) * clamp(108px, 13vw, 172px)))
    translateY(calc(var(--orbit-distance) * 13px))
    rotateY(calc(var(--orbit-offset) * -5deg))
    scale(var(--orbit-scale));
  transform-origin: center bottom;
  transition: transform 320ms cubic-bezier(0.22, 1, 0.36, 1), opacity 240ms ease-out, box-shadow 240ms ease-out, border-color 240ms ease-out;
}

.series-orbit-card.is-active {
  border-color: color-mix(in srgb, var(--color-primary, #17324d) 55%, transparent);
  box-shadow: 0 22px 48px rgba(15, 39, 64, 0.22);
}

.series-orbit-card:hover,
.series-orbit-card:focus-visible {
  border-color: var(--color-primary, #17324d);
  outline: none;
  box-shadow: 0 22px 48px rgba(15, 39, 64, 0.24);
}

.orbit-control {
  display: inline-flex;
  width: 48px;
  height: 48px;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, var(--color-outline-variant, #c5c7ca) 55%, transparent);
  border-radius: 9999px;
  color: var(--color-on-surface, #111827);
  background: var(--color-surface-container-lowest, #ffffff);
  cursor: pointer;
  box-shadow: 0 6px 16px rgba(15, 39, 64, 0.1);
  transition: color 180ms ease, background-color 180ms ease, border-color 180ms ease, transform 180ms ease;
}

.orbit-control:hover,
.orbit-control:focus-visible {
  color: var(--color-on-primary, #ffffff);
  background: var(--color-primary, #17324d);
  border-color: var(--color-primary, #17324d);
  outline: 3px solid color-mix(in srgb, var(--color-primary, #17324d) 25%, transparent);
  outline-offset: 2px;
  transform: scale(1.04);
}

.current-badge,
.stock-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  border-radius: 9999px;
  padding: 5px 9px;
  color: #ffffff;
  font-size: 10px;
  font-weight: 800;
  box-shadow: 0 4px 12px rgba(15, 39, 64, 0.18);
}

.current-badge {
  background: var(--color-primary, #17324d);
}

.stock-badge {
  background: #475569;
}

@media (max-width: 639px) {
  .orbit-stage {
    height: 355px;
  }

  .series-orbit-card {
    width: 148px;
  }

  .series-orbit-card[data-distance='2'],
  .series-orbit-card[data-distance='3'] {
    visibility: hidden;
    pointer-events: none;
    opacity: 0 !important;
  }
}

@media (min-width: 640px) and (max-width: 1023px) {
  .series-orbit-card[data-distance='3'] {
    visibility: hidden;
    pointer-events: none;
    opacity: 0 !important;
  }
}

@media (prefers-reduced-motion: reduce) {
  .series-orbit-card,
  .orbit-control {
    transition: none;
  }
}
</style>
