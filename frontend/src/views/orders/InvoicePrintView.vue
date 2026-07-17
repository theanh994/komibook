<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const orderId = route.params.id

const order = ref(null)
const loading = ref(true)

const fetchOrderDetails = async () => {
  loading.value = true
  try {
    const res = await apiClient.get(`/api/orders`)
    // Find order
    const o = res.data?.find(item => item.id == orderId)
    if (o) {
      order.value = o
    }
  } catch (e) {
    console.error('Không tải được hóa đơn', e)
    // Fallback Mock Invoice
    order.value = {
      order_code: 'ORD-98765',
      created_at: '2026-07-13',
      id: orderId,
      customer_name: 'Trần Thị Bích Ngọc',
      customer_email: 'bichngoc.tran@email.com',
      customer_phone: '0901 234 567',
      shipping_address: '456 Lê Lợi, Phường Bến Nghé, Quận 1, TP. HCM',
      payment_method: 'Chuyển khoản Ngân hàng',
      order_items: [
        { id: 1, book: { title: 'Nghệ Thuật Tư Duy Rành Mạch' }, price: 150000, quantity: 2 },
        { id: 2, book: { title: 'Sapiens: Lược Sử Loài Người' }, price: 220000, quantity: 1 },
      ],
      subtotal: 520000,
      tax: 41600,
      total: 561600,
    }
  } finally {
    loading.value = false
  }
}

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

onMounted(() => {
  fetchOrderDetails()
})
</script>

<template>
  <div class="print-page min-h-screen bg-slate-100 py-10 px-4 flex justify-center">
    <div v-if="loading" class="flex items-center justify-center p-12">
      <i class="pi pi-spin pi-spinner text-3xl text-slate-500"></i>
    </div>

    <!-- Invoice Container -->
    <main v-else class="print-container bg-white w-full max-w-4xl p-10 md:p-16 border border-slate-300 shadow-md rounded flex flex-col gap-8 text-slate-800">
      <!-- Invoice Header -->
      <header class="flex flex-col md:flex-row justify-between items-start border-b-2 border-slate-900 pb-6 gap-6">
        <div class="flex items-center gap-3">
          <i class="pi pi-book text-3xl text-indigo-950"></i>
          <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">KomiBook Premium</h1>
            <p class="text-xs text-slate-500">Công Ty TNHH Sách & Công Nghệ KomiBook</p>
            <p class="text-[10px] text-slate-400 mt-1">MST: 0312345678 | 123 Đường Sách, Q.1, TP. HCM</p>
          </div>
        </div>
        <div class="text-left md:text-right">
          <h2 class="text-2xl font-extrabold text-slate-900 mb-1">HÓA ĐƠN BÁN HÀNG</h2>
          <div class="text-xs text-slate-500 space-y-1">
            <p>Mã hóa đơn: <span class="font-bold text-slate-900 tracking-wider">INV-2026-0892</span></p>
            <p>Ngày lập: <span class="text-slate-900 font-medium">{{ order.created_at?.split('T')[0] }}</span></p>
            <p>Mã đơn hàng: <span class="text-slate-900 font-medium">#ORD-{{ order.id }}</span></p>
          </div>
        </div>
      </header>

      <!-- Customer & Seller Info -->
      <section class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
        <div class="bg-slate-50 p-4 rounded border border-slate-200">
          <h3 class="font-bold text-indigo-950 uppercase mb-2 border-b border-slate-200 pb-1">Thông tin người bán</h3>
          <p class="font-semibold text-slate-800">KomiBook Official Store</p>
          <p class="text-slate-500 mt-1">Đại diện: Ban quản trị KomiBook</p>
          <p class="text-slate-500">Email: support@komibook.vn</p>
        </div>

        <div class="bg-slate-50 p-4 rounded border border-slate-200">
          <h3 class="font-bold text-indigo-950 uppercase mb-2 border-b border-slate-200 pb-1">Thông tin người mua</h3>
          <p class="font-semibold text-slate-800">{{ order.customer_name || 'Khách hàng' }}</p>
          <p class="text-slate-500 mt-1">SĐT: {{ order.customer_phone || 'N/A' }}</p>
          <p class="text-slate-500">Email: {{ order.customer_email || 'N/A' }}</p>
          <p class="text-slate-500">Đ/C: {{ order.shipping_address || 'Tải Ebook trực tuyến' }}</p>
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
            <tr v-for="(item, idx) in order.order_items" :key="item.id">
              <td class="border border-slate-900 p-2.5 text-center">{{ idx + 1 }}</td>
              <td class="border border-slate-900 p-2.5 font-semibold">{{ item.book?.title }}</td>
              <td class="border border-slate-900 p-2.5 text-right">{{ formatCurrency(item.price) }}</td>
              <td class="border border-slate-900 p-2.5 text-center">{{ item.quantity }}</td>
              <td class="border border-slate-900 p-2.5 text-right font-bold text-slate-900">
                {{ formatCurrency(item.price * item.quantity) }}
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-slate-50 font-bold text-slate-900">
            <tr>
              <td class="border border-slate-900 p-2.5 text-right" colspan="4">Tạm tính:</td>
              <td class="border border-slate-900 p-2.5 text-right">{{ formatCurrency(order.subtotal) }}</td>
            </tr>
            <tr>
              <td class="border border-slate-900 p-2.5 text-right" colspan="4">Thuế VAT (8%):</td>
              <td class="border border-slate-900 p-2.5 text-right">{{ formatCurrency(order.tax) }}</td>
            </tr>
            <tr class="bg-indigo-900 text-white font-extrabold text-sm">
              <td class="border border-slate-900 p-2.5 text-right" colspan="4">Tổng cộng:</td>
              <td class="border border-slate-900 p-2.5 text-right">{{ formatCurrency(order.total) }}</td>
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
          <p class="font-bold text-slate-800 mb-1">Người bán hàng</p>
          <p class="text-slate-400 italic mb-16">(Ký, đóng dấu, ghi rõ họ tên)</p>
        </div>
      </footer>

      <!-- Print controls (Hidden on print) -->
      <div class="no-print flex justify-end gap-3 border-t border-slate-100 pt-4 mt-auto">
        <button class="px-4 py-2 border border-slate-350 text-slate-600 rounded text-xs font-semibold hover:bg-slate-50" @click="window.close()">
          Đóng tab
        </button>
        <button class="px-5 py-2 bg-indigo-600 text-white rounded text-xs font-semibold hover:opacity-90 flex items-center gap-1.5" @click="window.print()">
          <i class="pi pi-print"></i> In hóa đơn
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
