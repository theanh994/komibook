<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'

const toast = useToast()
const loading = ref(true)

const pendingVendors = ref([])
const pendingAuthors = ref([])
const selectedPartner = ref(null)

const showRejectDialog = ref(false)
const rejectReason = ref('')

const fetchApprovals = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/approvals/vendors')
    if (res.data?.status === 'success') {
      pendingVendors.value = res.data.data.vendors || []
      pendingAuthors.value = res.data.data.authors || []
    }
  } catch (e) {
    console.error('Không tải được danh sách phê duyệt', e)
    // Fallback Mock Data
    pendingVendors.value = [
      { id: 1, shop_name: 'Alpha Publishing House', description: 'Đơn vị phát hành sách kinh tế chuyên nghiệp.', user: { name: 'Nguyễn Văn A', email: 'contact@alpha.vn' }, created_at: '2026-07-13' },
    ]
    pendingAuthors.value = [
      { id: 2, pen_name: 'Nguyễn Nhật Ánh', bio: 'Nhà văn viết truyện thiếu nhi nổi tiếng Việt Nam.', user: { name: 'Nguyễn Nhật Ánh', email: 'anhnn@gmail.com' }, bank_name: 'Vietcombank', bank_account_number: '007100123456', created_at: '2026-07-13' },
    ]
  } finally {
    loading.value = false
  }
}

const approveVendor = async (id) => {
  try {
    const res = await apiClient.patch(`/api/admin/approvals/vendors/${id}/approve`)
    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã phê duyệt', detail: 'Đã kích hoạt tài khoản nhà bán đối tác thành công.', life: 3000 })
      fetchApprovals()
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể phê duyệt tài khoản.', life: 3000 })
  }
}

const approveAuthor = async (id) => {
  try {
    const res = await apiClient.patch(`/api/admin/approvals/authors/${id}/approve`)
    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã phê duyệt', detail: 'Đã duyệt đối tác tác giả và tự động kích hoạt gian hàng.', life: 3500 })
      fetchApprovals()
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể phê duyệt tài khoản.', life: 3000 })
  }
}

const openRejectDialog = (partner, type) => {
  selectedPartner.value = { ...partner, type }
  rejectReason.value = ''
  showRejectDialog.value = true
}

const rejectPartner = async () => {
  if (!rejectReason.value) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Vui lòng cung cấp lý do từ chối.', life: 3000 })
    return
  }

  try {
    const { id, type } = selectedPartner.value
    const res = await apiClient.patch(`/api/admin/approvals/partners/${type}/${id}/reject`, {
      reason: rejectReason.value
    })

    if (res.data?.status === 'success') {
      toast.add({ severity: 'info', summary: 'Đã từ chối', detail: 'Đã gửi email phản hồi từ chối hồ sơ đăng ký.', life: 3000 })
      showRejectDialog.value = false
      fetchApprovals()
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Thao tác từ chối gặp lỗi.', life: 3000 })
  }
}

const downloadAuthorDoc = async (author) => {
  try {
    const url = author.identity_document_url
    if (!url) {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Tác giả chưa cập nhật giấy tờ CCCD.', life: 3000 })
      return
    }
    const response = await apiClient.get(url, { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', `author-cccd-${author.id}`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải giấy tờ CCCD.', life: 3000 })
  }
}

onMounted(() => {
  fetchApprovals()
})
</script>

