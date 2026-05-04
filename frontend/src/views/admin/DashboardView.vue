<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()

// ─── State ───
const stats = ref({
  total_users: 0,
  total_vendors: 0,
  total_books: 0,
  total_revenue: 0,
  total_orders: 0,
  pending_orders: 0,
})
const loading = ref(true)

// ─── Stats Cards Config ───
const cards = [
  {
    key: 'total_users',
    label: 'Tổng người dùng',
    icon: 'pi pi-users',
    gradient: 'linear-gradient(135deg, #6366f1, #818cf8)',
    shadow: 'rgba(99, 102, 241, 0.35)',
    format: 'number',
  },
  {
    key: 'total_vendors',
    label: 'Nhà bán hàng',
    icon: 'pi pi-shop',
    gradient: 'linear-gradient(135deg, #f59e0b, #fbbf24)',
    shadow: 'rgba(245, 158, 11, 0.35)',
    format: 'number',
  },
  {
    key: 'total_books',
    label: 'Tổng số sách',
    icon: 'pi pi-book',
    gradient: 'linear-gradient(135deg, #10b981, #34d399)',
    shadow: 'rgba(16, 185, 129, 0.35)',
    format: 'number',
  },
  {
    key: 'total_revenue',
    label: 'Doanh thu (hoàn thành)',
    icon: 'pi pi-wallet',
    gradient: 'linear-gradient(135deg, #ef4444, #f87171)',
    shadow: 'rgba(239, 68, 68, 0.35)',
    format: 'currency',
  },
]

// ─── Helpers ───
const formatValue = (value, format) => {
  if (format === 'currency') {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
  }
  return new Intl.NumberFormat('vi-VN').format(value)
}

// ─── Fetch ───
const fetchStats = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/stats')
    stats.value = res.data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu thống kê.', life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(fetchStats)
</script>

<template>
  <div class="admin-dashboard">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Tổng quan hệ thống</h1>
        <p class="page-subtitle">Bảng điều khiển quản trị — Dữ liệu toàn sàn KomiBook</p>
      </div>
      <button class="refresh-btn" @click="fetchStats" :disabled="loading">
        <i class="pi pi-refresh" :class="{ 'pi-spin': loading }"></i>
        Làm mới
      </button>
    </div>

    <!-- Stats Cards Grid -->
    <div class="stats-grid">
      <div
        v-for="card in cards"
        :key="card.key"
        class="stat-card"
        :style="{ '--card-shadow': card.shadow }"
      >
        <div class="stat-icon-wrap" :style="{ background: card.gradient }">
          <i :class="card.icon"></i>
        </div>
        <div class="stat-content">
          <span class="stat-label">{{ card.label }}</span>
          <span v-if="loading" class="stat-value stat-skeleton">&nbsp;</span>
          <span v-else class="stat-value">{{ formatValue(stats[card.key], card.format) }}</span>
        </div>
        <div class="stat-glow" :style="{ background: card.gradient }"></div>
      </div>
    </div>

    <!-- Secondary Stats -->
    <div class="secondary-grid">
      <div class="secondary-card">
        <div class="sec-icon pending-icon">
          <i class="pi pi-clock"></i>
        </div>
        <div class="sec-info">
          <span class="sec-value">{{ loading ? '—' : stats.pending_orders }}</span>
          <span class="sec-label">Đơn hàng chờ xử lý</span>
        </div>
      </div>
      <div class="secondary-card">
        <div class="sec-icon orders-icon">
          <i class="pi pi-shopping-cart"></i>
        </div>
        <div class="sec-info">
          <span class="sec-value">{{ loading ? '—' : stats.total_orders }}</span>
          <span class="sec-label">Tổng đơn hàng</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-dashboard {
  max-width: 1100px;
  margin: 0 auto;
}

/* ═══ PAGE HEADER ═══ */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 32px;
}
.page-title {
  font-size: 26px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.03em;
  margin: 0;
}
.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0;
}
.refresh-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #475569;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.refresh-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #cbd5e1;
  color: #1e293b;
}
.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ═══ STATS GRID ═══ */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 24px;
}

.stat-card {
  position: relative;
  background: white;
  border-radius: 18px;
  padding: 24px;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 16px;
  overflow: hidden;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px var(--card-shadow, rgba(0,0,0,0.08));
  border-color: transparent;
}

.stat-icon-wrap {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 22px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
  flex-shrink: 0;
}

.stat-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: relative;
  z-index: 1;
}

.stat-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #94a3b8;
}

.stat-value {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.stat-skeleton {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
  height: 34px;
  width: 80%;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Glow effect */
.stat-glow {
  position: absolute;
  right: -30px;
  bottom: -30px;
  width: 100px;
  height: 100px;
  border-radius: 50%;
  opacity: 0.08;
  pointer-events: none;
  filter: blur(24px);
}

/* ═══ SECONDARY GRID ═══ */
.secondary-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.secondary-card {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all 0.2s ease;
}
.secondary-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.sec-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.pending-icon {
  background: #fef3c7;
  color: #d97706;
}

.orders-icon {
  background: #ede9fe;
  color: #7c3aed;
}

.sec-info {
  display: flex;
  flex-direction: column;
}

.sec-value {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.02em;
}

.sec-label {
  font-size: 13px;
  color: #64748b;
  margin-top: 2px;
}

/* ═══ RESPONSIVE ═══ */
@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  .secondary-grid {
    grid-template-columns: 1fr;
  }
  .page-header {
    flex-direction: column;
    gap: 12px;
  }
}
</style>
