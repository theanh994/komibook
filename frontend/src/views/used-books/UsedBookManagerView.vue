<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import UserSidebar from '@/components/profile/UserSidebar.vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/axios'

const router = useRouter()
const authStore = useAuthStore()

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
const showAddressEdit = ref(false)

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
  { value: 'like_new', label: 'Như mới (95 - 99%)', desc: 'Bìa đẹp, trang sách sạch, không ghi chú' },
  { value: 'good', label: 'Tốt (80 - 90%)', desc: 'Có vết sờn nhẹ, trang hơi ngả màu theo thời gian' },
  { value: 'fair', label: 'Khá (60 - 79%)', desc: 'Bìa cũ, có nếp gấp hoặc gạch chân bằng bút chì' },
]

const newDraft = () => ({
  clientId: crypto.randomUUID?.() || `draft-${Date.now()}-${Math.random()}`,
  title: '',
  author_name: '',
  description: '',
  category_id: '',
  price: 10000,
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
const activeListingsCount = computed(() => listings.value.filter(l => l.status === 'active').length)
const money = value => `${Number(value || 0).toLocaleString('vi-VN')} đ`
const conditionLabel = value => conditionOptions.find(option => option.value === value)?.label || value
const statusLabel = value => ({ pending: 'Chờ xét duyệt', active: 'Đang bán', rejected: 'Bị từ chối', suspended: 'Tạm dừng', sold_out: 'Hết hàng', draft: 'Bản nháp' }[value] || value)
const statusBadgeClass = status => {
  switch (status) {
    case 'active':
      return 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/20'
    case 'pending':
      return 'bg-amber-500/10 text-amber-700 border border-amber-500/20'
    case 'rejected':
      return 'bg-red-500/10 text-red-700 border border-red-500/20'
    case 'suspended':
      return 'bg-rose-500/10 text-rose-700 border border-rose-500/20'
    default:
      return 'bg-slate-500/10 text-slate-700 border border-slate-500/20'
  }
}

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
  const newFiles = Array.from(event.target.files || []).slice(0, 8)
  draft.actual_photos = newFiles
  draft.photoPreviews = newFiles.map((file, index) => ({
    name: file.name,
    url: URL.createObjectURL(file),
    alt: `Ảnh thật ${index + 1} của ${draft.title || 'sách cũ'}`,
  }))
  delete draft.errors.actual_photos
}

const removeSinglePhoto = (draft, photoIndex) => {
  if (draft.photoPreviews[photoIndex]) {
    URL.revokeObjectURL(draft.photoPreviews[photoIndex].url)
  }
  draft.actual_photos.splice(photoIndex, 1)
  draft.photoPreviews.splice(photoIndex, 1)
}

const validateDraft = draft => {
  const errors = {}
  if (!draft.title.trim()) errors.title = 'Vui lòng nhập tên sách.'
  if (!draft.author_name.trim()) errors.author_name = 'Vui lòng nhập tên tác giả.'
  if (!draft.category_id) errors.category_id = 'Vui lòng chọn danh mục.'
  if (Number(draft.price) < 1000) errors.price = 'Giá tối thiểu là 1.000 đ.'
  if (Number(draft.quantity) < 1 || Number(draft.quantity) > 100) errors.quantity = 'Số lượng từ 1 đến 100.'
  if (!draft.actual_photos.length) errors.actual_photos = 'Tải lên ít nhất 1 ảnh chụp thực tế của sách.'
  if (!draft.authenticity_attested) errors.authenticity_attested = 'Bạn cần cam kết sách bán là sách thật.'
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
    draft.errors = { general: 'Vui lòng điền và lưu Địa chỉ gửi hàng riêng tư bên trên trước khi đăng sách.' }
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
    notice.value = `Đã gửi cuốn "${draft.title}" thành công! Sách đang được Admin kiểm duyệt thông tin & ảnh chụp thực tế trước khi xuất hiện trên sàn.`
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
    ? `Đã đăng thành công ${saved} sách. ${failed ? `${failed} dòng bị lỗi cần kiểm tra lại.` : 'Tất cả sách đã lên sàn!'}`
    : (failed ? 'Chưa có sách nào được đăng; vui lòng sửa các lỗi hiển thị đỏ.' : '')
  bulkBusy.value = false
}

const saveInventory = async listing => {
  inventoryBusy.value = listing.id
  try {
    const response = await apiClient.patch(`/api/used-book-seller/listings/${listing.id}/inventory`, {
      quantity_available: Number(inventoryValues[listing.id]),
    })
    Object.assign(listing, response.data.data)
    notice.value = `Đã cập nhật số lượng khả dụng cho "${listing.book?.title || `listing #${listing.id}`}".`
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể cập nhật tồn kho.'
    inventoryValues[listing.id] = listing.quantity_available
  } finally {
    inventoryBusy.value = null
  }
}

const activeTab = ref('listings')
const sellerOrders = ref([])
const orderFilterStatus = ref('all')
const loadingOrders = ref(false)

const walletData = ref({ balance: 0, reserved_balance: 0, entries: [] })
const loadingWallet = ref(false)

const showShipModal = ref(false)
const shippingOrder = ref(null)
const shipForm = reactive({
  shipping_carrier: 'KomiExpress C2C',
  shipping_tracking_code: '',
})
const shippingBusy = ref(false)

const copyToClipboard = text => {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text)
  }
  notice.value = 'Đã sao chép địa chỉ giao hàng của người mua vào khay nhớ tạm!'
}

const fetchSellerOrders = async () => {
  loadingOrders.value = true
  try {
    const res = await apiClient.get('/api/used-book-seller/orders', {
      params: { status: orderFilterStatus.value },
    })
    sellerOrders.value = res.data.data?.data || []
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể tải danh sách đơn bán sách cũ.'
  } finally {
    loadingOrders.value = false
  }
}

const fetchWalletSummary = async () => {
  loadingWallet.value = true
  try {
    const res = await apiClient.get('/api/used-book-seller/wallet')
    walletData.value = res.data.data || { balance: 0, reserved_balance: 0, entries: [] }
  } catch {
    // ignore
  } finally {
    loadingWallet.value = false
  }
}

const openShipModal = order => {
  shippingOrder.value = order
  shipForm.shipping_carrier = order.shipping_carrier || 'KomiExpress C2C'
  shipForm.shipping_tracking_code = order.shipping_tracking_code || `KM-C2C-${Date.now().toString().slice(-6)}`
  showShipModal.value = true
}

const submitShipOrder = async () => {
  if (!shippingOrder.value) return
  shippingBusy.value = true
  loadError.value = ''
  try {
    const res = await apiClient.post(`/api/used-book-seller/orders/${shippingOrder.value.id}/ship`, shipForm)
    notice.value = res.data.message || 'Đã xác nhận gửi hàng thành công!'
    showShipModal.value = false
    await fetchSellerOrders()
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể xác nhận gửi hàng.'
  } finally {
    shippingBusy.value = false
  }
}

