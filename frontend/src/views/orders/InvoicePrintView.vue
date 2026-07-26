<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const orderId = route.params.id

const order = ref(null)
const loading = ref(true)
const error = ref(null)

const fetchOrderDetails = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get(`/api/my-orders/${orderId}`)
    if (res.data?.data) {
      order.value = res.data.data
    } else {
      error.value = 'Không tìm thấy thông tin đơn hàng.'
    }
  } catch (e) {
    console.error('Không tải được thông tin đơn hàng:', e)
    error.value = e.response?.data?.message || 'Không thể kết nối đến hệ thống để lấy thông tin đơn hàng.'
  } finally {
    loading.value = false
  }
}

const formatCurrency = (val) => {
  if (val == null) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const formatDate = (dateString) => {
  if (!dateString) return '—'
  return dateString.split('T')[0]
}

onMounted(() => {
  fetchOrderDetails()
})
</script>

<template>
  <div class="print-page min-h-screen bg-slate-100 py-10 px-4 flex justify-center">
    <!-- Loading State -->
    <div v-if="loading" class="flex flex-col items-center justify-center p-12 gap-3">
      <i class="pi pi-spin pi-spinner text-3xl text-slate-500"></i>
      <p class="text-xs text-slate-500 font-semibold">Đang tải thông tin đơn hàng...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error || !order" class="bg-white max-w-md w-full p-8 rounded-2xl border border-slate-200 shadow-md text-center my-auto space-y-4">
      <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto">
        <i class="pi pi-exclamation-triangle text-xl"></i>
      </div>
      <h3 class="text-base font-bold text-slate-900">Không thể tải thông tin đơn hàng</h3>
      <p class="text-xs text-slate-500 leading-relaxed">{{ error || 'Đơn hàng không tồn tại hoặc bạn không có quyền xem.' }}</p>
      <div class="pt-2 flex justify-center gap-3">
        <button class="px-4 py-2 bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-300 transition-colors border-none cursor-pointer" @click="window.close()">
          Đóng tab
        </button>
        <button class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-colors border-none cursor-pointer" @click="fetchOrderDetails">
          Thử lại
        </button>
      </div>
    </div>

    <!-- Invoice Container -->
    <main v-else class="print-container bg-white w-full max-w-4xl p-10 md:p-16 border border-slate-300 shadow-md rounded flex flex-col gap-8 text-slate-800">
      <!-- Invoice Header -->
      <header class="flex flex-col md:flex-row justify-between items-start border-b-2 border-slate-900 pb-6 gap-6">
        <div class="flex items-center gap-3">
          <i class="pi pi-book text-3xl text-indigo-950"></i>
          <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">KomiBook</h1>
            <p class="text-xs text-slate-500">Thông tin chi tiết đơn hàng bán lẻ</p>
          </div>
        </div>
        <div class="text-left md:text-right">
          <h2 class="text-2xl font-extrabold text-slate-900 mb-1">THÔNG TIN ĐƠN HÀNG</h2>
          <div class="text-xs text-slate-500 space-y-1">
            <p>Mã đơn hàng: <span class="font-bold text-slate-900 tracking-wider">#{{ order.order_code || order.id }}</span></p>
            <p>Ngày đặt hàng: <span class="text-slate-900 font-medium">{{ formatDate(order.created_at) }}</span></p>
          </div>
        </div>
      </header>

      <!-- Customer & Seller Info -->
      <section class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
        <div class="bg-slate-50 p-4 rounded border border-slate-200">
          <h3 class="font-bold text-indigo-950 uppercase mb-2 border-b border-slate-200 pb-1">Thông tin đơn vị cung cấp</h3>
          <p class="font-semibold text-slate-800">KomiBook Store</p>
          <p class="text-slate-500 mt-1">Đại diện: Ban quản trị KomiBook</p>
        </div>

        <div class="bg-slate-50 p-4 rounded border border-slate-200">
          <h3 class="font-bold text-indigo-950 uppercase mb-2 border-b border-slate-200 pb-1">Thông tin người mua</h3>
          <p class="font-semibold text-slate-800">{{ order.customer_name || order.user?.name || '—' }}</p>
          <p class="text-slate-500 mt-1">SĐT: {{ order.customer_phone || order.phone || '—' }}</p>
          <p class="text-slate-500">Email: {{ order.customer_email || order.user?.email || '—' }}</p>
          <p class="text-slate-500">Đ/C: {{ order.shipping_address || '—' }}</p>
        </div>
      </section>

      <!-- Table items -->
      <section class="overflow-x-auto">
        <table class="w-full text-left border-collapse border border-slate-900 text-xs">
          <thead class="bg-slate-100 border-b-2 border-slate-900 font-bold">
            <tr>
              <th class="border border-slate-900 p-2.5 w-12 text-center">STT</th>
              <th class="border border-slate-900 p-2.5">Tên sản phẩm</th>
              <th class="border border-slate-900 p-2.5 w-28 text-right">Đơn giá</th>
              <th class="border border-slate-900 p-2.5 w-24 text-center">Số lượng</th>
              <th class="border border-slate-900 p-2.5 w-32 text-right">Thành tiền</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in order.items" :key="item.id">
              <td class="border border-slate-900 p-2.5 text-center">{{ idx + 1 }}</td>
              <td class="border border-slate-900 p-2.5 font-semibold">{{ item.book?.title || '—' }}</td>
              <td class="border border-slate-900 p-2.5 text-right">{{ formatCurrency(item.price) }}</td>
              <td class="border border-slate-900 p-2.5 text-center">{{ item.quantity }}</td>
              <td class="border border-slate-900 p-2.5 text-right font-bold text-slate-900">
                {{ formatCurrency(item.price * item.quantity) }}
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-slate-50 font-bold text-slate-900">
            <tr class="bg-indigo-900 text-white font-extrabold text-sm">
              <td class="border border-slate-900 p-2.5 text-right" colspan="4">Tổng thanh toán:</td>
              <td class="border border-slate-900 p-2.5 text-right">{{ formatCurrency(order.total_amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </section>

      <!-- Footer / Signatures -->
      <footer class="mt-8 pt-8 border-t-2 border-slate-200 grid grid-cols-2 gap-6 text-center text-xs">
        <div>
          <p class="font-bold text-slate-800 mb-1">Người mua hàng</p>
          <p class="text-slate-400 italic mb-16">(Ký, ghi rõ họ tên)</p>
        </div>
        <div>
          <p class="font-bold text-slate-800 mb-1">Đơn vị bán hàng</p>
          <p class="text-slate-400 italic mb-16">(Ký, đóng dấu, ghi rõ họ tên)</p>
        </div>
      </footer>

      <!-- Print controls (Hidden on print) -->
      <div class="no-print flex justify-end gap-3 border-t border-slate-100 pt-4 mt-auto">
        <button class="px-4 py-2 border border-slate-350 text-slate-600 rounded text-xs font-semibold hover:bg-slate-50 border-none cursor-pointer" @click="window.close()">
          Đóng tab
        </button>
        <button class="px-5 py-2 bg-indigo-600 text-white rounded text-xs font-semibold hover:opacity-90 flex items-center gap-1.5 border-none cursor-pointer" @click="window.print()">
          <i class="pi pi-print"></i> In đơn hàng
        </button>
      </div>
    </main>
  </div>
</template>

<style>
@media print {
  body {
    background-color: white !important;
  }
  .print-page {
    background-color: white !important;
    padding: 0 !important;
  }
  .print-container {
    box-shadow: none !important;
    border: none !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  .no-print {
    display: none !important;
  }
}
</style>
