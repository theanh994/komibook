<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const user = ref(null)
const generatedAt = new Date()
const roleLabel = (role) => ({ admin: 'Quản trị viên', vendor: 'Nhà bán', customer: 'Khách hàng' })[role] || role || 'Chưa xác định'
const formatDate = (value) => value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—'
const formatVND = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value) || 0)
const addresses = computed(() => user.value?.addresses || [])

const load = async () => {
  try {
    const response = await apiClient.get(`/api/admin/users/${route.params.id}`)
    user.value = response.data?.data || null
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Không thể tải phiếu thông tin.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="print-page mx-auto my-6 max-w-[210mm] bg-white p-8 text-slate-900 shadow-sm">
    <div class="print-actions mb-6 flex justify-between gap-3"><router-link :to="{ name: 'admin-user-detail', params: { id: route.params.id } }" class="ui-btn ui-btn-secondary">Quay lại hồ sơ</router-link><button type="button" class="ui-btn ui-btn-primary" @click="globalThis.print()"><span class="material-symbols-outlined text-[18px]">print</span>In phiếu</button></div>
    <p v-if="loading" class="py-20 text-center">Đang chuẩn bị phiếu…</p><p v-else-if="error" class="py-20 text-center text-red-700">{{ error }}</p>
    <article v-else-if="user">
      <header class="border-b-2 border-slate-900 pb-5 text-center"><h1 class="text-2xl font-black uppercase">Phiếu thông tin người dùng</h1><p class="mt-2 text-sm">KomiBook · Mã hồ sơ #{{ user.id }}</p></header>
      <section class="mt-6 grid grid-cols-2 gap-x-8 gap-y-3 text-sm"><p><strong>Họ và tên:</strong> {{ user.name }}</p><p><strong>Vai trò:</strong> {{ roleLabel(user.role) }}</p><p><strong>Email:</strong> {{ user.email || '—' }}</p><p><strong>Xác minh email:</strong> {{ user.email_verified_at ? formatDate(user.email_verified_at) : 'Chưa xác minh' }}</p><p><strong>Số điện thoại:</strong> {{ user.phone || '—' }}</p><p><strong>Ngày sinh:</strong> {{ user.birthday || '—' }}</p><p><strong>Ngày tham gia:</strong> {{ formatDate(user.created_at) }}</p><p><strong>Hoạt động gần nhất:</strong> {{ formatDate(user.last_login_at) }}</p></section>
      <section class="mt-7"><h2 class="border-b border-slate-400 pb-2 text-base font-bold uppercase">Thống kê tài khoản</h2><div class="mt-3 grid grid-cols-4 gap-3 text-center text-sm"><div class="border border-slate-300 p-3"><strong class="block text-xl">{{ user.total_orders || 0 }}</strong>Đơn hàng</div><div class="border border-slate-300 p-3"><strong class="block text-base">{{ formatVND(user.total_spent) }}</strong>Chi tiêu</div><div class="border border-slate-300 p-3"><strong class="block text-xl">{{ user.purchased_books_count || 0 }}</strong>Sách đã mua</div><div class="border border-slate-300 p-3"><strong class="block text-xl">{{ user.reviews_count || 0 }}</strong>Đánh giá</div></div></section>
      <section class="mt-7"><h2 class="border-b border-slate-400 pb-2 text-base font-bold uppercase">Địa chỉ liên hệ</h2><ol v-if="addresses.length" class="mt-3 list-decimal space-y-2 pl-5 text-sm"><li v-for="address in addresses" :key="address.id"><strong>{{ address.receiver_name }}</strong> · {{ address.phone }} · {{ address.address }}<span v-if="address.is_default"> (Mặc định)</span></li></ol><p v-else class="mt-3 text-sm">Chưa lưu địa chỉ.</p></section>
      <section v-if="user.vendor_info || user.organization_memberships?.length || user.warehouse_manager_assignments?.length" class="mt-7"><h2 class="border-b border-slate-400 pb-2 text-base font-bold uppercase">Đơn vị và quyền liên kết</h2><div class="mt-3 space-y-2 text-sm"><p v-if="user.vendor_info"><strong>Nhà bán:</strong> {{ user.vendor_info.shop_name }} · {{ user.vendor_info.status }}</p><p v-for="membership in user.organization_memberships || []" :key="membership.id"><strong>Tổ chức:</strong> {{ membership.organization?.display_name || membership.organization?.legal_name }} · {{ membership.role }} · {{ membership.status }}</p><p v-for="assignment in user.warehouse_manager_assignments || []" :key="assignment.id"><strong>Kho:</strong> {{ assignment.warehouse?.name }} · {{ assignment.vendor?.shop_name }} · {{ assignment.status }}</p></div></section>
      <footer class="mt-10 flex justify-between border-t border-slate-400 pt-4 text-xs text-slate-600"><span>Kết xuất lúc {{ formatDate(generatedAt) }}</span><span>Phiếu được tạo từ dữ liệu hiện có trên KomiBook.</span></footer>
    </article>
  </main>
</template>

<style scoped>
@page { size: A4; margin: 14mm; }
@media print {
  :global(.admin-sidebar), :global(.sidebar-edge-toggle), :global(.admin-topbar), .print-actions { display: none !important; }
  :global(.admin-main), :global(.main-expanded) { margin: 0 !important; width: 100% !important; }
  :global(.admin-content) { padding: 0 !important; }
  :global(body), :global(.admin-layout) { background: #fff !important; }
  .print-page { margin: 0; max-width: none; padding: 0; box-shadow: none; }
}
</style>
