<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import apiClient from '@/services/axios'

const listings = ref([])
const categories = ref([])
const drafts = ref([])
const loading = ref(true)
const loadError = ref('')
const notice = ref('')
const bulkBusy = ref(false)
const inventoryBusy = ref(null)
const inventoryValues = reactive({})
const addressBusy = ref(false)
const addressReady = ref(false)
const addressForm = reactive({
  recipient_name: '',
  phone: '',
  address_line: '',
  ward: '',
  district: '',
  province: '',
  postal_code: '',
})

const conditionOptions = [
  { value: 'like_new', label: 'Như mới' },
  { value: 'good', label: 'Tốt' },
  { value: 'fair', label: 'Khá' },
]

const newDraft = () => ({
  clientId: crypto.randomUUID?.() || `draft-${Date.now()}-${Math.random()}`,
  title: '',
  author_name: '',
  description: '',
  category_id: '',
  price: 1000,
  condition: 'good',
  defects: '',
  quantity: 1,
  actual_photos: [],
  photoPreviews: [],
  authenticity_attested: false,
  state: 'draft',
  errors: {},
  savedId: null,
})

const unsavedCount = computed(() => drafts.value.filter(draft => draft.state !== 'saved').length)
const money = value => `${Number(value || 0).toLocaleString('vi-VN')} đ`
const conditionLabel = value => conditionOptions.find(option => option.value === value)?.label || value
const statusLabel = value => ({ draft: 'Bản nháp', active: 'Đang bán', suspended: 'Tạm dừng', sold_out: 'Hết hàng' }[value] || value)

const addDraft = () => {
  drafts.value.push(newDraft())
  notice.value = ''
}

const revokePreviews = draft => draft.photoPreviews.forEach(item => URL.revokeObjectURL(item.url))

const removeDraft = draft => {
  const hasEnteredData = draft.title || draft.author_name || draft.description || draft.defects || draft.actual_photos.length
  if (draft.state !== 'saved' && hasEnteredData && !window.confirm('Xóa dòng nháp này? Dữ liệu chưa lưu sẽ không thể khôi phục.')) return
  revokePreviews(draft)
  drafts.value = drafts.value.filter(item => item.clientId !== draft.clientId)
}

const onPhotos = (draft, event) => {
  revokePreviews(draft)
  draft.actual_photos = Array.from(event.target.files || []).slice(0, 8)
  draft.photoPreviews = draft.actual_photos.map((file, index) => ({
    name: file.name,
    url: URL.createObjectURL(file),
    alt: `Ảnh thật ${index + 1} của ${draft.title || 'sách cũ'}`,
  }))
  delete draft.errors.actual_photos
}

const validateDraft = draft => {
  const errors = {}
  if (!draft.title.trim()) errors.title = 'Nhập tên sách.'
  if (!draft.author_name.trim()) errors.author_name = 'Nhập tên người viết trên bìa.'
  if (!draft.category_id) errors.category_id = 'Chọn danh mục.'
  if (Number(draft.price) < 1000) errors.price = 'Giá tối thiểu là 1.000 đ.'
  if (Number(draft.quantity) < 1 || Number(draft.quantity) > 100) errors.quantity = 'Số lượng từ 1 đến 100.'
  if (!draft.actual_photos.length) errors.actual_photos = 'Thêm ít nhất một ảnh thật của sách.'
  if (!draft.authenticity_attested) errors.authenticity_attested = 'Bạn cần xác nhận cam kết sách thật.'
  draft.errors = errors
  return Object.keys(errors).length === 0
}

const apiErrors = requestError => {
  const validation = requestError.response?.data?.errors || {}
  const normalized = {}
  Object.entries(validation).forEach(([field, messages]) => {
    const key = field.startsWith('actual_photos') ? 'actual_photos' : field
    normalized[key] = Array.isArray(messages) ? messages[0] : messages
  })
  if (!Object.keys(normalized).length) {
    normalized.general = requestError.response?.data?.message || 'Không thể lưu sách này. Kiểm tra thông tin và thử lại.'
  }
  return normalized
}

