<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const router = useRouter()
const toast = useToast()

const users = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const currentPage = ref(1)
const perPage = ref(10)
const filterTier = ref('')
const filterStatus = ref('')
const selectAll = ref(false)
const selectedIds = ref([])

const lastPage = computed(() => Math.ceil(totalRecords.value / perPage.value))

const fetchUsers = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/users', {
      params: { page: currentPage.value, per_page: perPage.value }
    })
    users.value = res.data.data || []
    totalRecords.value = res.data.meta?.total || users.value.length
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách khách hàng.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedIds.value = users.value.map(u => u.id)
  } else {
    selectedIds.value = []
  }
}

const getInitials = (name) => {
  if (!name) return '??'
  const parts = name.split(' ')
  return (parts[0]?.[0] || '') + (parts[parts.length - 1]?.[0] || '')
}

const formatVND = (val) => {
  if (!val && val !== 0) return '0 ₫'
  return new Intl.NumberFormat('vi-VN').format(val) + ' ₫'
}

const viewDetail = (user) => {
  router.push({ name: 'admin-customer-detail', params: { id: user.id } })
}

onMounted(fetchUsers)
</script>

<template>
  <div class="px-lg md:px-xl pb-xxl max-w-container-max mx-auto w-full pt-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-xl gap-md animate-fade-in">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Danh sách Khách hàng</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Quản lý và theo dõi thông tin người dùng hệ thống.</p>
      </div>
      <div class="flex items-center gap-sm">
        <button class="px-4 py-2 border-[1.5px] border-primary text-primary rounded-lg font-label-md text-label-md hover:bg-surface-container-low transition-colors flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">download</span> Xuất dữ liệu
        </button>
        <button class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-colors flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">person_add</span> Thêm khách hàng
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft p-md mb-lg border border-outline-variant/30 flex flex-wrap items-center gap-md animate-slide-up">
      <div class="flex-1 min-w-[200px] relative">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">filter_list</span>
        <select v-model="filterTier" class="w-full pl-10 pr-10 py-2.5 bg-surface border border-outline-variant rounded-lg font-body-md text-body-md text-on-surface appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors cursor-pointer">
          <option value="">Tất cả hạng thành viên</option>
          <option value="free">Free</option>
          <option value="premium">Premium</option>
          <option value="vip">VIP</option>
        </select>
      </div>
      <div class="flex-1 min-w-[200px] relative">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">rule</span>
        <select v-model="filterStatus" class="w-full pl-10 pr-10 py-2.5 bg-surface border border-outline-variant rounded-lg font-body-md text-body-md text-on-surface appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors cursor-pointer">
          <option value="">Tất cả trạng thái</option>
          <option value="active">Đang hoạt động</option>
          <option value="suspended">Tạm khóa</option>
        </select>
      </div>
      <div class="flex items-center gap-sm ml-auto">
        <span class="font-label-md text-label-md text-on-surface-variant">Hành động hàng loạt:</span>
        <button :disabled="selectedIds.length === 0" class="p-2 border border-outline-variant rounded-lg text-on-surface-variant hover:bg-surface-variant hover:text-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
          <span class="material-symbols-outlined">delete</span>
        </button>
        <button :disabled="selectedIds.length === 0" class="p-2 border border-outline-variant rounded-lg text-on-surface-variant hover:bg-surface-variant hover:text-primary transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
          <span class="material-symbols-outlined">mail</span>
        </button>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft overflow-hidden border border-outline-variant/30 animate-slide-up delay-100">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-surface-container-low border-b border-outline-variant/50">
              <th class="py-md px-md w-[48px] text-center">
                <input v-model="selectAll" @change="toggleSelectAll" type="checkbox" class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary"/>
              </th>
              <th class="py-md px-sm font-label-md text-label-md text-on-surface-variant font-semibold">Khách hàng</th>
              <th class="py-md px-sm font-label-md text-label-md text-on-surface-variant font-semibold">ID</th>
              <th class="py-md px-sm font-label-md text-label-md text-on-surface-variant font-semibold">Hạng</th>
              <th class="py-md px-sm font-label-md text-label-md text-on-surface-variant font-semibold">Trạng thái</th>
              <th class="py-md px-sm font-label-md text-label-md text-on-surface-variant font-semibold text-right">Vai trò</th>
              <th class="py-md px-md text-right font-label-md text-label-md text-on-surface-variant font-semibold">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/30">
            <tr v-for="user in users" :key="user.id" class="hover:bg-surface-variant/30 transition-colors group">
              <td class="py-sm px-md text-center">
                <input v-model="selectedIds" :value="user.id" type="checkbox" class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary"/>
              </td>
              <td class="py-sm px-sm">
                <div class="flex items-center gap-md">
                  <div class="w-10 h-10 rounded-full flex items-center justify-center bg-primary-fixed text-primary font-headline-md text-[16px] border border-outline-variant/30">
                    {{ getInitials(user.name) }}
                  </div>
                  <div>
                    <div class="font-label-md text-label-md text-on-surface font-semibold">{{ user.name }}</div>
                    <div class="font-body-md text-[13px] text-on-surface-variant">{{ user.email }}</div>
                  </div>
                </div>
              </td>
              <td class="py-sm px-sm font-body-md text-body-md text-on-surface-variant">#KB-{{ String(user.id).padStart(4, '0') }}</td>
              <td class="py-sm px-sm">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[12px] font-medium bg-[#f1f5f9] text-[#475569] border border-[#e2e8f0]">
                  Free
                </span>
              </td>
              <td class="py-sm px-sm">
                <span class="inline-flex items-center gap-1.5 font-label-md text-[13px] text-[#15803d]">
                  <span class="w-1.5 h-1.5 rounded-full bg-[#16a34a]"></span>
                  Đang hoạt động
                </span>
              </td>
              <td class="py-sm px-sm text-right">
                <span class="font-label-md text-label-md text-on-surface capitalize">{{ user.role }}</span>
              </td>
              <td class="py-sm px-md text-right">
                <button @click="viewDetail(user)" class="text-outline hover:text-primary transition-colors p-1 rounded-full hover:bg-surface-variant">
                  <span class="material-symbols-outlined text-[20px]">visibility</span>
                </button>
              </td>
            </tr>
            <tr v-if="users.length === 0">
              <td colspan="7" class="py-xl px-lg text-center text-on-surface-variant">Không tìm thấy khách hàng nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="px-md py-sm border-t border-outline-variant/30 flex items-center justify-between bg-surface-container-lowest">
        <div class="font-body-md text-[13px] text-on-surface-variant">
          Hiển thị trang <span class="font-semibold text-on-surface">{{ currentPage }}</span> / <span class="font-semibold text-on-surface">{{ lastPage }}</span> trong <span class="font-semibold text-on-surface">{{ totalRecords }}</span> khách hàng
        </div>
        <div class="flex items-center gap-xs">
          <button @click="currentPage > 1 && (currentPage--, fetchUsers())" :disabled="currentPage <= 1" class="p-1 rounded text-outline hover:bg-surface-variant transition-colors disabled:opacity-50">
            <span class="material-symbols-outlined text-[20px]">chevron_left</span>
          </button>
          <button class="w-8 h-8 rounded bg-primary text-on-primary font-label-md text-[14px] flex items-center justify-center">{{ currentPage }}</button>
          <button @click="currentPage < lastPage && (currentPage++, fetchUsers())" :disabled="currentPage >= lastPage" class="p-1 rounded text-on-surface-variant hover:bg-surface-variant transition-colors">
            <span class="material-symbols-outlined text-[20px]">chevron_right</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shadow-soft { box-shadow: 0 4px 12px rgba(26, 58, 90, 0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
</style>