const confirmDelivery = async order => {
  if (!window.confirm(`Xác nhận đơn hàng #${order.order_code} đã được giao thành công cho khách và hoàn tất đơn?`)) return
  loadError.value = ''
  try {
    const res = await apiClient.post(`/api/used-book-seller/orders/${order.id}/confirm-delivered`)
    notice.value = res.data.message || 'Đã hoàn tất đơn hàng và cộng doanh thu vào Ví KomiBook!'
    await fetchSellerOrders()
    await fetchWalletSummary()
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể hoàn tất đơn hàng.'
  }
}

const advanceStep = async order => {
  loadError.value = ''
  try {
    const res = await apiClient.post(`/api/used-book-seller/orders/${order.id}/advance-shipping`)
    notice.value = res.data.message || 'Đã cập nhật hành trình giao hàng!'
    await fetchSellerOrders()
    if (res.data.data?.status === 'completed') {
      await fetchWalletSummary()
    }
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể cập nhật bước giao hàng.'
  }
}

const saveAddress = async () => {
  addressBusy.value = true
  loadError.value = ''
  try {
    await apiClient.put('/api/used-book-seller/fulfillment-address', addressForm)
    addressReady.value = true
    showAddressEdit.value = false
    notice.value = 'Đã cập nhật thành công địa chỉ gửi hàng riêng tư.'
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể lưu địa chỉ gửi hàng.'
  } finally {
    addressBusy.value = false
  }
}

const load = async () => {
  if (!authStore.isAuthenticated) {
    loading.value = false
    return
  }

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
      loadError.value = listingResult.reason?.response?.data?.message || 'Không thể tải danh sách sách cũ.'
    }

    if (categoryResult.status === 'fulfilled') {
      const categoryPayload = categoryResult.value.data?.data ?? categoryResult.value.data ?? []
      categories.value = Array.isArray(categoryPayload) ? categoryPayload : (categoryPayload.data || [])
    }

    if (addressResult.status === 'fulfilled' && addressResult.value.data?.data) {
      Object.assign(addressForm, addressResult.value.data.data)
      addressReady.value = addressResult.value.data.data.status === 'verified'
      if (!addressReady.value) showAddressEdit.value = true
    } else {
      showAddressEdit.value = true
    }

    listings.value.forEach(listing => { inventoryValues[listing.id] = listing.quantity_available })
    if (!drafts.value.length) addDraft()
    fetchSellerOrders()
    fetchWalletSummary()
  } catch (requestError) {
    loadError.value = requestError.response?.data?.message || 'Không thể tải thông tin khu vực Bán sách cũ.'
    if (!drafts.value.length) addDraft()
  } finally {
    loading.value = false
  }
}

onMounted(load)
onBeforeUnmount(() => drafts.value.forEach(revokePreviews))
</script>

