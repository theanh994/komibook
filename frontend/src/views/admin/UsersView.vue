<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useRouter, RouterLink } from 'vue-router'

const toast = useToast()
const router = useRouter()

// ─── State ───
const users = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyParams = ref({ first: 0, rows: 15, page: 1 })
const search = ref('')
const roleFilter = ref('')
const viewMode = ref('table')
const sortBy = ref('created_at')
const sortDirection = ref('desc')
const sortOptions = [
  { label: 'Mới tham gia trước', value: 'created_at:desc' },
  { label: 'Cũ tham gia trước', value: 'created_at:asc' },
  { label: 'Tên A → Z', value: 'name:asc' },
  { label: 'Tên Z → A', value: 'name:desc' },
  { label: 'Vai trò A → Z', value: 'role:asc' },
]

// Track which user session is being terminated
const terminatingUserId = ref(null)

// ─── Role Config ───
const roleMap = {
  admin:    { label: 'Admin',      severity: 'danger',  icon: 'pi pi-shield' },
  vendor:   { label: 'Vendor',     severity: 'info',    icon: 'pi pi-shop' },
  customer: { label: 'Khách hàng', severity: 'success', icon: 'pi pi-user' },
}

const roleOptions = [
  { label: 'Khách hàng', value: 'customer' },
  { label: 'Vendor', value: 'vendor' },
  { label: 'Admin', value: 'admin' },
]

const getRole = (role) => roleMap[role] || roleMap.customer

// ─── Formatters ───
const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  })
}

// ─── API Calls ───
const fetchUsers = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/users', {
      params: {
        page: lazyParams.value.page,
        per_page: lazyParams.value.rows,
        search: search.value || undefined,
        role: roleFilter.value || undefined,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
      },
    })
    users.value = res.data.data
    totalRecords.value = res.data.meta?.total || res.data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách người dùng.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const onPage = (event) => {
  lazyParams.value = { ...event, page: event.page + 1 }
  fetchUsers()
}

const applyFilters = () => {
  lazyParams.value.page = 1
  fetchUsers()
}

const applySort = (value) => {
  const [field, direction] = value.split(':')
  sortBy.value = field
  sortDirection.value = direction
  applyFilters()
}

const terminateSessions = async (user) => {
  if (!window.confirm(`Đăng xuất ${user.name} khỏi tất cả thiết bị?`)) return
  terminatingUserId.value = user.id
  try {
    const response = await apiClient.delete(`/api/admin/users/${user.id}/sessions`)
    toast.add({ severity: 'success', summary: 'Đã thu hồi phiên', detail: response.data.message, life: 3500 })
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể thu hồi phiên', detail: exception.response?.data?.message || 'Vui lòng thử lại.', life: 3500 })
  } finally {
    terminatingUserId.value = null
  }
}

onMounted(fetchUsers)
</script>

