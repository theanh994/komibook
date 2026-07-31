<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import apiClient from '@/services/axios'

const activeTab = ref('flash')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const message = ref('')
const books = ref([])
const categories = ref([])
const requests = ref([])
const campaigns = ref([])
const coupons = ref([])

const emptyGroup = () => ({ book_ids: [], discount_percent: 15, max_quantity: 10, search: '' })
const flashForm = reactive({
  title: '',
  preferred_start_time: '',
  preferred_end_time: '',
  vendor_note: '',
  groups: [emptyGroup()],
})
const couponForm = reactive({
  code: '',
  discount_percent: 10,
  min_order_value: 0,
  max_discount_amount: 50000,
  start_time: '',
  end_time: '',
  usage_limit: 100,
  stacking_policy: 'deny',
  scope_type: 'store',
  category_id: '',
  scope_book_ids: [],
  search: '',
})

const formatDate = (value) => value ? new Date(value).toLocaleString('vi-VN') : '—'
const statusLabel = (status) => ({
  pending: 'Chờ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
  enrollment_open: 'Đang mở đăng ký',
  active: 'Đang chạy',
  upcoming: 'Sắp diễn ra',
}[status] || status)
const normalizeList = (response) => {
  const payload = response.data?.data
  return Array.isArray(payload) ? payload : payload?.data || []
}
const filteredBooks = (search) => {
  const keyword = search.trim().toLocaleLowerCase('vi')
  if (!keyword) return books.value
  return books.value.filter((book) => book.title.toLocaleLowerCase('vi').includes(keyword))
}
const bookTitle = (id) => books.value.find((book) => Number(book.id) === Number(id))?.title || `Sách #${id}`
const selectedCouponBooks = computed(() => couponForm.scope_book_ids.map((id) => ({ id, title: bookTitle(id) })))
const scopeLabel = (coupon) => {
  if (coupon.scope_book_ids?.length) return `${coupon.scope_book_ids.length} sách cụ thể`
  if (coupon.category_id) return `Thể loại #${coupon.category_id}`
  return 'Toàn gian hàng'
}
const addGroup = () => flashForm.groups.push(emptyGroup())
const removeGroup = (index) => {
  if (flashForm.groups.length > 1) flashForm.groups.splice(index, 1)
}
const toggleBook = (target, bookId) => {
  const numericId = Number(bookId)
  const index = target.indexOf(numericId)
  if (index >= 0) target.splice(index, 1)
  else target.push(numericId)
}

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const [bookResponse, categoryResponse, requestResponse, campaignResponse, couponResponse] = await Promise.all([
      apiClient.get('/api/vendor/books', { params: { status: 'published', per_page: 100 } }),
      apiClient.get('/api/categories'),
      apiClient.get('/api/vendor/flash-sale-requests'),
      apiClient.get('/api/vendor/flash-sales'),
      apiClient.get('/api/vendor/coupons'),
    ])
    books.value = normalizeList(bookResponse)
    categories.value = normalizeList(categoryResponse)
    requests.value = normalizeList(requestResponse)
    campaigns.value = normalizeList(campaignResponse)
    coupons.value = normalizeList(couponResponse)
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể tải trung tâm khuyến mãi.'
  } finally {
    loading.value = false
  }
}

const submitFlashRequest = async () => {
  saving.value = true
  error.value = ''
  message.value = ''
  try {
    const payload = {
      title: flashForm.title,
      preferred_start_time: flashForm.preferred_start_time,
      preferred_end_time: flashForm.preferred_end_time,
      vendor_note: flashForm.vendor_note,
      groups: flashForm.groups.map(({ book_ids, discount_percent, max_quantity }) => ({
        book_ids,
        discount_percent,
        max_quantity,
      })),
    }
    const response = await apiClient.post('/api/vendor/flash-sale-requests', payload)
    message.value = response.data.message
    Object.assign(flashForm, { title: '', preferred_start_time: '', preferred_end_time: '', vendor_note: '', groups: [emptyGroup()] })
    await load()
  } catch (requestError) {
    const validation = requestError.response?.data?.errors
    error.value = validation ? Object.values(validation).flat().join(' ') : (requestError.response?.data?.message || 'Không thể gửi đề xuất Flash Sale.')
  } finally {
    saving.value = false
  }
}

