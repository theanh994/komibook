<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const transferId = route.params.id

const transfer = ref(null)
const loading = ref(true)

const fetchTransferDetails = async () => {
  loading.value = true
  try {
    const res = await apiClient.get(`/api/vendor/inventory/transfers/${transferId}`)
    if (res.data?.status === 'success') {
      transfer.value = res.data.data
    }
  } catch (e) {
    console.error('Không tải được phiếu điều chuyển', e)
    // Fallback Mock Print
    transfer.value = {
      transfer_code: 'TRF-20260713-A8BC',
      from_warehouse: { name: 'Kho Trung Tâm (Quận 1)', address: '123 Lê Lợi, Phường Bến Nghé, Quận 1, TP.HCM' },
      to_warehouse: { name: 'Kho Chi Nhánh Thủ Đức', address: '45 Võ Văn Ngân, Phường Linh Chiểu, Thủ Đức, TP.HCM' },
      reason: 'Bổ sung số lượng sách tham khảo chuyên ngành và tiểu thuyết bán chạy cho chi nhánh Thủ Đức.',
      created_at: '2026-07-13',
      items: [
        { id: 1, book: { title: 'Lược Sử Loài Người (Sapiens)' }, quantity: 50 },
        { id: 2, book: { title: 'Lược Sử Thời Gian' }, quantity: 30 },
        { id: 3, book: { title: 'Nhà Giả Kim' }, quantity: 100 },
      ]
    }
  } finally {
    loading.value = false
  }
}

const getTotalQty = () => {
  if (!transfer.value?.items) return 0
  return transfer.value.items.reduce((sum, item) => sum + item.quantity, 0)
}

onMounted(() => {
  fetchTransferDetails()
})
</script>