<template>
  <div class="vendor-approvals min-h-screen bg-slate-50 p-6 md:p-8">
    <Toast />
    
    <div class="mb-8">
      <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Kiểm duyệt đối tác đăng ký</h1>
      <p class="text-slate-500 text-sm mt-1">Phê duyệt danh tính và tài khoản thụ hưởng của các Nhà bán sách cũ & Tác giả tự viết.</p>
    </div>

    <!-- Bento Stats Counter -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
          <i class="pi pi-store text-xl"></i>
        </div>
        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Yêu cầu Nhà bán (Sách cũ)</span>
          <h2 class="text-2xl font-black text-slate-900 mt-1">{{ pendingVendors.length }} đơn chờ duyệt</h2>
        </div>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
          <i class="pi pi-user text-xl"></i>
        </div>
        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Yêu cầu Tác giả (Ebook)</span>
          <h2 class="text-2xl font-black text-slate-900 mt-1">{{ pendingAuthors.length }} đơn chờ duyệt</h2>
        </div>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Pending Vendors Column -->
      <div class="space-y-4">
        <h3 class="font-bold text-slate-800 text-base mb-2">Đăng ký Nhà bán sách cũ</h3>
        
        <div v-for="vendor in pendingVendors" :key="vendor.id" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm relative overflow-hidden">
          <div class="absolute left-0 top-0 w-1 h-full bg-indigo-600"></div>
          
          <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
            <div class="space-y-2">
              <div class="flex items-center gap-2">
                <h4 class="font-bold text-slate-800 text-base">{{ vendor.shop_name }}</h4>
                <span class="bg-slate-100 text-slate-600 text-[10px] px-2 py-0.5 rounded-full font-semibold">Cửa hàng sách cũ</span>
              </div>
              <p class="text-xs text-slate-500 leading-normal">{{ vendor.description }}</p>
              <div class="text-[10px] text-slate-400">
                Người đại diện: <strong class="text-slate-600">{{ vendor.user?.name }}</strong> | Email: {{ vendor.user?.email }}
              </div>
            </div>

            <div class="flex gap-2 w-full sm:w-auto shrink-0 justify-end">
              <Button label="Phê duyệt" icon="pi pi-check" class="p-button-success p-button-sm text-xs font-bold" @click="approveVendor(vendor.id)" />
              <Button label="Từ chối" icon="pi pi-times" class="p-button-outlined p-button-danger p-button-sm text-xs" @click="openRejectDialog(vendor, 'vendor')" />
            </div>
          </div>
        </div>

        <div v-if="pendingVendors.length === 0" class="text-center p-8 text-slate-400 text-xs bg-white rounded-2xl border border-slate-200">
          Không có yêu cầu nhà bán nào đang chờ duyệt.
        </div>
      </div>

      <!-- Pending Authors Column -->
      <div class="space-y-4">
        <h3 class="font-bold text-slate-800 text-base mb-2">Đăng ký Tác giả tự xuất bản</h3>
        
        <div v-for="author in pendingAuthors" :key="author.id" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm relative overflow-hidden">
          <div class="absolute left-0 top-0 w-1 h-full bg-rose-600"></div>
          
          <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
            <div class="space-y-2">
              <div class="flex items-center gap-2">
                <h4 class="font-bold text-slate-800 text-base">{{ author.pen_name }}</h4>
                <span class="bg-rose-50 text-rose-700 text-[10px] px-2 py-0.5 rounded-full font-semibold">Tác giả sáng tác</span>
              </div>
              <p class="text-xs text-slate-500 leading-normal">{{ author.bio }}</p>
              <div class="text-[10px] text-slate-400">
                Chủ thẻ: <strong class="text-slate-600">{{ author.user?.name }}</strong> | NH: {{ author.bank_name }} | STK: {{ author.bank_account_number }}
              </div>
              <div v-if="author.has_identity_document || author.identity_document_url" class="mt-1">
                <button type="button" @click="downloadAuthorDoc(author)" class="text-[11px] text-indigo-600 hover:underline flex items-center gap-1 font-semibold bg-transparent border-none cursor-pointer p-0">
                  <i class="pi pi-file-pdf"></i> Tải / Xem giấy tờ CCCD
                </button>
              </div>
            </div>

            <div class="flex gap-2 w-full sm:w-auto shrink-0 justify-end">
              <Button label="Duyệt & Kích hoạt" icon="pi pi-check" class="p-button-success p-button-sm text-xs font-bold" @click="approveAuthor(author.id)" />
              <Button label="Từ chối" icon="pi pi-times" class="p-button-outlined p-button-danger p-button-sm text-xs" @click="openRejectDialog(author, 'author')" />
            </div>
          </div>
        </div>

        <div v-if="pendingAuthors.length === 0" class="text-center p-8 text-slate-400 text-xs bg-white rounded-2xl border border-slate-200">
          Không có yêu cầu tác giả nào đang chờ duyệt.
        </div>
      </div>
    </div>

    <!-- Reject Dialog -->
    <Dialog v-model:visible="showRejectDialog" modal header="Từ chối hồ sơ đăng ký đối tác" :style="{ width: '90vw', maxWidth: '500px' }">
      <div class="space-y-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500">Lý do từ chối hồ sơ <span class="text-rose-500">*</span></label>
          <InputText v-model="rejectReason" placeholder="Nhập lý do từ chối để phản hồi cho đối tác..." class="w-full text-sm" />
        </div>
      </div>
      
      <template #footer>
        <Button label="Hủy" class="p-button-text p-button-sm text-xs" @click="showRejectDialog = false" />
        <Button label="Xác nhận từ chối" class="p-button-danger p-button-sm text-xs bg-rose-600 text-white" @click="rejectPartner" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.vendor-approvals {
  font-family: 'Inter', sans-serif;
}
</style>