<template>
  <div class="admin-users">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý người dùng</h1>
        <p class="page-subtitle">Xem danh sách và thông tin tài khoản người dùng trên hệ thống (Phân quyền Nhà bán thực hiện tại trang Hồ sơ nhà bán).</p>
      </div>
      <div class="header-badge">
        <i class="pi pi-users"></i>
        <span>{{ totalRecords }} tài khoản</span>
      </div>
    </div>

    <!-- Integrated Filter & View Mode Toggle Bar -->
    <form class="mb-4 flex flex-col md:flex-row items-stretch md:items-end justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs" role="search" @submit.prevent="applyFilters">
      <div class="flex flex-wrap items-end gap-3 flex-1">
        <label class="block text-xs font-bold text-slate-600 flex-1 min-w-[200px]">
          Tìm tài khoản
          <InputText v-model.trim="search" class="mt-1 min-h-10 w-full text-sm" placeholder="Tên hoặc email…" />
        </label>

        <label class="block text-xs font-bold text-slate-600 w-44">
          Vai trò
          <Select v-model="roleFilter" class="mt-1 min-h-10 w-full text-sm" :options="[{ label: 'Tất cả vai trò', value: '' }, ...roleOptions]" optionLabel="label" optionValue="value" />
        </label>

        <label class="block text-xs font-bold text-slate-600 w-52">
          Sắp xếp
          <Select :modelValue="`${sortBy}:${sortDirection}`" class="mt-1 min-h-10 w-full text-sm" :options="sortOptions" optionLabel="label" optionValue="value" @update:modelValue="applySort" />
        </label>

        <Button type="submit" label="Lọc danh sách" icon="pi pi-search" class="min-h-10" />
      </div>

      <!-- View Mode Buttons Integrated inside Filter Bar -->
      <div class="flex items-center gap-2 border-t md:border-t-0 md:border-l border-slate-200 pt-3 md:pt-0 md:pl-3">
        <span class="text-xs font-bold text-slate-500 shrink-0">Hiển thị:</span>
        <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all cursor-pointer"
            :class="viewMode === 'table' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
            @click="viewMode = 'table'"
          >
            <i class="pi pi-table"></i> Bảng
          </button>

          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all cursor-pointer"
            :class="viewMode === 'cards' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
            @click="viewMode = 'cards'"
          >
            <i class="pi pi-th-large"></i> Thẻ
          </button>
        </div>
      </div>
    </form>

    <!-- Data Table Card -->
    <div v-if="viewMode === 'table'" class="table-card">
      <DataTable
        :value="users"
        :loading="loading"
        :paginator="true"
        :rows="lazyParams.rows"
        :totalRecords="totalRecords"
        :lazy="true"
        :rowsPerPageOptions="[15, 30, 50]"
        @page="onPage"
        dataKey="id"
        stripedRows
        class="users-table"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-users"></i>
            <p>Chưa có người dùng nào phù hợp với bộ lọc.</p>
          </div>
        </template>

        <!-- ID -->
        <Column header="ID" style="min-width: 70px; max-width: 80px">
          <template #body="{ data }">
            <span class="user-id">#{{ data.id }}</span>
          </template>
        </Column>

        <!-- Avatar + Name -->
        <Column header="Người dùng" style="min-width: 220px">
          <template #body="{ data }">
            <div class="user-cell">
              <div class="user-avatar-badge" :class="'role-' + data.role">
                <i class="pi pi-user"></i>
              </div>
              <div class="user-meta">
                <span class="user-name-text">{{ data.name }}</span>
                <span class="user-email-text">{{ data.email }}</span>
              </div>
            </div>
          </template>
        </Column>

        <!-- Role (Static Tag Badge) -->
        <Column header="Quyền hạn" style="min-width: 140px">
          <template #body="{ data }">
            <Tag
              :severity="getRole(data.role).severity"
              :value="getRole(data.role).label"
              :icon="getRole(data.role).icon"
              rounded
            />
          </template>
        </Column>

        <!-- Vendor Info / Hồ sơ nhà bán -->
        <Column header="Gian hàng" style="min-width: 180px">
          <template #body="{ data }">
            <RouterLink
              v-if="data.vendor"
              to="/admin/approvals"
              class="vendor-name font-bold text-indigo-600 hover:underline flex items-center gap-1.5"
            >
              <i class="pi pi-shop text-xs"></i>
              <span>{{ data.vendor.shop_name }}</span>
            </RouterLink>
            <span v-else class="no-vendor text-slate-400">—</span>
          </template>
        </Column>

        <!-- Ngày đăng ký -->
        <Column header="Ngày tham gia" style="min-width: 130px">
          <template #body="{ data }">
            <span class="date-text">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>

        <!-- Hành động -->
        <Column header="Hành động" style="min-width: 120px; text-align: right">
          <template #body="{ data }">
            <div class="flex items-center justify-end gap-1">
              <Button
                icon="pi pi-eye"
                text
                rounded
                severity="secondary"
                @click="router.push({ name: 'admin-user-detail', params: { id: data.id } })"
                v-tooltip.top="'Xem chi tiết'"
              />
              <Button
                v-if="data.role !== 'admin'"
                icon="pi pi-sign-out"
                text
                rounded
                severity="danger"
                :loading="terminatingUserId === data.id"
                @click="terminateSessions(data)"
                v-tooltip.top="'Đăng xuất khỏi mọi thiết bị'"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Skeleton Loading -->
    <div v-else-if="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" role="status" aria-label="Đang tải người dùng">
      <div v-for="index in 6" :key="index" class="h-44 animate-pulse rounded-xl bg-slate-200"></div>
    </div>
    <div v-else-if="!users.length" class="empty-state rounded-xl border border-slate-200 bg-white">Chưa có người dùng phù hợp với điều kiện lọc.</div>

    <!-- Cards Grid View -->
    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="user in users" :key="user.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between hover:shadow-md transition-all">
        <div>
          <div class="flex items-start justify-between gap-3">
            <div class="user-cell min-w-0">
              <div class="user-avatar-badge" :class="'role-' + user.role"><i class="pi pi-user"></i></div>
              <div class="user-meta">
                <strong class="user-name-text">{{ user.name }}</strong>
                <span class="user-email-text">{{ user.email }}</span>
              </div>
            </div>
            <Tag :severity="getRole(user.role).severity" :value="getRole(user.role).label" :icon="getRole(user.role).icon" rounded />
          </div>
          <dl class="mt-4 grid grid-cols-[92px_1fr] gap-x-3 gap-y-2 text-sm">
            <dt class="text-slate-500 font-semibold">ID</dt>
            <dd class="m-0 font-bold text-slate-800">#{{ user.id }}</dd>
            <dt class="text-slate-500 font-semibold">Gian hàng</dt>
            <dd class="m-0 truncate">
              <RouterLink v-if="user.vendor" to="/admin/approvals" class="font-bold text-indigo-600 hover:underline">
                {{ user.vendor.shop_name }}
              </RouterLink>
              <span v-else class="text-slate-400">—</span>
            </dd>
            <dt class="text-slate-500 font-semibold">Tham gia</dt>
            <dd class="m-0 text-slate-700">{{ formatDate(user.created_at) }}</dd>
          </dl>
        </div>

        <div class="mt-5 flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <Button label="Chi tiết" icon="pi pi-eye" size="small" outlined @click="router.push({ name: 'admin-user-detail', params: { id: user.id } })" />
          <Button v-if="user.role !== 'admin'" label="Thu hồi phiên" icon="pi pi-sign-out" size="small" severity="danger" outlined :loading="terminatingUserId === user.id" @click="terminateSessions(user)" />
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.admin-users {
  max-width: 100%;
}

