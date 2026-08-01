<script setup>
defineProps({
  text: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    default: 'Xem thông tin chi tiết',
  },
})
</script>

<template>
  <button
    type="button"
    class="inline-flex h-5 w-5 shrink-0 cursor-help items-center justify-center rounded-full border-0 bg-transparent p-0 align-middle leading-none text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-fixed-dim"
    :aria-label="label"
    :title="text"
    v-tooltip.top="text"
  >
    <span class="material-symbols-outlined !text-[16px] !leading-none" aria-hidden="true">info</span>
  </button>
</template>