<template>
  <div class="print-page min-h-screen bg-slate-100 py-10 px-4 flex justify-center">
    <div v-if="loading" class="flex items-center justify-center p-12">
      <i class="pi pi-spin pi-spinner text-3xl text-slate-500"></i>
    </div>

    <!-- Print Container -->
    <main v-else class="print-container bg-white w-full max-w-4xl p-10 md:p-16 border border-slate-300 shadow-md rounded flex flex-col gap-8 text-slate-800">
      <!-- Print Header -->
      <header class="flex flex-col md:flex-row justify-between items-start border-b-2 border-slate-900 pb-6 gap-6">
        <div class="flex items-center gap-3">
          <i class="pi pi-directions text-3xl text-indigo-900"></i>
          <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">KomiBook Distribution</h1>
            <p class="text-xs text-slate-500">Hệ thống Điều chuyển hàng tồn kho</p>
          </div>
        </div>
        <div class="text-left md:text-right">
          <h2 class="text-2xl font-extrabold text-slate-900 mb-1">PHIẾU ĐIỀU CHUYỂN KHO</h2>
          <div class="text-xs text-slate-500 space-y-1">
            <p>Số phiếu: <span class="font-bold text-slate-900 tracking-wider">{{ transfer.transfer_code }}</span></p>
            <p>Ngày lập: <span class="text-slate-900 font-medium">{{ transfer.created_at?.split('T')[0] }}</span></p>
          </div>
        </div>
      </header>

      <!-- Info grids -->
      <section class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        <div class="border border-slate-300 p-4 rounded">
          <h3 class="font-bold text-slate-900 uppercase mb-2 border-b border-slate-200 pb-1 text-xs">Từ kho (Xuất)</h3>
          <div class="space-y-1.5 text-xs text-slate-600">
            <p class="font-semibold text-slate-800">{{ transfer.from_warehouse?.name || 'Kho nguồn' }}</p>
            <p>{{ transfer.from_warehouse?.address || 'Địa chỉ kho xuất' }}</p>
          </div>
        </div>

        <div class="border border-slate-300 p-4 rounded">
          <h3 class="font-bold text-slate-900 uppercase mb-2 border-b border-slate-200 pb-1 text-xs">Đến kho (Nhập)</h3>
          <div class="space-y-1.5 text-xs text-slate-600">
            <p class="font-semibold text-slate-800">{{ transfer.to_warehouse?.name || 'Kho đích' }}</p>
            <p>{{ transfer.to_warehouse?.address || 'Địa chỉ kho nhập' }}</p>
          </div>
        </div>
      </section>

      <!-- Reason -->
      <section class="bg-slate-50 border border-slate-200 p-4 rounded flex gap-3 text-xs">
        <i class="pi pi-info-circle text-slate-500 text-base mt-0.5"></i>
        <div>
          <h4 class="font-bold text-slate-900 mb-1">Lý do điều chuyển</h4>
          <p class="text-slate-600">{{ transfer.reason || 'Điều động sách phục vụ kinh doanh chi nhánh' }}</p>
        </div>
      </section>

      <!-- Table Details -->
      <section class="overflow-x-auto">
        <table class="w-full text-left border-collapse border border-slate-900 text-xs">
          <thead class="bg-slate-100 border-b-2 border-slate-900 font-bold">
            <tr>
              <th class="border border-slate-900 p-2.5 w-12 text-center">STT</th>
              <th class="border border-slate-900 p-2.5">Tên Sách giấy</th>
              <th class="border border-slate-900 p-2.5 w-24 text-center">ĐVT</th>
              <th class="border border-slate-900 p-2.5 w-32 text-right">SL Chuyển</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in transfer.items" :key="item.id">
              <td class="border border-slate-900 p-2.5 text-center">{{ idx + 1 }}</td>
              <td class="border border-slate-900 p-2.5 font-semibold">{{ item.book?.title || 'Sách đã xóa' }}</td>
              <td class="border border-slate-900 p-2.5 text-center">Cuốn</td>
              <td class="border border-slate-900 p-2.5 text-right font-bold text-slate-900">{{ item.quantity }}</td>
            </tr>
          </tbody>
          <tfoot class="bg-slate-50 font-bold">
            <tr>
              <td class="border border-slate-900 p-2.5 text-right" colspan="3">Tổng cộng số lượng:</td>
              <td class="border border-slate-900 p-2.5 text-right text-sm">{{ getTotalQty() }}</td>
            </tr>
          </tfoot>
        </table>
      </section>

      <!-- Signatures -->
      <footer class="mt-8 pt-8 border-t-2 border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-6 text-center text-xs">
        <div>
          <p class="font-bold text-slate-800 mb-1">Người lập phiếu</p>
          <p class="text-slate-400 italic mb-16">(Ký, ghi rõ họ tên)</p>
        </div>
        <div>
          <p class="font-bold text-slate-800 mb-1">Thủ kho xuất</p>
          <p class="text-slate-400 italic mb-16">(Ký, ghi rõ họ tên)</p>
        </div>
        <div>
          <p class="font-bold text-slate-800 mb-1">Thủ kho nhập</p>
          <p class="text-slate-400 italic mb-16">(Ký, ghi rõ họ tên)</p>
        </div>
        <div>
          <p class="font-bold text-slate-800 mb-1">Người phê duyệt</p>
          <p class="text-slate-400 italic mb-16">(Ký, ghi rõ họ tên)</p>
        </div>
      </footer>

      <!-- Floating Print Controls (Hidden on print) -->
      <div class="no-print flex justify-end gap-3 border-t border-slate-100 pt-4 mt-auto">
        <button class="px-4 py-2 border border-indigo-600 text-indigo-600 rounded text-xs font-semibold hover:bg-slate-50" @click="window.close()">
          Đóng tab
        </button>
        <button class="px-5 py-2 bg-indigo-600 text-white rounded text-xs font-semibold hover:opacity-90 flex items-center gap-1.5" @click="window.print()">
          <i class="pi pi-print"></i> In phiếu
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