const submitCoupon = async () => {
  saving.value = true
  error.value = ''
  message.value = ''
  try {
    const { search, ...fields } = couponForm
    const payload = {
      ...fields,
      code: couponForm.code.trim().toUpperCase(),
      category_id: couponForm.scope_type === 'category' ? couponForm.category_id : null,
      scope_book_ids: couponForm.scope_type === 'books' ? couponForm.scope_book_ids : null,
    }
    const response = await apiClient.post('/api/vendor/coupons', payload)
    message.value = response.data.message
    Object.assign(couponForm, {
      code: '', discount_percent: 10, min_order_value: 0, max_discount_amount: 50000,
      start_time: '', end_time: '', usage_limit: 100, stacking_policy: 'deny',
      scope_type: 'store', category_id: '', scope_book_ids: [], search: '',
    })
    await load()
  } catch (requestError) {
    const validation = requestError.response?.data?.errors
    error.value = validation ? Object.values(validation).flat().join(' ') : (requestError.response?.data?.message || 'Không thể tạo mã giảm giá.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-sm font-bold uppercase tracking-wider text-secondary">Khuyến mãi Nhà bán</p>
        <h1 class="mt-2 text-3xl font-bold text-primary">Flash Sale &amp; mã giảm giá</h1>
        <p class="mt-2 max-w-3xl text-on-surface-variant">Form đề xuất Flash Sale hoạt động bất cứ lúc nào. Bạn có thể tạo một chiến dịch gồm nhiều nhóm mức giảm, hoặc phát hành voucher theo phạm vi gian hàng, thể loại hay sách cụ thể.</p>
      </div>
      <button class="ui-btn ui-btn-secondary" type="button" @click="load">Làm mới dữ liệu</button>
    </header>

    <nav class="inline-flex max-w-full gap-1 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-1" aria-label="Loại khuyến mãi">
      <button type="button" class="min-h-11 rounded-lg px-4 font-bold" :class="activeTab === 'flash' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'" @click="activeTab = 'flash'">Đăng ký Flash Sale</button>
      <button type="button" class="min-h-11 rounded-lg px-4 font-bold" :class="activeTab === 'coupon' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'" @click="activeTab = 'coupon'">Mã giảm giá</button>
    </nav>

    <p v-if="message" class="ui-alert" role="status">{{ message }}</p>
    <div v-if="error" class="ui-alert ui-alert-error flex flex-wrap items-center justify-between gap-3" role="alert"><span>{{ error }}</span><button type="button" class="ui-btn ui-btn-secondary" @click="load">Thử lại</button></div>
    <div v-if="loading" class="ui-skeleton h-72" role="status" aria-label="Đang tải khuyến mãi"></div>

    <template v-else-if="activeTab === 'flash'">
      <div class="grid min-w-0 items-start gap-6 2xl:grid-cols-[minmax(0,1fr)_360px]">
        <form class="ui-panel min-w-0 space-y-5" @submit.prevent="submitFlashRequest">
          <div><h2 class="text-xl font-bold text-primary">Đề xuất chiến dịch Flash Sale</h2><p class="mt-1 text-sm text-on-surface-variant">Mỗi nhóm có mức giảm và giới hạn riêng; một sách chỉ nằm trong một nhóm.</p></div>
          <label class="block font-bold">Tên chiến dịch<input v-model.trim="flashForm.title" class="ui-field mt-2" required maxlength="255" placeholder="Ví dụ: Tuần lễ sách thiếu nhi" /></label>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block font-bold">Bắt đầu mong muốn<input v-model="flashForm.preferred_start_time" class="ui-field mt-2" type="datetime-local" required /></label>
            <label class="block font-bold">Kết thúc mong muốn<input v-model="flashForm.preferred_end_time" class="ui-field mt-2" type="datetime-local" required /></label>
          </div>

          <fieldset class="space-y-4">
            <legend class="font-bold">Các nhóm mức giảm</legend>
            <article v-for="(group, index) in flashForm.groups" :key="index" class="rounded-xl border border-outline-variant/40 bg-surface-container p-4">
              <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="font-bold text-primary">Nhóm {{ index + 1 }}</h3><button type="button" class="ui-btn ui-btn-secondary" :disabled="flashForm.groups.length === 1" @click="removeGroup(index)">Xóa nhóm</button></div>
              <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block font-bold">Mức giảm (%)<input v-model.number="group.discount_percent" class="ui-field mt-2" type="number" min="1" max="90" required /></label>
                <label class="block font-bold">Số lượng tối đa / sách<input v-model.number="group.max_quantity" class="ui-field mt-2" type="number" min="1" /></label>
              </div>
              <label class="mt-4 block font-bold">Tìm và chọn nhiều sách<input v-model.trim="group.search" class="ui-field mt-2" type="search" placeholder="Nhập tên sách…" /></label>
              <div class="mt-3 max-h-56 space-y-1 overflow-y-auto rounded-lg border border-outline-variant/40 bg-surface-container-lowest p-2">
                <label v-for="book in filteredBooks(group.search)" :key="book.id" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 hover:bg-surface-container">
                  <input type="checkbox" :checked="group.book_ids.includes(Number(book.id))" @change="toggleBook(group.book_ids, book.id)" />
                  <span class="min-w-0 truncate">{{ book.title }}</span>
                </label>
              </div>
              <p class="mt-2 text-sm text-on-surface-variant">Đã chọn {{ group.book_ids.length }} sách.</p>
            </article>
            <button type="button" class="ui-btn ui-btn-secondary" @click="addGroup">+ Thêm nhóm mức giảm</button>
          </fieldset>
          <label class="block font-bold">Ghi chú cho ban vận hành<textarea v-model="flashForm.vendor_note" class="ui-field mt-2 min-h-24" maxlength="2000"></textarea></label>
          <button class="ui-btn ui-btn-primary" type="submit" :disabled="saving">{{ saving ? 'Đang gửi…' : 'Gửi đề xuất chiến dịch' }}</button>
        </form>

        <aside class="space-y-4 2xl:sticky 2xl:top-24">
          <div class="ui-panel"><h2 class="text-lg font-bold text-primary">Chiến dịch đang mở</h2><p class="mt-2 text-sm text-on-surface-variant">{{ campaigns.length ? `${campaigns.length} chiến dịch đang mở đăng ký.` : 'Chưa có chiến dịch mở; form đề xuất vẫn luôn hoạt động.' }}</p></div>
          <div class="ui-panel"><h2 class="text-lg font-bold text-primary">Quản lý đề xuất chung</h2><div v-if="!requests.length" class="mt-4 text-sm text-on-surface-variant">Chưa có đề xuất.</div><ul v-else class="mt-4 space-y-3"><li v-for="item in requests" :key="item.id" class="rounded-lg border border-outline-variant/40 p-3"><div class="flex justify-between gap-3"><strong>{{ item.title }}</strong><span class="text-sm font-bold text-secondary">{{ statusLabel(item.status) }}</span></div><p class="mt-1 text-sm text-on-surface-variant">{{ item.groups?.length || 1 }} nhóm · {{ item.groups?.reduce((sum, group) => sum + group.book_ids.length, 0) || 1 }} sách</p><p class="mt-1 text-xs text-on-surface-variant">{{ formatDate(item.preferred_start_time) }} → {{ formatDate(item.preferred_end_time) }}</p></li></ul></div>
        </aside>
      </div>
    </template>

    <template v-else>
      <div class="grid min-w-0 items-start gap-6 2xl:grid-cols-[minmax(0,1fr)_360px]">
        <form class="ui-panel min-w-0 space-y-5" @submit.prevent="submitCoupon">
          <div><h2 class="text-xl font-bold text-primary">Tạo mã giảm giá gian hàng</h2><p class="mt-1 text-sm text-on-surface-variant">Chọn phạm vi rõ ràng; mã mới sẽ chờ Admin duyệt trước khi sử dụng.</p></div>
          <div class="grid gap-4 sm:grid-cols-2"><label class="block font-bold">Mã voucher<input v-model.trim="couponForm.code" class="ui-field mt-2 uppercase" required maxlength="40" placeholder="KOMI10" /></label><label class="block font-bold">Giảm (%)<input v-model.number="couponForm.discount_percent" class="ui-field mt-2" type="number" min="1" max="90" required /></label></div>
          <div class="grid gap-4 sm:grid-cols-2"><label class="block font-bold">Đơn tối thiểu<input v-model.number="couponForm.min_order_value" class="ui-field mt-2" type="number" min="0" /></label><label class="block font-bold">Giảm tối đa<input v-model.number="couponForm.max_discount_amount" class="ui-field mt-2" type="number" min="0" /></label></div>
          <div class="grid gap-4 sm:grid-cols-2"><label class="block font-bold">Bắt đầu<input v-model="couponForm.start_time" class="ui-field mt-2" type="datetime-local" required /></label><label class="block font-bold">Kết thúc<input v-model="couponForm.end_time" class="ui-field mt-2" type="datetime-local" required /></label></div>

          <fieldset class="space-y-3">
            <legend class="font-bold">Phạm vi áp dụng</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label v-for="option in [{ value: 'store', label: 'Toàn gian hàng' }, { value: 'category', label: 'Theo thể loại' }, { value: 'books', label: 'Sách cụ thể' }]" :key="option.value" class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-outline-variant/40 p-3" :class="{ 'border-primary bg-primary-container': couponForm.scope_type === option.value }"><input v-model="couponForm.scope_type" type="radio" :value="option.value" /><strong>{{ option.label }}</strong></label>
            </div>
            <label v-if="couponForm.scope_type === 'category'" class="block font-bold">Thể loại<select v-model="couponForm.category_id" class="ui-field mt-2" required><option value="" disabled>Chọn thể loại có sách của gian hàng</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
            <div v-if="couponForm.scope_type === 'books'" class="rounded-xl border border-outline-variant/40 p-4">
              <label class="block font-bold">Tìm sách<input v-model.trim="couponForm.search" class="ui-field mt-2" type="search" placeholder="Nhập tên sách…" /></label>
              <div class="mt-3 max-h-56 space-y-1 overflow-y-auto">
                <label v-for="book in filteredBooks(couponForm.search)" :key="book.id" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 hover:bg-surface-container"><input type="checkbox" :checked="couponForm.scope_book_ids.includes(Number(book.id))" @change="toggleBook(couponForm.scope_book_ids, book.id)" /><span class="truncate">{{ book.title }}</span></label>
              </div>
              <div v-if="selectedCouponBooks.length" class="mt-3 flex flex-wrap gap-2"><button v-for="book in selectedCouponBooks" :key="book.id" type="button" class="rounded-full bg-primary-container px-3 py-2 text-sm font-semibold text-on-primary-container" @click="toggleBook(couponForm.scope_book_ids, book.id)">{{ book.title }} ×</button></div>
            </div>
          </fieldset>

          <div class="grid gap-4 sm:grid-cols-2"><label class="block font-bold">Giới hạn lượt dùng<input v-model.number="couponForm.usage_limit" class="ui-field mt-2" type="number" min="1" /></label><label class="block font-bold">Dùng cùng Flash Sale<select v-model="couponForm.stacking_policy" class="ui-field mt-2"><option value="deny">Không cho phép</option><option value="allow">Cho phép</option></select></label></div>
          <button class="ui-btn ui-btn-primary" type="submit" :disabled="saving">{{ saving ? 'Đang tạo…' : 'Tạo và gửi duyệt voucher' }}</button>
        </form>

        <aside class="ui-panel 2xl:sticky 2xl:top-24"><h2 class="text-lg font-bold text-primary">Voucher của gian hàng</h2><div v-if="!coupons.length" class="mt-4 text-sm text-on-surface-variant">Chưa có voucher.</div><ul v-else class="mt-4 space-y-3"><li v-for="item in coupons" :key="item.id" class="rounded-lg border border-outline-variant/40 p-3"><div class="flex justify-between gap-3"><strong class="font-mono text-primary">{{ item.code }}</strong><span class="text-sm font-bold text-secondary">{{ statusLabel(item.status) }}</span></div><p class="mt-1 text-sm text-on-surface-variant">{{ scopeLabel(item) }} · giảm {{ item.discount_percent }}%</p><p class="mt-1 text-xs text-on-surface-variant">Đến {{ formatDate(item.end_time) }}</p></li></ul></aside>
      </div>
    </template>
  </section>
</template>