.shadow-xs { box-shadow: 0px 2px 4px rgba(26,58,90,0.04); }

/* ═══ PAGE HEADER ═══ */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}
.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin: 0;
}
.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0;
}

.header-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: linear-gradient(135deg, #eef2ff, #e0e7ff);
  border-radius: 10px;
  color: #4338ca;
  font-weight: 600;
  font-size: 13px;
}
.header-badge i {
  font-size: 14px;
}

/* ═══ TABLE CARD ═══ */
.table-card {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* User ID */
.user-id {
  font-weight: 600;
  font-size: 13px;
  color: #94a3b8;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}

/* User cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar-badge {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}

.role-admin {
  background: linear-gradient(135deg, #fecdd3, #fda4af);
  color: #be123c;
}
.role-vendor {
  background: linear-gradient(135deg, #bfdbfe, #93c5fd);
  color: #1d4ed8;
}
.role-customer {
  background: linear-gradient(135deg, #bbf7d0, #86efac);
  color: #15803d;
}

.user-meta {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.user-name-text {
  font-weight: 600;
  font-size: 14px;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-email-text {
  font-size: 12px;
  color: #94a3b8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Vendor name */
.vendor-name {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
}

.date-text {
  font-size: 13px;
  color: #475569;
}

/* Empty state */
.empty-state {
  text-align: center;
  padding: 48px 24px;
  color: #94a3b8;
}
.empty-state i {
  font-size: 48px;
  margin-bottom: 12px;
  display: block;
}

/* ═══ RESPONSIVE ═══ */
@media (max-width: 640px) {
  .page-header {
    flex-direction: column;
    gap: 12px;
  }
  .user-cell {
    gap: 8px;
  }
}
</style>
