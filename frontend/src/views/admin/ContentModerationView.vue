<script setup>
import { ref } from 'vue'
import ReviewModerationView from '@/views/admin/ReviewModerationView.vue'
import ArticleCommentsView from '@/views/admin/ArticleCommentsView.vue'

const activeTab = ref('reviews')
const tabs = [
  { key: 'reviews', label: 'Đánh giá sách', icon: 'star' },
  { key: 'comments', label: 'Bình luận bài viết', icon: 'forum' },
]
</script>

<template>
  <section class="space-y-6">
    <header>
      <p class="text-sm font-bold uppercase tracking-wider text-secondary">Trung tâm kiểm duyệt</p>
      <h1 class="mt-2 text-3xl font-bold text-primary">Kiểm duyệt nội dung độc giả</h1>
      <p class="mt-2 max-w-3xl text-on-surface-variant">Xử lý đánh giá sách và bình luận Newsroom trong cùng một không gian, nhưng vẫn giữ lịch sử quyết định riêng.</p>
    </header>
    <nav class="inline-flex max-w-full gap-1 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-1" aria-label="Loại nội dung kiểm duyệt">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="inline-flex min-h-11 items-center gap-2 rounded-lg px-4 font-bold transition-colors"
        :class="activeTab === tab.key ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container'"
        :aria-pressed="activeTab === tab.key"
        @click="activeTab = tab.key"
      >
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ tab.icon }}</span>{{ tab.label }}
      </button>
    </nav>
    <ReviewModerationView v-if="activeTab === 'reviews'" />
    <ArticleCommentsView v-else />
  </section>
</template>
