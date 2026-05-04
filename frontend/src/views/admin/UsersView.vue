<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Select from 'primevue/select'

const toast = useToast()

// ─── State ───
const users = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyParams = ref({ first: 0, rows: 15, page: 1 })

// Track which user is being updated
const updatingUserId = ref(null)

// ─── Role Config ───
const roleMap = {
  admin:    { label: 'Admin',    severity: 'danger',  icon: 'pi pi-shield' },
  vendor:   { label: 'Vendor',   severity: 'info',    icon: 'pi pi-shop' },
  customer: { label: 'Customer', severity: 'success', icon: 'pi pi-user' },
}

const roleOptions = [
  { label: 'Customer', value: 'customer' },
  { label: 'Vendor', value: 'vendor' },
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
      params: { page: lazyParams.value.page, per_page: lazyParams.value.rows },
    })
    users.value = res.data.data
    totalRecords.value = res.data.meta?.total || res.data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách người dùng.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const updateRole = async (user, newRole) => {
  if (user.role === newRole) return
  updatingUserId.value = user.id
  try {
    const res = await apiClient.patch(`/api/admin/users/${user.id}/role`, {
      role: newRole,
    })
    // Update local data
    const idx = users.value.findIndex(u => u.id === user.id)
    if (idx !== -1) {
      users.value[idx] = res.data.data
    }
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã cập nhật ${user.name} thành ${newRole}.`,
      life: 3000,
    })
  } catch (e) {
    const msg = e.response?.data?.message || 'Không thể cập nhật quyền.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    updatingUserId.value = null
  }
}

const onPage = (event) => {
  lazyParams.value = { ...event, page: event.page + 1 }
  fetchUsers()
}

onMounted(fetchUsers)
</script>

<template>
  <div class="admin-users">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý người dùng</h1>
        <p class="page-subtitle">Xem và phân quyền cho toàn bộ tài khoản trên hệ thống</p>
      </div>
      <div class="header-badge">
        <i class="pi pi-users"></i>
        <span>{{ totalRecords }} users</span>
      </div>
    </div>

    <!-- Data Table Card -->
    <div class="table-card">
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
            <p>Chưa có người dùng nào.</p>
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

        <!-- Role (Editable) -->
        <Column header="Quyền hạn" style="min-width: 180px">
          <template #body="{ data }">
            <!-- Admin role: chỉ hiện badge, không cho sửa -->
            <div v-if="data.role === 'admin'" class="admin-badge-wrap">
              <Tag
                :severity="getRole(data.role).severity"
                :value="getRole(data.role).label"
                :icon="getRole(data.role).icon"
                rounded
              />
              <i class="pi pi-lock lock-icon" v-tooltip="'Không thể thay đổi quyền Admin'"></i>
            </div>
            <!-- Non-admin: dropdown để đổi role -->
            <div v-else class="role-select-wrap">
              <Select
                :modelValue="data.role"
                @update:modelValue="(val) => updateRole(data, val)"
                :options="roleOptions"
                optionLabel="label"
                optionValue="value"
                :loading="updatingUserId === data.id"
                :disabled="updatingUserId === data.id"
                class="role-select"
                placeholder="Chọn quyền"
              />
            </div>
          </template>
        </Column>

        <!-- Vendor Info -->
        <Column header="Gian hàng" style="min-width: 160px">
          <template #body="{ data }">
            <span v-if="data.vendor" class="vendor-name">
              <i class="pi pi-shop vendor-shop-icon"></i>
              {{ data.vendor.shop_name }}
            </span>
            <span v-else class="no-vendor">—</span>
          </template>
        </Column>

        <!-- Ngày đăng ký -->
        <Column header="Ngày tham gia" style="min-width: 130px">
          <template #body="{ data }">
            <span class="date-text">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>
      </DataTable>
    </div>
  </div>
</template>

<style scoped>
.admin-users {
  max-width: 1200px;
  margin: 0 auto;
}

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

/* Admin badge */
.admin-badge-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.lock-icon {
  font-size: 12px;
  color: #cbd5e1;
}

/* Role select */
.role-select-wrap {
  max-width: 150px;
}
.role-select {
  width: 100%;
}

/* Vendor name */
.vendor-name {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #334155;
}
.vendor-shop-icon {
  color: #6366f1;
  font-size: 12px;
}
.no-vendor {
  color: #cbd5e1;
  font-size: 13px;
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