<template>
  <div class="min-h-screen bg-background font-inter antialiased">
    <!-- GUEST ONBOARDING VIEW (Chưa đăng nhập) -->
    <div v-if="!authStore.isAuthenticated" class="w-full px-gutter max-w-[1280px] mx-auto py-xl">
        <!-- Hero Header -->
        <header class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-primary via-primary-container to-secondary p-8 text-on-primary shadow-elevated sm:p-12">
          <div class="relative z-10 max-w-3xl space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1 text-xs font-bold text-white uppercase tracking-wider">
              <span class="material-symbols-outlined text-base">recycling</span>
              <span>Kênh Bán Sách Cũ KomiBook</span>
            </div>
            <h1 class="text-3xl font-black text-white sm:text-4xl lg:text-5xl leading-tight">
              Biến tủ sách cá nhân thành thu nhập
            </h1>
            <p class="text-base leading-relaxed text-primary-fixed-dim sm:text-lg">
              Đăng bán dễ dàng sách đã qua sử dụng, định giá chủ động và nhận tiền trực tiếp về Ví KomiBook. Minh bạch, an toàn và bảo mật địa chỉ riêng tư.
            </p>
            <div class="pt-3 flex flex-wrap gap-3">
              <RouterLink
                to="/login?redirect=/used-books/manage"
                class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-white px-6 py-3 font-bold text-primary shadow-xs transition hover:bg-slate-100 no-underline"
              >
                <span class="material-symbols-outlined">login</span>
                <span>Đăng Nhập Để Bán Sách</span>
              </RouterLink>

              <RouterLink
                to="/register"
                class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-6 py-3 font-bold text-white transition hover:bg-white/20 no-underline"
              >
                <span class="material-symbols-outlined">person_add</span>
                <span>Tạo Tài Khoản Mới</span>
              </RouterLink>
            </div>
          </div>
        </header>

        <!-- 4-Step How It Works Guide -->
        <section class="mt-12 space-y-8">
          <div class="text-center max-w-2xl mx-auto space-y-2">
            <h2 class="text-2xl font-black text-on-surface sm:text-3xl">4 Bước Đăng Bán Sách Cũ Đơn Giản</h2>
            <p class="text-sm text-on-surface-variant">Quy trình vận hành khép kín đảm bảo quyền lợi cho cả người bán lẫn độc giả mua sách.</p>
          </div>

          <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Step 1 -->
            <div class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-2xs space-y-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary font-black text-xl">1</div>
              <h3 class="font-bold text-on-surface text-base">Chụp ảnh & Khai báo</h3>
              <p class="text-xs leading-relaxed text-on-surface-variant">Tải từ 1-8 ảnh thật của sách và chọn tình trạng thực tế (Như mới, Tốt, Khá).</p>
            </div>

            <!-- Step 2 -->
            <div class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-2xs space-y-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-600 font-black text-xl">2</div>
              <h3 class="font-bold text-on-surface text-base">Bảo mật địa chỉ gửi</h3>
              <p class="text-xs leading-relaxed text-on-surface-variant">Khai báo địa chỉ lấy hàng riêng tư. Thông tin được giữ kín 100% không hiển thị cho người mua.</p>
            </div>

            <!-- Step 3 -->
            <div class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-2xs space-y-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10 text-blue-600 font-black text-xl">3</div>
              <h3 class="font-bold text-on-surface text-base">Đồng bộ đơn hàng</h3>
              <p class="text-xs leading-relaxed text-on-surface-variant">Khi độc giả đặt mua, hệ thống tự động thông báo và hướng dẫn bàn giao cho đơn vị vận chuyển.</p>
            </div>

            <!-- Step 4 -->
            <div class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-2xs space-y-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-600/10 text-amber-600 font-black text-xl">4</div>
              <h3 class="font-bold text-on-surface text-base">Nhận tiền qua Ví KomiBook</h3>
              <p class="text-xs leading-relaxed text-on-surface-variant">Doanh thu bán sách tự động chuyển vào Ví KomiBook và có thể rút về tài khoản ngân hàng dễ dàng.</p>
            </div>
          </div>
        </section>
    </div>

    <!-- AUTHENTICATED SELLER PORTAL (Đã đăng nhập - 2 Column Layout with UserSidebar) -->
    <div v-else class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row items-stretch gap-xl">
      <!-- Left Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content Container -->
      <main class="flex-1 min-w-0 w-full flex flex-col" aria-labelledby="used-books-title">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
          <!-- Standard Banner Header -->
          <div class="p-lg md:p-xl border-b border-outline-variant/10 bg-gradient-to-r from-surface-container-low to-surface-container-lowest flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <h1 id="used-books-title" class="text-2xl font-black text-on-surface tracking-tight">Kênh Bán Sách Cũ Của Tôi</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/10 text-emerald-700">
                  C2C Seller
                </span>
              </div>
              <p class="text-xs text-on-surface-variant font-medium">Quản lý các cuốn sách cũ đã đăng, định giá chủ động và theo dõi tồn kho thực tế.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
              <div class="flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-1.5 text-xs font-medium">
                <span class="material-symbols-outlined text-primary text-base">storefront</span>
                <span>Đang bán: <strong class="text-on-surface font-bold">{{ activeListingsCount }}</strong></span>
              </div>

              <div class="flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-1.5 text-xs font-medium">
                <span class="material-symbols-outlined text-amber-600 text-base">edit_note</span>
                <span>Bản nháp: <strong class="text-amber-700 font-bold">{{ unsavedCount }}</strong></span>
              </div>

              <router-link 
                to="/wallet"
                class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-primary/20 bg-primary/10 px-3.5 py-1.5 text-xs font-bold text-primary hover:bg-primary/20 transition-all no-underline"
                title="Ví KomiBook nhận tiền bán sách"
              >
                <span class="material-symbols-outlined text-base">account_balance_wallet</span>
                <span>Ví KomiBook</span>
              </router-link>

              <button
                type="button"
                class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-primary px-4 py-1.5 text-xs font-bold text-on-primary shadow-xs transition hover:bg-primary/90 cursor-pointer"
                @click="addDraft"
              >
                <span class="material-symbols-outlined text-base">add</span>
                <span>Thêm dòng sách mới</span>
              </button>
            </div>
          </div>

          <!-- 3-Tab Navigation Bar -->
          <div class="flex items-center gap-2 border-b border-outline-variant/20 px-lg md:px-xl bg-surface-container-low/30 overflow-x-auto no-scrollbar">
            <button 
              type="button"
              @click="activeTab = 'listings'"
              :class="['px-5 py-3.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center gap-2', activeTab === 'listings' ? 'border-primary text-primary bg-primary/5 font-black' : 'border-transparent text-on-surface-variant hover:text-on-surface']"
            >
              <span class="material-symbols-outlined text-lg">auto_stories</span>
              <span>Tủ Sách Đã Đăng & Đăng Mới</span>
              <span class="px-2 py-0.5 rounded-full bg-surface-container-high text-[11px] font-bold text-on-surface-variant">
                {{ listings.length }}
              </span>
            </button>

            <button 
              type="button"
              @click="activeTab = 'orders'; fetchSellerOrders()"
              :class="['px-5 py-3.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center gap-2', activeTab === 'orders' ? 'border-primary text-primary bg-primary/5 font-black' : 'border-transparent text-on-surface-variant hover:text-on-surface']"
            >
              <span class="material-symbols-outlined text-lg">local_shipping</span>
              <span>Đơn Hàng Sách Cũ</span>
              <span v-if="sellerOrders.some(o => o.status === 'processing' || o.status === 'confirmed')" class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
            </button>

            <button 
              type="button"
              @click="activeTab = 'wallet'; fetchWalletSummary()"
              :class="['px-5 py-3.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center gap-2', activeTab === 'wallet' ? 'border-primary text-primary bg-primary/5 font-black' : 'border-transparent text-on-surface-variant hover:text-on-surface']"
            >
              <span class="material-symbols-outlined text-lg">account_balance_wallet</span>
              <span>Doanh Thu & Ví KomiBook</span>
              <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                {{ money(walletData.balance) }}
              </span>
            </button>
          </div>

          <!-- Seller Portal Body -->
          <div class="p-lg md:p-xl space-y-6 flex-1">

        <!-- Dynamic Notice / Alert -->
        <div v-if="loadError" role="alert" class="flex items-center justify-between gap-3 rounded-2xl border border-error/30 bg-error/5 p-4 text-sm text-error">
          <span class="flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">error</span>
            {{ loadError }}
          </span>
          <button type="button" class="font-bold underline cursor-pointer" @click="load">Thử lại</button>
        </div>

        <div v-if="notice" role="status" aria-live="polite" class="flex items-center gap-2 rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
          <span class="material-symbols-outlined text-emerald-700 text-lg">check_circle</span>
          {{ notice }}
        </div>

        <!-- TAB 1: LISTINGS & ADDRESS -->
        <template v-if="activeTab === 'listings'">
        <!-- 1. FULFILLMENT ADDRESS SECTION -->
        <section class="rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-soft space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/15 pb-4">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <span class="material-symbols-outlined text-xl">location_on</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-on-surface">Địa chỉ gửi hàng đã xác minh</h2>
                <p class="text-xs text-on-surface-variant">Địa chỉ dùng để đơn vị vận chuyển đến nhận hàng. Giữ bí mật 100%, không hiển thị cho người mua.</p>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <span class="rounded-full px-3 py-1 text-xs font-bold" :class="addressReady ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                {{ addressReady ? '✓ Đã sẵn sàng' : '⚠ Cần bổ sung địa chỉ' }}
              </span>
              <button
                type="button"
                class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline cursor-pointer"
                @click="showAddressEdit = !showAddressEdit"
              >
                <span>{{ showAddressEdit ? 'Thu gọn' : 'Chỉnh sửa' }}</span>
                <span class="material-symbols-outlined text-base">{{ showAddressEdit ? 'expand_less' : 'expand_more' }}</span>
              </button>
            </div>
          </div>

          <!-- Address Form (Toggleable or required if not ready) -->
          <form v-if="showAddressEdit || !addressReady" class="space-y-4 pt-2" @submit.prevent="saveAddress">
            <fieldset :disabled="addressBusy" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <label class="space-y-1.5 text-xs font-bold text-on-surface">
                <span>Người liên hệ <span class="text-red-500">*</span></span>
                <input v-model.trim="addressForm.recipient_name" required maxlength="255" autocomplete="name" placeholder="Họ và tên người gửi..." class="form-input" />
              </label>

              <label class="space-y-1.5 text-xs font-bold text-on-surface">
                <span>Số điện thoại <span class="text-red-500">*</span></span>
                <input v-model.trim="addressForm.phone" required inputmode="tel" autocomplete="tel" pattern="0[0-9]{9}" placeholder="Ví dụ: 0989999999" class="form-input" />
              </label>

              <label class="space-y-1.5 text-xs font-bold text-on-surface">
                <span>Tỉnh / Thành phố <span class="text-red-500">*</span></span>
                <input v-model.trim="addressForm.province" required maxlength="120" autocomplete="address-level1" placeholder="Ví dụ: Hà Nội" class="form-input" />
              </label>

              <label class="space-y-1.5 text-xs font-bold text-on-surface">
                <span>Quận / Huyện</span>
                <input v-model.trim="addressForm.district" maxlength="120" placeholder="Ví dụ: Cầu Giấy" class="form-input" />
              </label>

              <label class="space-y-1.5 text-xs font-bold text-on-surface">
                <span>Phường / Xã</span>
                <input v-model.trim="addressForm.ward" maxlength="120" placeholder="Ví dụ: Dịch Vọng Hậu" class="form-input" />
              </label>

              <label class="space-y-1.5 text-xs font-bold text-on-surface">
                <span>Mã bưu chính (Optional)</span>
                <input v-model.trim="addressForm.postal_code" maxlength="20" placeholder="100000" class="form-input" />
              </label>

              <label class="space-y-1.5 text-xs font-bold text-on-surface sm:col-span-2 lg:col-span-3">
                <span>Địa chỉ chi tiết (Số nhà, tên đường...) <span class="text-red-500">*</span></span>
                <input v-model.trim="addressForm.address_line" required maxlength="500" autocomplete="street-address" placeholder="Ví dụ: Số 12 ngõ 34 Phố Xuân Thủy..." class="form-input" />
              </label>
            </fieldset>

            <div class="flex justify-end gap-3 pt-2">
              <button
                type="submit"
                :disabled="addressBusy"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 font-bold text-on-primary shadow-xs transition hover:bg-primary-container disabled:opacity-50 cursor-pointer"
              >
                <span class="material-symbols-outlined text-lg">save</span>
                <span>{{ addressBusy ? 'Đang lưu...' : 'Lưu địa chỉ gửi hàng' }}</span>
              </button>
            </div>
          </form>
        </section>

        <!-- 2. DRAFT QUEUE SECTION -->
        <section class="space-y-6 pt-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-outline-variant/20 pb-4">
            <div>
              <h2 class="text-xl font-black text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">add_to_photos</span>
                Hàng đợi đăng sách cũ
              </h2>
              <p class="text-xs text-on-surface-variant mt-0.5">Bạn có thể chuẩn bị nhiều cuốn sách cùng lúc. Đăng tải nhiều dòng độc lập.</p>
            </div>

            <button
              v-if="drafts.length"
              type="button"
              :disabled="bulkBusy || !unsavedCount"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 font-bold text-white shadow-xs transition hover:bg-emerald-700 disabled:opacity-50 cursor-pointer"
              @click="submitAll"
            >
              <span class="material-symbols-outlined text-lg">cloud_upload</span>
              <span>{{ bulkBusy ? 'Đang đăng tải...' : `Đăng ${unsavedCount} cuốn sách chưa hoàn tất` }}</span>
            </button>
          </div>

          <!-- Empty Queue Placeholder -->
          <div v-if="!drafts.length" class="rounded-3xl border border-dashed border-outline-variant/40 bg-surface-container-lowest p-10 text-center space-y-3">
            <span class="material-symbols-outlined text-5xl text-outline">library_add</span>
            <h3 class="text-base font-bold text-on-surface">Hàng đợi đăng sách đang trống</h3>
            <p class="text-xs text-on-surface-variant">Bấm nút bên dưới để thêm thông tin cuốn sách cũ bạn muốn bán.</p>
            <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-5 font-bold text-on-primary shadow-xs hover:bg-primary-container cursor-pointer" @click="addDraft">
              <span class="material-symbols-outlined">add</span>
              <span>Thêm sách đầu tiên</span>
            </button>
          </div>

          <!-- Draft Item Forms -->
          <div v-else class="space-y-6">
            <form
              v-for="(draft, index) in drafts"
              :key="draft.clientId"
              class="rounded-3xl border bg-surface-container-lowest p-6 shadow-soft space-y-5 transition-all"
              :class="draft.state === 'error' ? 'border-red-300 ring-2 ring-red-100' : draft.state === 'saved' ? 'border-emerald-300 bg-emerald-50/20' : 'border-outline-variant/30'"
              @submit.prevent="submitDraft(draft)"
            >
              <!-- Draft Card Header -->
              <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/15 pb-4">
                <div class="flex items-center gap-3">
                  <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-sm font-black text-primary">{{ index + 1 }}</span>
                  <div>
                    <h3 class="font-bold text-on-surface text-base">{{ draft.title || `Cuốn sách số ${index + 1}` }}</h3>
                    <span
                      class="text-xs font-semibold"
                      :class="draft.state === 'error' ? 'text-red-600' : draft.state === 'saved' ? 'text-emerald-600' : 'text-on-surface-variant'"
                    >
                      {{ draft.state === 'saving' ? 'Đang lưu...' : draft.state === 'saved' ? `✓ Đã đăng thành công (ID #${draft.savedId})` : draft.state === 'error' ? '⚠ Cần bổ sung thông tin' : 'Bản nháp' }}
                    </span>
                  </div>
                </div>

                <button
                  type="button"
                  class="flex h-9 w-9 items-center justify-center rounded-xl text-outline hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer"
                  :title="draft.state === 'saved' ? 'Ẩn dòng đã đăng' : 'Xóa bản nháp'"
                  @click="removeDraft(draft)"
                >
                  <span class="material-symbols-outlined text-xl">{{ draft.state === 'saved' ? 'close' : 'delete' }}</span>
                </button>
              </div>

              <!-- General Error Message -->
              <p v-if="draft.errors.general" role="alert" class="rounded-xl bg-red-50 p-3 text-xs font-semibold text-red-700 border border-red-200">
                {{ draft.errors.general }}
              </p>

              <!-- Form Inputs -->
              <fieldset :disabled="draft.state === 'saving' || draft.state === 'saved'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="space-y-1.5 text-xs font-bold text-on-surface">
                  <span>Tên sách <span class="text-red-500">*</span></span>
                  <input v-model="draft.title" type="text" maxlength="255" placeholder="Tên sách ghi trên bìa..." class="form-input" />
                  <small v-if="draft.errors.title" class="block font-normal text-red-600">{{ draft.errors.title }}</small>
                </label>

                <label class="space-y-1.5 text-xs font-bold text-on-surface">
                  <span>Tác giả <span class="text-red-500">*</span></span>
                  <input v-model="draft.author_name" type="text" maxlength="255" placeholder="Tên tác giả..." class="form-input" />
                  <small v-if="draft.errors.author_name" class="block font-normal text-red-600">{{ draft.errors.author_name }}</small>
                </label>

                <label class="space-y-1.5 text-xs font-bold text-on-surface">
                  <span>Thể loại / Danh mục <span class="text-red-500">*</span></span>
                  <select v-model="draft.category_id" class="form-input cursor-pointer">
                    <option value="" disabled>-- Chọn danh mục --</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                  </select>
                  <small v-if="draft.errors.category_id" class="block font-normal text-red-600">{{ draft.errors.category_id }}</small>
                </label>

                <label class="space-y-1.5 text-xs font-bold text-on-surface">
                  <span>Tình trạng sách <span class="text-red-500">*</span></span>
                  <select v-model="draft.condition" class="form-input cursor-pointer">
                    <option v-for="opt in conditionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                </label>

                <label class="space-y-1.5 text-xs font-bold text-on-surface">
                  <span>Giá bánmong muốn (VND) <span class="text-red-500">*</span></span>
                  <input v-model.number="draft.price" type="number" min="1000" step="1000" class="form-input" />
                  <small v-if="draft.errors.price" class="block font-normal text-red-600">{{ draft.errors.price }}</small>
                </label>

                <label class="space-y-1.5 text-xs font-bold text-on-surface">
                  <span>Số lượng sách</span>
                  <input v-model.number="draft.quantity" type="number" min="1" max="100" class="form-input" />
                  <small v-if="draft.errors.quantity" class="block font-normal text-red-600">{{ draft.errors.quantity }}</small>
                </label>

                <label class="space-y-1.5 text-xs font-bold text-on-surface sm:col-span-2 lg:col-span-3">
                  <span>Mô tả ngắn về cuốn sách</span>
                  <textarea v-model="draft.description" rows="2" maxlength="5000" placeholder="Tóm tắt nội dung hoặc lý do nhượng lại..." class="form-input resize-y py-2.5"></textarea>
                </label>

                <label class="space-y-1.5 text-xs font-bold text-on-surface sm:col-span-2 lg:col-span-3">
                  <span>Vết sờn / Khuyết điểm chi tiết</span>
                  <textarea v-model="draft.defects" rows="2" maxlength="3000" placeholder="Mô tả thực tế: Gáy xước nhẹ, có chữ ký tên độc giả ở trang đầu..." class="form-input resize-y py-2.5"></textarea>
                </label>

                <!-- Photos Upload Section -->
                <div class="space-y-2 sm:col-span-2 lg:col-span-3">
                  <label class="block text-xs font-bold text-on-surface">
                    Ảnh chụp thực tế của sách (1 - 8 ảnh) <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="file"
                    accept="image/*"
                    multiple
                    class="form-input file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-primary hover:file:bg-primary/20 cursor-pointer"
                    @change="onPhotos(draft, $event)"
                  />
                  <small v-if="draft.errors.actual_photos" class="block font-normal text-red-600">{{ draft.errors.actual_photos }}</small>

                  <!-- Photo Preview Grid with Individual Remove Option -->
                  <div v-if="draft.photoPreviews.length" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 pt-2">
                    <figure
                      v-for="(photo, photoIdx) in draft.photoPreviews"
                      :key="photo.url"
                      class="group relative overflow-hidden rounded-xl border border-outline-variant/30 bg-surface-container-low"
                    >
                      <img :src="photo.url" :alt="photo.alt" class="aspect-square w-full object-cover" />
                      <button
                        type="button"
                        class="absolute top-1 right-1 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-white shadow-xs opacity-80 hover:opacity-100 transition-opacity cursor-pointer"
                        title="Xóa ảnh này"
                        @click="removeSinglePhoto(draft, photoIdx)"
                      >
                        <span class="material-symbols-outlined text-sm">close</span>
                      </button>
                      <figcaption class="truncate p-1.5 text-[10px] text-on-surface-variant text-center bg-surface-container-lowest/80">{{ photo.name }}</figcaption>
                    </figure>
                  </div>
                </div>

                <!-- Authenticity Attestation -->
                <label class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50/60 p-4 text-xs text-amber-950 sm:col-span-2 lg:col-span-3 cursor-pointer">
                  <input v-model="draft.authenticity_attested" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-amber-400 text-primary focus:ring-primary" />
                  <span class="leading-relaxed">
                    <strong>Cam kết sách thật:</strong> Tôi xác nhận cuốn sách cũ trên là sách chính hãng/sách thật và chịu trách nhiệm hoàn toàn nếu phát sinh khiếu nại sách giả.
                    <small v-if="draft.errors.authenticity_attested" class="block font-bold text-red-600 mt-1">{{ draft.errors.authenticity_attested }}</small>
                  </span>
                </label>
              </fieldset>

              <!-- Card Action Button -->
              <div class="flex justify-end pt-2 border-t border-outline-variant/15">
                <button
                  type="submit"
                  :disabled="draft.state === 'saving' || draft.state === 'saved'"
                  class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 font-bold text-on-primary shadow-xs transition hover:bg-primary-container disabled:opacity-50 cursor-pointer"
                >
                  <span class="material-symbols-outlined text-lg">{{ draft.state === 'saved' ? 'check_circle' : 'publish' }}</span>
                  <span>{{ draft.state === 'saving' ? 'Đang lưu...' : draft.state === 'saved' ? 'Đã đăng tải' : 'Đăng bán cuốn này' }}</span>
                </button>
              </div>
            </form>
          </div>
        </section>

        <!-- 3. PUBLISHED LISTINGS SECTION -->
        <section class="space-y-6 pt-8 border-t border-outline-variant/20">
          <div>
            <h2 class="text-xl font-black text-on-surface flex items-center gap-2">
              <span class="material-symbols-outlined text-emerald-600">inventory</span>
              Danh sách sách cũ đã đăng
            </h2>
            <p class="text-xs text-on-surface-variant mt-0.5">Quản lý tồn kho thực tế, số lượng đã đặt giữ, số lượng đã bán và cập nhật trạng thái.</p>
          </div>

          <div v-if="!listings.length" class="rounded-3xl border border-dashed border-outline-variant/40 bg-surface-container-lowest p-10 text-center text-on-surface-variant space-y-2">
            <span class="material-symbols-outlined text-4xl text-outline">inventory_2</span>
            <p class="text-sm font-semibold">Chưa có cuốn sách cũ nào được đăng công khai.</p>
          </div>

          <div v-else class="grid gap-4 md:grid-cols-2">
            <article
              v-for="listing in listings"
              :key="listing.id"
              class="flex flex-col sm:flex-row gap-4 rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-soft"
            >
              <!-- Cover Image -->
              <div class="h-32 w-24 shrink-0 overflow-hidden rounded-2xl bg-surface-container-low border border-outline-variant/20 flex items-center justify-center mx-auto sm:mx-0">
                <img v-if="listing.book?.cover_image" :src="listing.book.cover_image" :alt="`Bìa ${listing.book.title}`" class="h-full w-full object-contain" />
                <span v-else class="material-symbols-outlined text-3xl text-outline">menu_book</span>
              </div>

              <!-- Listing Meta & Inventory Control -->
              <div class="flex-1 min-w-0 space-y-3">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <h3 class="font-bold text-on-surface text-base truncate">{{ listing.book?.title || `Listing #${listing.id}` }}</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">
                      {{ listing.book?.author }} · <span class="font-bold text-primary">{{ money(listing.book?.price) }}</span>
                    </p>
                  </div>
                  <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase transition-colors" :class="statusBadgeClass(listing.status)">
                    {{ statusLabel(listing.status) }}
                  </span>
                </div>

                <!-- Rejection Reason Alert if rejected -->
                <div v-if="listing.status === 'rejected' && listing.rejection_reason" class="rounded-xl border border-red-200 bg-red-50 p-2.5 text-xs text-red-800 space-y-0.5">
                  <strong class="font-bold flex items-center gap-1"><span class="material-symbols-outlined text-sm">info</span> Lý do Admin từ chối:</strong>
                  <p class="leading-relaxed text-[11px]">{{ listing.rejection_reason }}</p>
                </div>

                <!-- Tình trạng sách badge -->
                <div class="inline-flex items-center gap-1 rounded-md bg-surface-container-high px-2 py-0.5 text-xs text-on-surface-variant font-medium">
                  <span class="material-symbols-outlined text-sm text-emerald-600">verified</span>
                  <span>{{ conditionLabel(listing.condition) }}</span>
                </div>

                <!-- Stats Counters -->
                <div class="grid grid-cols-4 gap-2 rounded-xl bg-surface-container-low p-2.5 text-center text-xs">
                  <div><span class="text-on-surface-variant text-[10px] block">Khả dụng</span><strong class="text-on-surface">{{ listing.quantity_available }}</strong></div>
                  <div><span class="text-on-surface-variant text-[10px] block">Đã giữ</span><strong class="text-amber-700">{{ listing.quantity_reserved }}</strong></div>
                  <div><span class="text-on-surface-variant text-[10px] block">Đã bán</span><strong class="text-emerald-700">{{ listing.quantity_sold }}</strong></div>
                  <div><span class="text-on-surface-variant text-[10px] block">Đã trả</span><strong class="text-red-600">{{ listing.quantity_returned }}</strong></div>
                </div>

                <!-- Update Inventory -->
                <div class="flex items-center gap-2 pt-1">
                  <input v-model.number="inventoryValues[listing.id]" type="number" min="0" max="100" class="form-input !h-9 text-xs !py-1 text-center font-bold" />
                  <button
                    type="button"
                    :disabled="inventoryBusy === listing.id"
                    class="inline-flex min-h-9 items-center justify-center rounded-xl border border-primary px-3 text-xs font-bold text-primary hover:bg-primary/5 disabled:opacity-50 cursor-pointer whitespace-nowrap"
                    @click="saveInventory(listing)"
                  >
                    {{ inventoryBusy === listing.id ? 'Đang lưu...' : 'Lưu số lượng' }}
                  </button>
                </div>
              </div>
            </article>
          </div>
        </section>
        </template>

        <!-- TAB 2: USED BOOK SELLER ORDERS -->
        <template v-if="activeTab === 'orders'">
          <div class="space-y-4">
            <!-- Filter Sub-bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-outline-variant/15">
              <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
                <button 
                  v-for="st in [
                    { key: 'all', label: 'Tất cả đơn' },
                    { key: 'processing', label: 'Chờ gửi hàng 🚚' },
                    { key: 'shipped', label: 'Đang giao 📦' },
                    { key: 'completed', label: 'Hoàn tất 🎉' },
                    { key: 'cancelled', label: 'Đã hủy' }
                  ]" 
                  :key="st.key"
                  type="button"
                  @click="orderFilterStatus = st.key; fetchSellerOrders()"
                  :class="['px-3.5 py-1.5 rounded-xl text-xs font-bold cursor-pointer transition-all border', orderFilterStatus === st.key ? 'bg-primary text-on-primary border-primary shadow-xs' : 'bg-surface-container-low text-on-surface-variant border-outline-variant/20 hover:border-outline-variant/60']"
                >
                  {{ st.label }}
                </button>
              </div>

              <button type="button" @click="fetchSellerOrders" class="inline-flex items-center gap-1 text-xs font-bold text-outline hover:text-primary cursor-pointer border-none bg-transparent">
                <span class="material-symbols-outlined text-sm">refresh</span> Làm mới
              </button>
            </div>

            <!-- Loading State -->
            <div v-if="loadingOrders" class="py-12 text-center text-outline">
              <span class="material-symbols-outlined text-2xl text-primary animate-spin mb-2">progress_activity</span>
              <p class="text-xs font-bold">Đang tải danh sách đơn bán sách cũ...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="!sellerOrders.length" class="p-12 text-center bg-surface-container-low/20 rounded-2xl border border-outline-variant/20">
              <span class="material-symbols-outlined text-4xl text-outline mb-2">package_2</span>
              <h3 class="text-base font-bold text-on-surface">Chưa có đơn hàng nào</h3>
              <p class="text-xs text-on-surface-variant max-w-sm mx-auto mt-1">Khi độc giả đặt mua sách cũ của bạn, đơn hàng và thông tin giao hàng sẽ xuất hiện ở đây.</p>
            </div>

            <!-- Orders List -->
            <div v-else class="space-y-4">
              <div v-for="order in sellerOrders" :key="order.id" class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-xs space-y-4">
                <!-- Order Header -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/15 pb-3">
                  <div class="flex items-center gap-2">
                    <span class="font-black text-sm text-on-surface">Đơn bán #{{ order.order_code }}</span>
                    <span class="text-xs text-outline font-medium">({{ order.created_at ? new Date(order.created_at).toLocaleString('vi-VN') : '' }})</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="statusBadgeClass(order.status)">
                      {{ statusLabel(order.status) }}
                    </span>
                    <span v-if="order.shipping_status" class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                      {{ { pending_pickup: 'Chờ bàn giao ĐVVC', picked_up: 'Đã lấy hàng', delivering: 'Đang vận chuyển', awaiting_customer_confirmation: 'Đang chờ nhận', delivered: 'Đã giao' }[order.shipping_status] || order.shipping_status }}
                    </span>
                  </div>
                </div>

                <!-- Items List -->
                <div class="space-y-2">
                  <div v-for="item in order.items" :key="item.id" class="flex items-center gap-3">
                    <img v-if="item.cover_image" :src="item.cover_image" class="w-12 h-16 object-contain rounded border bg-surface-container" />
                    <div v-else class="w-12 h-16 bg-surface-container flex items-center justify-center rounded"><span class="material-symbols-outlined text-outline">book</span></div>
                    <div class="flex-1 min-w-0">
                      <h4 class="font-bold text-sm text-on-surface truncate">{{ item.title }}</h4>
                      <div class="text-xs text-on-surface-variant flex items-center gap-2">
                        <span>Tình trạng: {{ conditionLabel(item.condition) }}</span>
                        <span>•</span>
                        <span>Số lượng: {{ item.quantity }}</span>
                      </div>
                    </div>
                    <div class="text-right font-bold text-sm text-primary">
                      {{ money(item.price * item.quantity) }}
                    </div>
                  </div>
                </div>

                <!-- Buyer Shipping Address Box -->
                <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-xs space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-primary flex items-center gap-1.5">
                      <span class="material-symbols-outlined text-base">local_shipping</span>
                      Thông tin người nhận & Địa chỉ giao hàng (Secret Buyer Snapshot):
                    </span>
                    <button @click="copyToClipboard(`${order.buyer.name} - ${order.buyer.phone}\n${order.buyer.shipping_address}`)" type="button" class="text-[11px] font-bold text-primary hover:underline border-none bg-transparent cursor-pointer flex items-center gap-1">
                      <span class="material-symbols-outlined text-sm">content_copy</span> Sao chép địa chỉ
                    </button>
                  </div>
                  <div class="font-bold text-on-surface text-sm">{{ order.buyer.name }} ({{ order.buyer.phone }})</div>
                  <div class="text-on-surface-variant font-medium leading-relaxed">{{ order.buyer.shipping_address }}</div>
                  <div v-if="order.shipping_carrier || order.shipping_tracking_code" class="pt-2 border-t border-primary/10 flex flex-wrap gap-4 text-on-surface-variant font-bold">
                    <span>Hãng Vận Chuyển: <span class="text-on-surface">{{ order.shipping_carrier || 'KomiExpress C2C' }}</span></span>
                    <span>Mã Vận Đơn: <span class="font-mono text-primary">{{ order.shipping_tracking_code || 'Chưa gán' }}</span></span>
                  </div>
                </div>

                <!-- 5-Step Journey Progress Indicator -->
                <div class="rounded-xl border border-outline-variant/20 bg-surface-container-low/40 p-3 space-y-2">
                  <div class="text-[11px] font-bold text-on-surface-variant flex items-center justify-between">
                    <span>Hành trình giao hàng sách cũ:</span>
                    <span class="text-primary font-black uppercase">
                      {{ {
                        processing: 'Bước 1: Chờ đóng gói & gán mã vận đơn',
                        pending_pickup: 'Bước 1: Đã đóng gói - Chờ ĐVVC lấy hàng',
                        picked_up: 'Bước 2: ĐVVC đã lấy hàng',
                        delivering: 'Bước 3: Đang vận chuyển tới người mua',
                        awaiting_customer_confirmation: 'Bước 4: Đã tới nơi - Chờ người mua kiểm tra',
                        delivered: 'Bước 5: Hoàn tất & Đã nhận tiền Ví KomiBook'
                      }[order.status === 'completed' ? 'delivered' : (order.shipping_status || order.status)] || 'Đang xử lý' }}
                    </span>
                  </div>

                  <div class="grid grid-cols-5 gap-1 pt-1 text-center text-[10px] font-bold">
                    <div :class="['py-1.5 px-1 rounded-lg border transition-all', order.status !== 'pending' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-surface-container text-outline border-transparent']">
                      📦 1. Đóng gói
                    </div>
                    <div :class="['py-1.5 px-1 rounded-lg border transition-all', ['picked_up', 'delivering', 'awaiting_customer_confirmation', 'delivered'].includes(order.shipping_status) || order.status === 'completed' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : (order.shipping_status === 'pending_pickup' ? 'bg-amber-50 text-amber-800 border-amber-300 animate-pulse' : 'bg-surface-container text-outline border-transparent')]">
                      🚛 2. ĐVVC lấy
                    </div>
                    <div :class="['py-1.5 px-1 rounded-lg border transition-all', ['delivering', 'awaiting_customer_confirmation', 'delivered'].includes(order.shipping_status) || order.status === 'completed' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : (order.shipping_status === 'picked_up' ? 'bg-amber-50 text-amber-800 border-amber-300 animate-pulse' : 'bg-surface-container text-outline border-transparent')]">
                      🚚 3. Đang giao
                    </div>
                    <div :class="['py-1.5 px-1 rounded-lg border transition-all', ['awaiting_customer_confirmation', 'delivered'].includes(order.shipping_status) || order.status === 'completed' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : (order.shipping_status === 'delivering' ? 'bg-amber-50 text-amber-800 border-amber-300 animate-pulse' : 'bg-surface-container text-outline border-transparent')]">
                      📍 4. Đã tới nơi
                    </div>
                    <div :class="['py-1.5 px-1 rounded-lg border transition-all', order.status === 'completed' ? 'bg-emerald-600 text-white border-emerald-700 shadow-xs' : 'bg-surface-container text-outline border-transparent']">
                      💰 5. Hoàn tất Ví
                    </div>
                  </div>
                </div>

                <!-- Footer & Action Buttons -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-outline-variant/15">
                  <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-3 text-xs text-on-surface-variant font-semibold">
                      <span>Giá sách: <strong class="text-on-surface">{{ money(order.item_subtotal || order.total_amount) }}</strong></span>
                      <span>•</span>
                      <span>Phí ship (ĐVVC): <strong class="text-on-surface">{{ money(order.shipping_fee) }}</strong></span>
                      <span>•</span>
                      <span>Phí sàn (10%): <strong class="text-red-600">-{{ money(order.commission_amount) }}</strong></span>
                    </div>
                    <div class="text-xs">
                      <span class="text-outline font-bold">Doanh thu người bán thực nhận (Ví KomiBook): </span>
                      <span class="text-lg font-black text-emerald-700">{{ money(order.net_earning || (order.item_subtotal ? order.item_subtotal * 0.9 : order.total_amount * 0.9)) }}</span>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center gap-2">
                    <!-- Step 1 Button: Open Ship Modal -->
                    <button 
                      v-if="order.status === 'processing' || order.status === 'confirmed'"
                      @click="openShipModal(order)"
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-on-primary shadow-xs hover:bg-primary/90 transition-all border-none cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-base">local_shipping</span>
                      <span>Bước 1: Xác nhận đóng gói & Gán mã vận đơn</span>
                    </button>

                    <!-- Step 2 Button: Shipper Picked Up -->
                    <button 
                      v-else-if="order.status === 'shipped' && order.shipping_status === 'pending_pickup'"
                      @click="advanceStep(order)"
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-amber-700 transition-all border-none cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-base">front_hand</span>
                      <span>Bước 2: Xác nhận ĐVVC đã lấy hàng</span>
                    </button>

                    <!-- Step 3 Button: In Transit -->
                    <button 
                      v-else-if="order.status === 'shipped' && order.shipping_status === 'picked_up'"
                      @click="advanceStep(order)"
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition-all border-none cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-base">local_shipping</span>
                      <span>Bước 3: Cập nhật đang vận chuyển giao tới khách</span>
                    </button>

                    <!-- Step 4 Button: Arrived At Location -->
                    <button 
                      v-else-if="order.status === 'shipped' && order.shipping_status === 'delivering'"
                      @click="advanceStep(order)"
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-purple-700 transition-all border-none cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-base">pin_drop</span>
                      <span>Bước 4: Cập nhật đã giao tới nơi (Chờ nhận)</span>
                    </button>

                    <!-- Step 5 Button: Finalize Delivery & Credit Wallet -->
                    <button 
                      v-else-if="order.status === 'shipped' && order.shipping_status === 'awaiting_customer_confirmation'"
                      @click="confirmDelivery(order)"
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition-all border-none cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-base">check_circle</span>
                      <span>Bước 5: Xác nhận Hoàn tất & Giải ngân Ví KomiBook</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- TAB 3: WALLET & EARNINGS -->
        <template v-if="activeTab === 'wallet'">
          <div class="space-y-6">
            <!-- Balance Card -->
            <div class="rounded-3xl bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white shadow-elevated flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div class="space-y-1">
                <span class="text-xs font-bold text-emerald-100 uppercase tracking-wider block">Ví KomiBook - Doanh Thu Bán Sách Cũ</span>
                <h3 class="text-3xl font-black">{{ money(walletData.balance) }}</h3>
                <p class="text-xs text-emerald-100">Số dư khả dụng có thể rút hoặc sử dụng mua sách khác trên KomiBook</p>
              </div>

              <div class="flex items-center gap-3">
                <router-link to="/wallet" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-xs font-bold text-emerald-700 shadow-xs hover:bg-slate-100 no-underline">
                  <span class="material-symbols-outlined text-base">payments</span>
                  <span>Rút tiền / Lịch sử Ví</span>
                </router-link>
              </div>
            </div>

            <!-- Transaction Entries List -->
            <div class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5 space-y-4">
              <h3 class="font-bold text-base text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">history</span>
                <span>Lịch Sử Ghi Nhận Doanh Thu Bán Sách</span>
              </h3>

              <div v-if="!walletData.entries.length" class="text-center py-8 text-xs text-outline">
                Chưa có biến động số dư nào từ bán sách cũ.
              </div>

              <div v-else class="divide-y divide-outline-variant/15">
                <div v-for="entry in walletData.entries" :key="entry.id" class="py-3 flex items-center justify-between gap-3 text-xs">
                  <div class="space-y-0.5">
                    <span class="font-bold text-on-surface block">{{ entry.entry_type === 'credit' ? '➕ Thu nhập từ bán sách cũ' : '➖ Trừ số dư' }}</span>
                    <span class="text-outline text-[11px] block">{{ entry.created_at ? new Date(entry.created_at).toLocaleString('vi-VN') : '' }}</span>
                  </div>
                  <div class="text-right">
                    <span class="font-black text-sm block" :class="entry.entry_type === 'credit' ? 'text-emerald-600' : 'text-red-600'">
                      {{ entry.entry_type === 'credit' ? '+' : '-' }}{{ money(entry.amount) }}
                    </span>
                    <span class="text-[10px] text-outline">Số dư sau: {{ money(entry.balance_after) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- SHIPPING MODAL -->
        <div v-if="showShipModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
              <h3 class="font-bold text-base text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">local_shipping</span>
                <span>Xác Nhận Gửi Hàng Cho ĐVVC</span>
              </h3>
              <button @click="showShipModal = false" type="button" class="text-outline hover:text-on-surface border-none bg-transparent cursor-pointer">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>

            <p class="text-xs text-on-surface-variant leading-relaxed">
              Vui lòng nhập tên Đơn vị vận chuyển và Mã vận đơn sau khi bạn đã giao gói sách cũ cho nhân viên lấy hàng.
            </p>

            <form @submit.prevent="submitShipOrder" class="space-y-4">
              <label class="space-y-1.5 text-xs font-bold text-on-surface block">
                <span>Hãng Vận Chuyển <span class="text-red-500">*</span></span>
                <input v-model.trim="shipForm.shipping_carrier" required placeholder="Ví dụ: KomiExpress C2C, Giao Hàng Nhanh..." class="form-input" />
              </label>

              <label class="space-y-1.5 text-xs font-bold text-on-surface block">
                <span>Mã Vận Đơn <span class="text-red-500">*</span></span>
                <input v-model.trim="shipForm.shipping_tracking_code" required placeholder="Ví dụ: KM-C2C-123456..." class="form-input font-mono" />
              </label>

              <div class="flex items-center justify-end gap-2 pt-2">
                <button @click="showShipModal = false" type="button" class="rounded-xl px-4 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container border-none cursor-pointer">
                  Hủy
                </button>
                <button type="submit" :disabled="shippingBusy" class="rounded-xl bg-primary px-5 py-2 text-xs font-bold text-on-primary shadow-xs hover:bg-primary/90 border-none cursor-pointer">
                  {{ shippingBusy ? 'Đang cập nhật...' : 'Xác Nhận Đã Gửi' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
</div>
</template>

<style scoped>
.form-input {
  min-height: 44px;
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid var(--color-outline-variant, #e2e8f0);
  background: var(--color-surface, #ffffff);
  padding: 0.65rem 0.85rem;
  font-size: 0.875rem;
  color: var(--color-on-surface, #0f172a);
  transition: all 0.2s ease;
}

.form-input:focus {
  border-color: var(--color-primary, #00b14f);
  outline: 3px solid color-mix(in srgb, var(--color-primary, #00b14f) 18%, transparent);
}

button:not([tabindex="-1"]) {
  min-height: 44px;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>