const submitDraft = async draft => {
  if (!addressReady.value) {
    draft.errors = { general: 'Lưu địa chỉ gửi hàng đã xác minh trước khi đăng sách.' }
    return false
  }
  if (draft.state === 'saved' || !validateDraft(draft)) return false
  draft.state = 'saving'
  draft.errors = {}

  const payload = new FormData()
  const scalarFields = ['title', 'author_name', 'description', 'category_id', 'price', 'condition', 'defects', 'quantity']
  scalarFields.forEach(key => {
    payload.append(key, draft[key] ?? '')
  })
  draft.actual_photos.forEach(file => payload.append('actual_photos[]', file))
  payload.append('authenticity_attested', draft.authenticity_attested ? '1' : '0')

  try {
    const response = await apiClient.post('/api/used-book-seller/listings', payload)
    draft.state = 'saved'
    draft.savedId = response.data.data.id
    listings.value.unshift(response.data.data)
    inventoryValues[response.data.data.id] = response.data.data.quantity_available
    return true
  } catch (requestError) {
    draft.state = 'error'
    draft.errors = apiErrors(requestError)
    return false
  }
}

const submitAll = async () => {
  bulkBusy.value = true
  notice.value = ''
  let saved = 0
  for (const draft of drafts.value) {
    if (await submitDraft(draft)) saved += 1
  }
  const failed = drafts.value.filter(draft => draft.state === 'error').length
  notice.value = saved
    ? `Đã lưu ${saved} sách. ${failed ? `${failed} dòng còn lỗi và vẫn được giữ để bạn sửa.` : 'Tất cả dòng đã hoàn tất.'}`
    : (failed ? 'Chưa có dòng nào được lưu; các dữ liệu đã nhập vẫn được giữ nguyên.' : '')
  bulkBusy.value = false
}

const saveInventory = async listing => {
  inventoryBusy.value = listing.id
  try {
    const response = await apiClient.patch(`/api/used-book-seller/listings/${listing.id}/inventory`, {
      quantity_available: Number(inventoryValues[listing.id]),
    })
    Object.assign(listing, response.data.data)
    notice.value = `Đã cập nhật tồn khả dụng cho “${listing.book?.title || `listing #${listing.id}`}”.`
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể cập nhật tồn khả dụng.'
    inventoryValues[listing.id] = listing.quantity_available
  } finally {
    inventoryBusy.value = null
  }
}

const saveAddress = async () => {
  addressBusy.value = true
  loadError.value = ''
  try {
    await apiClient.put('/api/used-book-seller/fulfillment-address', addressForm)
    addressReady.value = true
    notice.value = 'Đã lưu địa chỉ gửi hàng riêng tư. Khách hàng không thể xem thông tin này.'
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể lưu địa chỉ gửi hàng.'
  } finally {
    addressBusy.value = false
  }
}

const load = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [listingResult, categoryResult, addressResult] = await Promise.allSettled([
      apiClient.get('/api/used-book-seller/listings'),
      apiClient.get('/api/categories'),
      apiClient.get('/api/used-book-seller/fulfillment-address'),
    ])
    if (listingResult.status === 'fulfilled') {
      listings.value = listingResult.value.data?.data || []
    } else {
      loadError.value = listingResult.reason.response?.data?.message || 'Không thể tải danh sách sách cũ của bạn.'
    }
    if (categoryResult.status === 'fulfilled') {
      const categoryPayload = categoryResult.value.data?.data ?? categoryResult.value.data ?? []
      categories.value = Array.isArray(categoryPayload) ? categoryPayload : (categoryPayload.data || [])
    } else {
      loadError.value = loadError.value || 'Không thể tải danh mục sách.'
    }
    if (addressResult.status === 'fulfilled' && addressResult.value.data?.data) {
      Object.assign(addressForm, addressResult.value.data.data)
      addressReady.value = addressResult.value.data.data.status === 'verified'
    }
    listings.value.forEach(listing => { inventoryValues[listing.id] = listing.quantity_available })
    if (!drafts.value.length) addDraft()
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể tải khu vực Người bán sách cũ.'
    if (!drafts.value.length) addDraft()
  } finally {
    loading.value = false
  }
}

onMounted(load)
onBeforeUnmount(() => drafts.value.forEach(revokePreviews))
</script>

