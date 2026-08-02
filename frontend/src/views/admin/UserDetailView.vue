<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const router = useRouter()
const userId = computed(() => route.params.id)
const loading = ref(true)
const error = ref(null)
const user = ref(null)

const recentOrders = computed(() => user.value?.orders || [])
const organizationMemberships = computed(() => user.value?.organization_memberships || [])
const warehouseAssignments = computed(() => user.value?.warehouse_manager_assignments || [])

const getInitials = (name) => {
  if (!name) return '??'
  const parts = name.trim().split(/\s+/)
  return `${parts[0]?.[0] || ''}${parts.at(-1)?.[0] || ''}`.toUpperCase()
}

const formatVND = (value) => new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: 'VND',
  maximumFractionDigits: 0,
}).format(Number(value) || 0)

const formatDate = (value, includeTime = false) => {
  if (!value) return 'Chưa có dữ liệu'
  return new Intl.DateTimeFormat('vi-VN', includeTime
    ? { dateStyle: 'short', timeStyle: 'short' }
    : { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

const roleLabel = (role) => ({ admin: 'Quản trị viên', vendor: 'Nhà bán', customer: 'Khách hàng' })[role] || role || 'Chưa xác định'
const genderLabel = (gender) => ({ male: 'Nam', female: 'Nữ', other: 'Khác' })[gender] || gender || 'Chưa khai báo'
const statusLabel = (status) => ({
  pending: 'Chờ xử lý', processing: 'Đang xử lý', shipped: 'Đang giao', completed: 'Hoàn thành', cancelled: 'Đã hủy',
})[status] || status || 'Chưa xác định'
const statusClass = (status) => status === 'completed'
  ? 'bg-emerald-100 text-emerald-800'
  : status === 'cancelled'
    ? 'bg-red-100 text-red-800'
    : 'bg-amber-100 text-amber-900'
const capabilityLabel = (capability) => ({
  view_inventory: 'Xem tồn kho',
  manage_inventory: 'Quản lý tồn kho',
  create_documents: 'Tạo phiếu kho',
  approve_documents: 'Duyệt phiếu kho',
  post_documents: 'Ghi sổ phiếu kho',
})[capability] || capability

const fetchUser = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await apiClient.get(`/api/admin/users/${userId.value}`)
    user.value = response.data?.data || null
  } catch (exception) {
    user.value = null
    error.value = exception.response?.data?.message || 'Không thể kết nối API thông tin người dùng.'
  } finally {
    loading.value = false
  }
}

const openPrintSheet = () => {
  const href = router.resolve({ name: 'admin-user-print', params: { id: userId.value } }).href
  globalThis.open(href, '_blank', 'noopener,noreferrer')
}

onMounted(fetchUser)
</script>

<template>
  <main id="main-content" class="w-full space-y-6 pb-10 pt-6" tabindex="-1">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="min-w-0">
        <button type="button" class="mb-2 inline-flex min-h-11 items-center gap-1 text-sm font-bold text-primary" @click="router.push({ name: 'admin-users' })">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
          Quản lý người dùng
        </button>
        <h1 class="truncate text-3xl font-bold text-on-surface">{{ user?.name || 'Chi tiết người dùng' }}</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Hồ sơ, hoạt động mua hàng và các quyền vận hành đang liên kết.</p>
      </div>
      <button v-if="user" type="button" class="ui-btn ui-btn-primary min-h-11 shrink-0" @click="openPrintSheet">
        <span class="material-symbols-outlined text-[19px]" aria-hidden="true">print</span>
        Xuất phiếu thông tin
      </button>
    </header>

    <section v-if="loading" class="flex min-h-64 items-center justify-center rounded-xl bg-surface-container-lowest" role="status">
      <i class="pi pi-spin pi-spinner text-4xl text-primary" aria-hidden="true"></i><span class="sr-only">Đang tải hồ sơ</span>
    </section>
    <section v-else-if="error" class="rounded-xl border border-error/30 bg-error-container p-8 text-center text-on-error-container" role="alert">
      <h2 class="text-xl font-bold">Không thể tải hồ sơ người dùng</h2>
      <p class="mt-2">{{ error }}</p>
      <button type="button" class="ui-btn ui-btn-primary mt-4 min-h-11" @click="fetchUser">Thử lại</button>
    </section>

    <template v-else-if="user">
      <div class="grid gap-6 xl:grid-cols-[minmax(280px,360px)_1fr]">
        <aside class="space-y-6">
          <section class="overflow-hidden rounded-xl border border-outline-variant/40 bg-surface-container-lowest">
            <div class="h-20 bg-primary"></div>
            <div class="-mt-10 px-6 pb-6 text-center">
              <div class="mx-auto grid h-20 w-20 place-items-center rounded-full border-4 border-surface bg-primary-container text-2xl font-black text-on-primary-container shadow-sm">{{ getInitials(user.name) }}</div>
              <h2 class="mt-3 text-xl font-bold">{{ user.name }}</h2>
              <p class="mt-1 break-all text-sm text-on-surface-variant">{{ user.email || 'Chưa có email' }}</p>
              <div class="mt-4 flex flex-wrap justify-center gap-2">
                <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ roleLabel(user.role) }}</span>
                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="user.email_verified_at ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'">{{ user.email_verified_at ? 'Email đã xác minh' : 'Email chưa xác minh' }}</span>
              </div>
            </div>
            <dl class="grid gap-4 border-t border-outline-variant/30 px-6 py-5 text-sm">
              <div><dt class="text-xs font-bold uppercase tracking-wider text-outline">Số điện thoại</dt><dd class="mt-1 font-medium">{{ user.phone || 'Chưa khai báo' }}</dd></div>
              <div class="grid grid-cols-2 gap-4"><div><dt class="text-xs font-bold uppercase tracking-wider text-outline">Giới tính</dt><dd class="mt-1 font-medium">{{ genderLabel(user.gender) }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wider text-outline">Ngày sinh</dt><dd class="mt-1 font-medium">{{ formatDate(user.birthday) }}</dd></div></div>
              <div><dt class="text-xs font-bold uppercase tracking-wider text-outline">Ngày tham gia</dt><dd class="mt-1 font-medium">{{ formatDate(user.created_at, true) }}</dd></div>
              <div><dt class="text-xs font-bold uppercase tracking-wider text-outline">Hoạt động gần nhất</dt><dd class="mt-1 font-medium">{{ formatDate(user.last_login_at, true) }}</dd></div>
            </dl>
          </section>

          <section class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-5">
            <h2 class="text-lg font-bold">Sổ địa chỉ</h2>
            <div v-if="user.addresses?.length" class="mt-4 space-y-3">
              <article v-for="address in user.addresses" :key="address.id" class="rounded-lg bg-surface-container p-3 text-sm">
                <div class="flex items-center justify-between gap-2"><strong>{{ address.receiver_name }}</strong><span v-if="address.is_default" class="text-xs font-bold text-primary">Mặc định</span></div>
                <p class="mt-1 text-on-surface-variant">{{ address.phone }}</p><p class="mt-1">{{ address.address }}</p>
              </article>
            </div>
            <p v-else class="mt-3 text-sm text-on-surface-variant">Chưa lưu địa chỉ nhận hàng.</p>
          </section>
        </aside>

        <div class="space-y-6">
          <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <article class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4"><p class="text-sm text-on-surface-variant">Tổng đơn hàng</p><strong class="mt-2 block text-2xl">{{ user.total_orders || 0 }}</strong></article>
            <article class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4"><p class="text-sm text-on-surface-variant">Tổng chi tiêu</p><strong class="mt-2 block text-xl">{{ formatVND(user.total_spent) }}</strong></article>
            <article class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4"><p class="text-sm text-on-surface-variant">Sách đã mua</p><strong class="mt-2 block text-2xl">{{ user.purchased_books_count || 0 }}</strong></article>
            <article class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4"><p class="text-sm text-on-surface-variant">Đánh giá / Yêu thích</p><strong class="mt-2 block text-2xl">{{ user.reviews_count || 0 }} / {{ user.wishlist_count || 0 }}</strong></article>
          </section>

          <section class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-5">
            <h2 class="text-lg font-bold">Vai trò và đơn vị liên kết</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
              <article v-if="user.vendor_info" class="rounded-xl bg-surface-container p-4 text-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-primary">Hồ sơ nhà bán</p><h3 class="mt-2 text-base font-bold">{{ user.vendor_info.shop_name }}</h3>
                <dl class="mt-3 grid gap-2"><div><dt class="inline text-on-surface-variant">Trạng thái: </dt><dd class="inline font-semibold">{{ user.vendor_info.status }}</dd></div><div><dt class="inline text-on-surface-variant">Kho tổng: </dt><dd class="inline font-semibold">{{ user.vendor_info.primary_warehouse?.name || 'Chưa đăng ký' }}</dd></div><div><dt class="inline text-on-surface-variant">Tổ chức chính: </dt><dd class="inline font-semibold">{{ user.vendor_info.primary_organization?.display_name || user.vendor_info.primary_organization?.legal_name || 'Chưa liên kết' }}</dd></div></dl>
              </article>
              <article v-for="membership in organizationMemberships" :key="`org-${membership.id}`" class="rounded-xl bg-surface-container p-4 text-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-primary">Thành viên tổ chức</p><h3 class="mt-2 text-base font-bold">{{ membership.organization?.display_name || membership.organization?.legal_name }}</h3><p class="mt-2 text-on-surface-variant">Vai trò: <strong class="text-on-surface">{{ membership.role }}</strong> · {{ membership.status }}</p>
              </article>
              <article v-for="assignment in warehouseAssignments" :key="`warehouse-${assignment.id}`" class="rounded-xl bg-surface-container p-4 text-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-primary">Nhân sự kho</p><h3 class="mt-2 text-base font-bold">{{ assignment.warehouse?.name }}</h3><p class="mt-1 text-on-surface-variant">{{ assignment.vendor?.shop_name }} · {{ assignment.status }}</p><div class="mt-3 flex flex-wrap gap-2"><span v-for="capability in assignment.capabilities || []" :key="capability" class="rounded-full bg-surface-container-high px-2.5 py-1 text-xs font-medium">{{ capabilityLabel(capability) }}</span></div>
              </article>
              <article v-if="!user.vendor_info && !organizationMemberships.length && !warehouseAssignments.length" class="rounded-xl bg-surface-container p-4 text-sm text-on-surface-variant lg:col-span-2">Tài khoản chưa liên kết hồ sơ nhà bán, tổ chức hoặc nhiệm vụ kho.</article>
            </div>
          </section>

          <section class="overflow-hidden rounded-xl border border-outline-variant/40 bg-surface-container-lowest">
            <div class="border-b border-outline-variant/30 px-5 py-4"><h2 class="text-lg font-bold">Đơn hàng gần đây</h2></div>
            <div class="overflow-x-auto" role="region" aria-label="Đơn hàng gần đây" tabindex="0">
              <table class="w-full min-w-[680px] text-left text-sm"><thead class="bg-surface-container text-on-surface-variant"><tr><th class="px-5 py-3">Mã đơn</th><th class="px-5 py-3">Gian hàng</th><th class="px-5 py-3">Ngày đặt</th><th class="px-5 py-3 text-right">Tổng tiền</th><th class="px-5 py-3">Trạng thái</th></tr></thead><tbody class="divide-y divide-outline-variant/30"><tr v-for="order in recentOrders" :key="order.id"><td class="px-5 py-3 font-bold text-primary">{{ order.order_code }}</td><td class="px-5 py-3">{{ order.vendor?.shop_name || '—' }}</td><td class="px-5 py-3">{{ formatDate(order.created_at) }}</td><td class="px-5 py-3 text-right font-semibold">{{ formatVND(order.total_amount) }}</td><td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(order.status)">{{ statusLabel(order.status) }}</span></td></tr><tr v-if="!recentOrders.length"><td colspan="5" class="px-5 py-10 text-center text-on-surface-variant">Chưa có đơn hàng.</td></tr></tbody></table>
            </div>
          </section>
        </div>
      </div>
    </template>
  </main>
</template>
