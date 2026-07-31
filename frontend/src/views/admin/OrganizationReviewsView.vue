<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const organizations = ref([])
const relationships = ref([])
const distributionAgreements = ref([])
const isApproval = (status) => ['verified', 'demo_accepted'].includes(status)

const load = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/admin/organization-reviews')
    organizations.value = response.data.data.organizations?.data || []
    relationships.value = response.data.data.relationships?.data || []
    distributionAgreements.value = response.data.data.distribution_agreements?.data || []
  } finally {
    loading.value = false
  }
}

const decideOrganization = async (organization, toStatus) => {
  const reason = isApproval(toStatus) ? null : window.prompt('Nhập lý do quyết định:')
  if (!isApproval(toStatus) && !reason) return
  await apiClient.patch(`/api/admin/organizations/${organization.id}/transition`, { to_status: toStatus, reason })
  toast.add({ severity: 'success', summary: 'Đã cập nhật tổ chức', life: 2500 })
  await load()
}

const decideRelationship = async (relationship, toStatus) => {
  const reason = isApproval(toStatus) ? null : window.prompt('Nhập lý do quyết định:')
  if (!isApproval(toStatus) && !reason) return
  await apiClient.patch(`/api/admin/organization-relationships/${relationship.id}/transition`, { to_status: toStatus, reason })
  toast.add({ severity: 'success', summary: 'Đã cập nhật quan hệ', life: 2500 })
  await load()
}

const decideDistributionAgreement = async (agreement, toStatus) => {
  const reason = isApproval(toStatus) ? null : window.prompt('Nhập lý do quyết định:')
  if (!isApproval(toStatus) && !reason) return
  await apiClient.patch(`/api/admin/distribution-agreements/${agreement.id}/transition`, { to_status: toStatus, reason })
  toast.add({ severity: 'success', summary: 'Đã cập nhật thỏa thuận phân phối', life: 2500 })
  await load()
}

onMounted(load)
</script>

<template>
  <main id="main-content" class="space-y-8" tabindex="-1">
    <header><p class="text-sm font-semibold uppercase tracking-wider text-primary">Kiểm duyệt đối tác</p><h1 class="mt-1 text-3xl font-bold text-on-surface">Tổ chức & quan hệ cung ứng</h1><p class="mt-2 text-on-surface-variant">Xác minh pháp nhân riêng với quan hệ Nhà bán–Nhà xuất bản–Nhà cung cấp.</p></header>
    <div v-if="loading" class="h-64 animate-pulse rounded-xl bg-surface-container"></div>
    <template v-else>
      <section class="space-y-4">
        <h2 class="text-xl font-bold">Hồ sơ tổ chức</h2>
        <div v-if="!organizations.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 text-on-surface-variant">Không có hồ sơ cần hiển thị.</div>
        <article v-for="organization in organizations" :key="organization.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"><div><h3 class="font-bold">{{ organization.display_name }}</h3><p class="text-sm text-on-surface-variant">{{ organization.legal_name }} · {{ organization.organization_types?.join(', ') }}</p><p v-if="organization.data_mode === 'demo'" class="mt-1 text-xs font-semibold text-amber-800">Dữ liệu mô phỏng – không xác minh pháp lý</p></div><div class="flex flex-wrap gap-2"><span class="rounded-full bg-surface-container px-3 py-2 text-sm">{{ organization.status }}</span><Button v-if="organization.data_mode === 'demo' && organization.status !== 'demo_accepted'" label="Duyệt mô phỏng" icon="pi pi-check" class="min-h-11" @click="decideOrganization(organization, 'demo_accepted')" /><Button v-else-if="organization.data_mode !== 'demo' && organization.status !== 'verified'" label="Xác minh" icon="pi pi-check" class="min-h-11" @click="decideOrganization(organization, 'verified')" /><Button label="Tạm dừng" severity="danger" outlined class="min-h-11" @click="decideOrganization(organization, 'suspended')" /></div></div>
        </article>
      </section>
      <section class="space-y-4">
        <h2 class="text-xl font-bold">Quan hệ với gian hàng</h2>
        <div v-if="!relationships.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 text-on-surface-variant">Không có quan hệ cần hiển thị.</div>
        <article v-for="relationship in relationships" :key="relationship.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"><div><h3 class="font-bold">{{ relationship.vendor?.shop_name }} → {{ relationship.organization?.display_name }}</h3><p class="text-sm text-on-surface-variant">{{ relationship.role }} · {{ relationship.status }}</p><p v-if="relationship.is_demo" class="mt-1 text-xs font-semibold text-amber-800">Quan hệ mô phỏng · {{ relationship.demo_reference }}</p></div><div class="flex flex-wrap gap-2"><Button v-if="relationship.is_demo && relationship.status !== 'demo_accepted'" label="Duyệt mô phỏng" icon="pi pi-check" class="min-h-11" @click="decideRelationship(relationship, 'demo_accepted')" /><Button v-else-if="!relationship.is_demo && relationship.status !== 'verified'" label="Duyệt quan hệ" icon="pi pi-check" class="min-h-11" @click="decideRelationship(relationship, 'verified')" /><Button label="Yêu cầu bổ sung" severity="secondary" outlined class="min-h-11" @click="decideRelationship(relationship, 'changes_requested')" /><Button label="Thu hồi" severity="danger" outlined class="min-h-11" @click="decideRelationship(relationship, 'revoked')" /></div></div>
        </article>
      </section>
      <section class="space-y-4" aria-labelledby="distribution-agreements-title">
        <div><h2 id="distribution-agreements-title" class="text-xl font-bold">Thỏa thuận NXB – Nhà phân phối</h2><p class="mt-1 text-sm text-on-surface-variant">Duyệt phạm vi phân phối trước khi Nhà bán gắn chuỗi cung ứng vào listing.</p></div>
        <div v-if="!distributionAgreements.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 text-on-surface-variant">Không có thỏa thuận cần hiển thị.</div>
        <article v-for="agreement in distributionAgreements" :key="agreement.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div><h3 class="font-bold">{{ agreement.publisher?.display_name }} → {{ agreement.distributor?.display_name }}</h3><p class="text-sm text-on-surface-variant">{{ agreement.scope?.coverage === 'books' ? 'Sách cụ thể' : 'Toàn catalog' }} · {{ agreement.status }}</p><p v-if="agreement.is_demo" class="mt-1 text-xs font-semibold text-amber-800">Thỏa thuận mô phỏng · {{ agreement.demo_reference }}</p></div>
            <div class="flex flex-wrap gap-2"><Button v-if="agreement.is_demo && agreement.status !== 'demo_accepted'" label="Duyệt mô phỏng" icon="pi pi-check" class="min-h-11" @click="decideDistributionAgreement(agreement, 'demo_accepted')" /><Button v-else-if="!agreement.is_demo && agreement.status !== 'verified'" label="Duyệt thỏa thuận" icon="pi pi-check" class="min-h-11" @click="decideDistributionAgreement(agreement, 'verified')" /><Button label="Yêu cầu bổ sung" severity="secondary" outlined class="min-h-11" @click="decideDistributionAgreement(agreement, 'changes_requested')" /><Button label="Thu hồi" severity="danger" outlined class="min-h-11" @click="decideDistributionAgreement(agreement, 'revoked')" /></div>
          </div>
        </article>
      </section>
    </template>
  </main>
</template>