<template>
  <section class="mx-auto w-full max-w-[1440px] space-y-6 px-4 py-8 sm:px-6 lg:px-8" aria-labelledby="used-book-manager-heading">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="max-w-3xl">
        <p class="font-label-md font-bold text-secondary">Người bán sách cũ</p>
        <h1 id="used-book-manager-heading" class="mt-1 text-3xl font-bold text-primary">Sách cũ của tôi</h1>
        <p class="mt-2 text-base leading-7 text-on-surface-variant">Chuẩn bị nhiều cuốn trong một hàng đợi, khai báo đúng tình trạng và quản lý tồn của từng sách đã đăng.</p>
      </div>
      <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 font-bold text-on-primary shadow-sm transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim" @click="addDraft">
        <span class="material-symbols-outlined" aria-hidden="true">add</span>
        Thêm một sách
      </button>
    </header>

    <aside class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm leading-6 text-on-surface" aria-label="Quy tắc riêng tư và trách nhiệm">
      <div class="flex items-start gap-3">
        <span class="material-symbols-outlined text-primary" aria-hidden="true">verified_user</span>
        <p><strong>Địa chỉ gửi hàng được giữ riêng tư.</strong> Sách cũ chỉ được gửi từ địa chỉ đã xác minh. Bạn chịu trách nhiệm về tính xác thực; khiếu nại sách giả có thể làm tạm dừng listing và giữ tiền đối soát.</p>
      </div>
    </aside>

    <form class="rounded-xl border border-outline-variant/30 bg-surface p-4 shadow-sm sm:p-6" aria-labelledby="seller-address-heading" @submit.prevent="saveAddress">
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id="seller-address-heading" class="text-xl font-bold text-primary">Địa chỉ gửi hàng đã xác minh</h2>
          <p class="mt-1 text-sm leading-6 text-on-surface-variant">Địa chỉ chỉ dùng nội bộ để xử lý kho và vận chuyển, không hiển thị cho khách hàng.</p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="addressReady ? 'bg-commerce/10 text-commerce' : 'bg-warning/10 text-warning'">
          {{ addressReady ? 'Đã sẵn sàng' : 'Cần bổ sung' }}
        </span>
      </div>
      <fieldset :disabled="addressBusy" class="grid gap-4 md:grid-cols-2">
        <legend class="sr-only">Thông tin địa chỉ gửi hàng riêng tư</legend>
        <label class="space-y-1 text-sm font-bold text-on-surface">
          <span>Người liên hệ</span>
          <input v-model="addressForm.recipient_name" required maxlength="255" autocomplete="name" class="queue-input" />
        </label>
        <label class="space-y-1 text-sm font-bold text-on-surface">
          <span>Số điện thoại</span>
          <input v-model="addressForm.phone" required inputmode="tel" autocomplete="tel" pattern="0[0-9]{9}" class="queue-input" />
        </label>
        <label class="space-y-1 text-sm font-bold text-on-surface md:col-span-2">
          <span>Địa chỉ</span>
          <input v-model="addressForm.address_line" required maxlength="500" autocomplete="street-address" class="queue-input" />
        </label>
        <label class="space-y-1 text-sm font-bold text-on-surface">
          <span>Phường / xã</span>
          <input v-model="addressForm.ward" maxlength="120" class="queue-input" />
        </label>
        <label class="space-y-1 text-sm font-bold text-on-surface">
          <span>Quận / huyện</span>
          <input v-model="addressForm.district" maxlength="120" class="queue-input" />
        </label>
        <label class="space-y-1 text-sm font-bold text-on-surface">
          <span>Tỉnh / thành phố</span>
          <input v-model="addressForm.province" required maxlength="120" autocomplete="address-level1" class="queue-input" />
        </label>
        <label class="space-y-1 text-sm font-bold text-on-surface">
          <span>Mã bưu chính</span>
          <input v-model="addressForm.postal_code" maxlength="20" autocomplete="postal-code" class="queue-input" />
        </label>
      </fieldset>
      <div class="mt-4 flex justify-end">
        <button type="submit" :disabled="addressBusy" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-5 font-bold text-on-primary disabled:opacity-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim">
          {{ addressBusy ? 'Đang lưu...' : 'Lưu địa chỉ riêng tư' }}
        </button>
      </div>
    </form>

    <div v-if="loadError" role="alert" class="flex flex-col gap-3 rounded-xl border border-error/30 bg-error/5 p-4 text-error sm:flex-row sm:items-center sm:justify-between">
      <span>{{ loadError }}</span>
      <button type="button" class="min-h-11 rounded-lg px-4 font-bold hover:bg-error/10 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-error/20" @click="load">Thử lại</button>
    </div>
    <p v-if="notice" role="status" aria-live="polite" class="rounded-xl border border-commerce/30 bg-commerce/5 p-4 text-commerce">{{ notice }}</p>

    <div v-if="loading" role="status" class="grid animate-pulse gap-4 lg:grid-cols-2" aria-label="Đang tải sách cũ">
      <div class="h-72 rounded-xl bg-surface-container-high"></div>
      <div class="h-72 rounded-xl bg-surface-container-high"></div>
    </div>

    <template v-else>
      <section class="space-y-4" aria-labelledby="draft-queue-heading">
        <div class="flex flex-col gap-3 border-b border-outline-variant/30 pb-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 id="draft-queue-heading" class="text-2xl font-bold text-primary">Hàng đợi đăng sách</h2>
            <p class="mt-1 text-sm text-on-surface-variant">Mỗi dòng được gửi độc lập; dòng lỗi không làm mất dữ liệu của các dòng khác.</p>
          </div>
          <button v-if="drafts.length" type="button" :disabled="bulkBusy || !unsavedCount" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-commerce px-5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-commerce/25" @click="submitAll">
            <span class="material-symbols-outlined" aria-hidden="true">cloud_upload</span>
            {{ bulkBusy ? 'Đang lưu hàng đợi...' : `Lưu ${unsavedCount} sách chưa hoàn tất` }}
          </button>
        </div>

        <div v-if="!drafts.length" class="rounded-xl border border-dashed border-outline-variant p-8 text-center">
          <span class="material-symbols-outlined text-4xl text-on-surface-variant" aria-hidden="true">library_add</span>
          <h3 class="mt-2 text-lg font-bold text-on-surface">Hàng đợi đang trống</h3>
          <p class="mt-1 text-sm text-on-surface-variant">Thêm một sách để bắt đầu.</p>
        </div>

        <form v-for="(draft, index) in drafts" :key="draft.clientId" class="rounded-xl border bg-surface p-4 shadow-sm sm:p-6" :class="draft.state === 'error' ? 'border-error/40' : draft.state === 'saved' ? 'border-commerce/40' : 'border-outline-variant/30'" @submit.prevent="submitDraft(draft)">
          <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-container font-bold text-on-primary-container" aria-hidden="true">{{ index + 1 }}</span>
              <div>
                <h3 class="font-bold text-on-surface">{{ draft.title || `Sách chưa đặt tên ${index + 1}` }}</h3>
                <p class="text-sm" :class="draft.state === 'error' ? 'text-error' : draft.state === 'saved' ? 'text-commerce' : 'text-on-surface-variant'">
                  {{ draft.state === 'saving' ? 'Đang lưu...' : draft.state === 'saved' ? `Đã lưu listing #${draft.savedId}` : draft.state === 'error' ? 'Cần sửa thông tin' : 'Bản nháp chưa gửi' }}
                </p>
              </div>
            </div>
            <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-error hover:bg-error/10 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-error/20" :aria-label="draft.state === 'saved' ? `Ẩn dòng đã lưu ${index + 1} khỏi hàng đợi` : `Xóa dòng nháp ${index + 1}`" @click="removeDraft(draft)">
              <span class="material-symbols-outlined" aria-hidden="true">{{ draft.state === 'saved' ? 'close' : 'delete' }}</span>
            </button>
          </div>

          <p v-if="draft.errors.general" role="alert" class="mb-4 rounded-lg bg-error/5 p-3 text-sm text-error">{{ draft.errors.general }}</p>

          <fieldset :disabled="draft.state === 'saving' || draft.state === 'saved'" class="grid gap-4 lg:grid-cols-2">
            <legend class="sr-only">Thông tin sách số {{ index + 1 }}</legend>
            <label class="space-y-1 text-sm font-bold text-on-surface">
              <span>Tên sách</span>
              <input v-model="draft.title" type="text" maxlength="255" aria-required="true" class="queue-input" :aria-describedby="draft.errors.title ? `title-error-${draft.clientId}` : undefined" />
              <small v-if="draft.errors.title" :id="`title-error-${draft.clientId}`" class="block font-normal text-error">{{ draft.errors.title }}</small>
            </label>
            <label class="space-y-1 text-sm font-bold text-on-surface">
              <span>Người viết trên bìa</span>
              <input v-model="draft.author_name" type="text" maxlength="255" aria-required="true" class="queue-input" :aria-describedby="draft.errors.author_name ? `author-error-${draft.clientId}` : undefined" />
              <small v-if="draft.errors.author_name" :id="`author-error-${draft.clientId}`" class="block font-normal text-error">{{ draft.errors.author_name }}</small>
            </label>
            <label class="space-y-1 text-sm font-bold text-on-surface">
              <span>Danh mục</span>
              <select v-model="draft.category_id" aria-required="true" class="queue-input" :aria-describedby="draft.errors.category_id ? `category-error-${draft.clientId}` : undefined">
                <option value="" disabled>Chọn danh mục</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
              </select>
              <small v-if="draft.errors.category_id" :id="`category-error-${draft.clientId}`" class="block font-normal text-error">{{ draft.errors.category_id }}</small>
            </label>
            <label class="space-y-1 text-sm font-bold text-on-surface">
              <span>Tình trạng</span>
              <select v-model="draft.condition" class="queue-input">
                <option v-for="option in conditionOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
            </label>
            <label class="space-y-1 text-sm font-bold text-on-surface">
              <span>Giá bán (VND)</span>
              <input v-model.number="draft.price" type="number" min="1000" step="1000" aria-required="true" class="queue-input" :aria-describedby="draft.errors.price ? `price-error-${draft.clientId}` : undefined" />
              <small v-if="draft.errors.price" :id="`price-error-${draft.clientId}`" class="block font-normal text-error">{{ draft.errors.price }}</small>
            </label>
            <label class="space-y-1 text-sm font-bold text-on-surface">
              <span>Số lượng</span>
              <input v-model.number="draft.quantity" type="number" min="1" max="100" aria-required="true" class="queue-input" :aria-describedby="draft.errors.quantity ? `quantity-error-${draft.clientId}` : undefined" />
              <small v-if="draft.errors.quantity" :id="`quantity-error-${draft.clientId}`" class="block font-normal text-error">{{ draft.errors.quantity }}</small>
            </label>
            <label class="space-y-1 text-sm font-bold text-on-surface lg:col-span-2">
              <span>Mô tả ngắn</span>
              <textarea v-model="draft.description" rows="3" maxlength="5000" class="queue-input resize-y"></textarea>
            </label>
            <label class="space-y-1 text-sm font-bold text-on-surface lg:col-span-2">
              <span>Khuyết điểm và dấu vết sử dụng</span>
              <textarea v-model="draft.defects" rows="2" maxlength="3000" class="queue-input resize-y" placeholder="Ví dụ: xước nhẹ ở gáy, có ghi chú bằng bút chì..."></textarea>
            </label>
            <div class="space-y-2 lg:col-span-2">
              <label class="block text-sm font-bold text-on-surface" :for="`photos-${draft.clientId}`">Ảnh thật của sách (1–8 ảnh)</label>
              <input :id="`photos-${draft.clientId}`" type="file" accept="image/*" multiple aria-required="true" class="queue-input file:mr-3 file:rounded-md file:border-0 file:bg-primary-container file:px-3 file:py-2 file:font-bold file:text-on-primary-container" :aria-describedby="draft.errors.actual_photos ? `photos-error-${draft.clientId}` : undefined" @change="onPhotos(draft, $event)" />
              <small v-if="draft.errors.actual_photos" :id="`photos-error-${draft.clientId}`" class="block text-error">{{ draft.errors.actual_photos }}</small>
              <div v-if="draft.photoPreviews.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                <figure v-for="photo in draft.photoPreviews" :key="photo.url" class="overflow-hidden rounded-lg border border-outline-variant/30 bg-surface-container-low">
                  <img :src="photo.url" :alt="photo.alt" class="aspect-square w-full object-cover" />
                  <figcaption class="truncate p-2 text-xs text-on-surface-variant">{{ photo.name }}</figcaption>
                </figure>
              </div>
            </div>
            <label class="flex min-h-11 items-start gap-3 rounded-lg border border-warning/30 bg-warning/5 p-3 text-sm leading-6 text-on-surface lg:col-span-2">
              <input v-model="draft.authenticity_attested" type="checkbox" class="mt-1 h-5 w-5 shrink-0 accent-primary" :aria-describedby="draft.errors.authenticity_attested ? `attest-error-${draft.clientId}` : undefined" />
              <span>Tôi cam kết đây là sách thật và chịu trách nhiệm nếu cung cấp sách giả.
                <small v-if="draft.errors.authenticity_attested" :id="`attest-error-${draft.clientId}`" class="block text-error">{{ draft.errors.authenticity_attested }}</small>
              </span>
            </label>
          </fieldset>

          <div class="mt-5 flex justify-end">
            <button type="submit" :disabled="draft.state === 'saving' || draft.state === 'saved'" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 font-bold text-on-primary disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim">
              <span class="material-symbols-outlined" aria-hidden="true">{{ draft.state === 'saved' ? 'check_circle' : 'save' }}</span>
              {{ draft.state === 'saving' ? 'Đang lưu...' : draft.state === 'saved' ? 'Đã lưu' : 'Lưu sách này' }}
            </button>
          </div>
        </form>
      </section>

      <section class="space-y-4" aria-labelledby="published-listings-heading">
        <div>
          <h2 id="published-listings-heading" class="text-2xl font-bold text-primary">Sách đã đăng</h2>
          <p class="mt-1 text-sm text-on-surface-variant">Tồn khả dụng, đã giữ, đã bán và đã trả được theo dõi riêng cho từng listing.</p>
        </div>
        <div v-if="!listings.length" class="rounded-xl border border-dashed border-outline-variant p-8 text-center text-on-surface-variant">
          <span class="material-symbols-outlined text-4xl" aria-hidden="true">inventory_2</span>
          <p class="mt-2">Chưa có sách cũ nào được đăng.</p>
        </div>
        <div v-else class="grid gap-4 xl:grid-cols-2">
          <article v-for="listing in listings" :key="listing.id" class="grid gap-4 rounded-xl border border-outline-variant/30 bg-surface p-4 shadow-sm sm:grid-cols-[96px_minmax(0,1fr)]">
            <div class="flex h-32 w-24 items-center justify-center overflow-hidden bg-surface-container-low">
              <img v-if="listing.book?.cover_image" :src="listing.book.cover_image" :alt="`Bìa ${listing.book.title}`" class="h-full w-full object-contain" loading="lazy" />
              <span v-else class="material-symbols-outlined text-3xl text-on-surface-variant" aria-hidden="true">menu_book</span>
            </div>
            <div class="min-w-0 space-y-3">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h3 class="font-bold text-on-surface">{{ listing.book?.title || `Listing #${listing.id}` }}</h3>
                  <p class="text-sm text-on-surface-variant">{{ listing.book?.author }} · {{ conditionLabel(listing.condition) }} · {{ money(listing.book?.price) }}</p>
                </div>
                <span class="rounded-full bg-primary-container px-3 py-1 text-xs font-bold text-on-primary-container">{{ statusLabel(listing.status) }}</span>
              </div>
              <dl class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div><dt class="text-on-surface-variant">Khả dụng</dt><dd class="font-bold">{{ listing.quantity_available }}</dd></div>
                <div><dt class="text-on-surface-variant">Đã giữ</dt><dd class="font-bold">{{ listing.quantity_reserved }}</dd></div>
                <div><dt class="text-on-surface-variant">Đã bán</dt><dd class="font-bold">{{ listing.quantity_sold }}</dd></div>
                <div><dt class="text-on-surface-variant">Đã trả</dt><dd class="font-bold">{{ listing.quantity_returned }}</dd></div>
              </dl>
              <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <label class="flex-1 space-y-1 text-sm font-bold text-on-surface">
                  <span>Cập nhật tồn khả dụng</span>
                  <input v-model.number="inventoryValues[listing.id]" type="number" min="0" max="100" class="queue-input" />
                </label>
                <button type="button" :disabled="inventoryBusy === listing.id" class="min-h-11 rounded-lg border border-primary px-4 font-bold text-primary hover:bg-primary/5 disabled:opacity-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim" @click="saveInventory(listing)">
                  {{ inventoryBusy === listing.id ? 'Đang lưu...' : 'Lưu tồn' }}
                </button>
              </div>
            </div>
          </article>
        </div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.queue-input {
  min-height: 44px;
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid var(--color-outline-variant);
  background: var(--color-surface);
  padding: 0.7rem 0.85rem;
  color: var(--color-on-surface);
}
.queue-input:focus {
  border-color: var(--color-primary);
  outline: 3px solid color-mix(in srgb, var(--color-primary) 18%, transparent);
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
}
</style>
