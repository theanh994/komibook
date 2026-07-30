<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const organizations = ref([])
const relationships = ref([])

const load = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/admin/organization-reviews')
    organizations.value = response.data.data.organizations?.data || []
    relationships.value = response.data.data.relationships?.data || []
  } finally {
    loading.value = false
  }
}

const decideOrganization = async (organization, toStatus) => {
  const reason = toStatus === 'verified' ? null : window.prompt('Nhập lý do quyết định:')
  if (toStatus !== 'verified' && !reason) return
  await apiClient.patch(`/api/admin/organizations/${organization.id}/transition`, { to_status: toStatus, reason })
  toast.add({ severity: 'success', summary: 'Đã cập nhật tổ chức', life: 2500 })
  await load()
}

const decideRelationship = async (relationship, toStatus) => {
  const reason = toStatus === 'verified' ? null : window.prompt('Nhập lý do quyết định:')
  if (toStatus !== 'verified' && !reason) return
  await apiClient.patch(`/api/admin/organization-relationships/${relationship.id}/transition`, { to_status: toStatus, reason })
  toast.add({ severity: 'success', summary: 'Đã cập nhật quan hệ', life: 2500 })
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
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"><div><h3 class="font-bold">{{ organization.display_name }}</h3><p class="text-sm text-on-surface-variant">{{ organization.legal_name }} · {{ organization.organization_types?.join(', ') }}</p></div><div class="flex flex-wrap gap-2"><span class="rounded-full bg-surface-container px-3 py-2 text-sm">{{ organization.status }}</span><Button v-if="organization.status !== 'verified'" label="Xác minh" icon="pi pi-check" class="min-h-11" @click="decideOrganization(organization, 'verified')" /><Button label="Tạm dừng" severity="danger" outlined class="min-h-11" @click="decideOrganization(organization, 'suspended')" /></div></div>
        </article>
      </section>
      <section class="space-y-4">
        <h2 class="text-xl font-bold">Quan hệ với gian hàng</h2>
        <div v-if="!relationships.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 text-on-surface-variant">Không có quan hệ cần hiển thị.</div>
        <article v-for="relationship in relationships" :key="relationship.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"><div><h3 class="font-bold">{{ relationship.vendor?.shop_name }} → {{ relationship.organization?.display_name }}</h3><p class="text-sm text-on-surface-variant">{{ relationship.role }} · {{ relationship.status }}</p></div><div class="flex flex-wrap gap-2"><Button v-if="relationship.status !== 'verified'" label="Duyệt quan hệ" icon="pi pi-check" class="min-h-11" @click="decideRelationship(relationship, 'verified')" /><Button label="Yêu cầu bổ sung" severity="secondary" outlined class="min-h-11" @click="decideRelationship(relationship, 'changes_requested')" /><Button label="Thu hồi" severity="danger" outlined class="min-h-11" @click="decideRelationship(relationship, 'revoked')" /></div></div>
        </article>
      </section>
    </template>
  </main>
</template>
