<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="book?.display_title || book?.title"
    class="w-[95vw] max-w-3xl"
  >
    <div v-if="book" class="grid gap-6 md:grid-cols-[minmax(0,220px)_1fr]">
      <div class="aspect-[2/3] overflow-hidden rounded-xl bg-surface-container-low">
        <img v-if="coverUrl" :src="coverUrl" :alt="book.display_title || book.title" class="h-full w-full object-contain" />
        <div v-else class="flex h-full items-center justify-center text-outline">
          <span class="material-symbols-outlined text-5xl" aria-hidden="true">menu_book</span>
        </div>
      </div>

      <div class="flex min-w-0 flex-col gap-4">
        <div>
          <p v-if="supplierName" class="text-sm text-outline">{{ supplierName }}</p>
          <p v-if="book.commercial_parties?.supplier?.is_demo === true" class="mt-1 text-xs text-outline">Thông tin nhà cung cấp đang ở chế độ minh họa.</p>
          <p class="mt-2 text-2xl font-bold text-primary">{{ formatCurrency(book.sale_price || book.price) }}</p>
          <p v-if="book.sale_price && book.price > book.sale_price" class="text-sm text-outline line-through">{{ formatCurrency(book.price) }}</p>
        </div>

        <p v-if="description" class="text-sm leading-relaxed text-on-surface-variant">{{ description }}</p>

        <p v-if="!isPurchasable" class="rounded-lg bg-surface-container-high px-3 py-3 text-sm font-semibold text-outline">
          Sách hiện chưa thể mua.
        </p>

        <div v-else class="flex flex-wrap items-center gap-3">
          <div v-if="book.type !== 'ebook'" class="flex min-h-11 items-center rounded-lg border border-outline-variant">
            <button type="button" class="h-11 w-11 text-lg" aria-label="Giảm số lượng" @click="setQuantity(quantity - 1)">−</button>
            <input v-model.number="quantity" class="h-11 w-12 bg-transparent text-center" type="number" min="1" :max="maxQuantity" aria-label="Số lượng" @change="setQuantity(quantity)" />
            <button type="button" class="h-11 w-11 text-lg" aria-label="Tăng số lượng" @click="setQuantity(quantity + 1)">+</button>
          </div>
          <span v-else class="text-sm text-outline">Ebook: 1</span>
          <button type="button" class="min-h-11 rounded-lg bg-primary px-4 font-semibold text-on-primary" @click="addToCart">Thêm vào giỏ</button>
          <button type="button" class="min-h-11 rounded-lg border border-primary px-4 font-semibold text-primary" @click="buyNow">Mua ngay</button>
        </div>
      </div>
    </div>
  </Dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import Dialog from 'primevue/dialog'

const props = defineProps({
  visible: Boolean,
  book: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'add-to-cart', 'buy-now'])
const quantity = ref(1)

const visible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value),
})
const isPurchasable = computed(() => props.book?.is_purchasable === true)
const maxQuantity = computed(() => props.book?.type === 'ebook' ? 1 : Math.max(Number(props.book?.stock) || 1, 1))
const supplierName = computed(() => props.book?.commercial_parties?.supplier?.display_name || '')
const description = computed(() => props.book?.description?.replace(/<[^>]*>/g, '').trim() || '')
const coverUrl = computed(() => {
  const path = props.book?.cover_image
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) return path
  if (path.includes('/storage/')) return path.slice(path.indexOf('/storage/'))
  return `/storage/${path}`
})

watch(() => props.book, () => { quantity.value = 1 })
watch(() => props.visible, (isVisible) => {
  if (isVisible) quantity.value = 1
})
const setQuantity = (value) => { quantity.value = Math.min(Math.max(Number(value) || 1, 1), maxQuantity.value) }
const addToCart = () => { if (isPurchasable.value) emit('add-to-cart', props.book, quantity.value) }
const buyNow = () => { if (isPurchasable.value) emit('buy-now', props.book, quantity.value) }
const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
</script>
