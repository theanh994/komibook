<script setup>
import { ref, onMounted } from 'vue'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import InputText from 'primevue/inputtext'

const articles = ref([])
const loading = ref(true)
const searchVal = ref('')
const selectedArticle = ref(null)

const fetchArticles = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/help-center/articles', {
      params: { search: searchVal.value }
    })
    if (res.data?.status === 'success') {
      articles.value = res.data.data
    }
  } catch (e) {
    console.error('Không tải được FAQs', e)
    // Fallback Mock FAQs
    articles.value = [
      { id: 1, title: 'Tôi làm thế nào để đọc sách Ebook ngoại tuyến?', category_name: 'Đọc sách', content: 'Để đọc Ebook ngoại tuyến không cần mạng, bạn hãy mở ứng dụng KomiBook trên điện thoại, truy cập "Tủ sách của tôi", chọn cuốn sách mong muốn và nhấn "Tải về đọc ngoại tuyến". Hệ thống sẽ mã hóa và lưu trữ sách trực tiếp trên thiết bị của bạn.', views_count: 340, helpful_count: 24 },
      { id: 2, title: 'Komibook hỗ trợ in ấn những tài liệu nào?', category_name: 'Bản quyền & In ấn', content: 'Theo chính sách bảo vệ bản quyền, bạn chỉ có thể thực hiện in ấn các trang sách giấy hoặc tài liệu mở được tác giả cho phép in. Vui lòng bấm vào biểu tượng máy in trong Trình đọc sách, đọc và ký xác nhận Cam kết tôn trọng quyền sở hữu trí tuệ để tiến hành in ấn.', views_count: 180, helpful_count: 15 },
      { id: 3, title: 'Chính sách nhuận bút dành cho Tác giả tự xuất bản', category_name: 'Đối tác & Tác giả', content: 'Komibook chia sẻ doanh thu lên tới 75% giá trị mỗi chương sách/ebook lẻ bán ra dành cho Tác giả được xác thực. Doanh thu sẽ được đối soát tự động hàng tháng và thanh toán vào tài khoản ngân hàng của bạn từ ngày 05 đến 10 hàng tháng.', views_count: 512, helpful_count: 45 },
    ]
  } finally {
    loading.value = false
  }
}

const selectArticle = (art) => {
  selectedArticle.value = art
  // Increment view counts mock or API
  apiClient.get(`/api/help-center/articles/${art.id}`).catch(() => {})
}

const rateHelpful = async (art) => {
  try {
    await apiClient.post(`/api/help-center/articles/${art.id}/helpful`)
    art.helpful_count++
    alert('Cảm ơn bạn đã gửi phản hồi hữu ích!')
  } catch (e) {
    art.helpful_count++
    alert('Cảm ơn phản hồi đánh giá của bạn!')
  }
}

onMounted(() => {
  fetchArticles()
})
</script>

<template>
  <div class="help-center min-h-screen bg-slate-50 py-12 px-4 md:px-8">
    <div class="max-w-4xl mx-auto space-y-8">
      
      <!-- Search Banner -->
      <div class="text-center py-12 bg-indigo-900 text-white rounded-3xl relative overflow-hidden shadow-md px-6">
        <div class="absolute inset-0 opacity-10 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9IiNmZmYiLz48L3N2Zz4=')]"></div>
        <div class="relative z-10 max-w-2xl mx-auto space-y-4">
          <h1 class="text-3xl font-black">Trung tâm Trợ giúp KomiBook</h1>
          <p class="text-indigo-200 text-sm">Tìm kiếm câu trả lời nhanh chóng cho mọi thắc mắc của bạn về tài khoản, bản quyền và in ấn.</p>
          
          <div class="flex gap-2 bg-white/10 p-1.5 rounded-full border border-indigo-700 max-w-lg mx-auto">
            <input 
              v-model="searchVal" 
              type="text" 
              placeholder="Nhập câu hỏi, từ khóa cần tìm..." 
              class="flex-grow bg-transparent text-white placeholder-indigo-300 border-none outline-none focus:ring-0 px-4 text-sm"
              @keyup.enter="fetchArticles"
            />
            <Button icon="pi pi-search" class="p-button-rounded p-button-primary bg-white text-indigo-950 p-2 border-none" @click="fetchArticles" />
          </div>
        </div>
      </div>

      <!-- Main Layout -->
      <div v-if="selectedArticle" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6">
        <Button label="Quay lại danh mục" icon="pi pi-arrow-left" class="p-button-text p-button-sm text-xs text-indigo-600" @click="selectedArticle = null" />
        
        <div class="space-y-4">
          <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest block bg-indigo-50 px-2 py-0.5 rounded w-max">
            {{ selectedArticle.category_name }}
          </span>
          <h2 class="text-2xl font-extrabold text-slate-800">{{ selectedArticle.title }}</h2>
          <div class="text-slate-600 leading-relaxed text-sm whitespace-pre-line border-t border-slate-100 pt-4">
            {{ selectedArticle.content }}
          </div>
        </div>

        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex justify-between items-center text-xs">
          <span class="text-slate-500">Bài viết này có giúp ích cho bạn không?</span>
          <div class="flex gap-2">
            <Button label="Có, rất hữu ích" icon="pi pi-thumbs-up" class="p-button-outlined p-button-success p-button-sm text-xs" @click="rateHelpful(selectedArticle)" />
          </div>
        </div>
      </div>

      <!-- FAQ categories -->
      <div v-else class="space-y-6">
        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
          <i class="pi pi-question-circle text-indigo-600"></i> Các câu hỏi thường gặp
        </h2>

        <div v-if="loading" class="flex justify-center p-8">
          <i class="pi pi-spin pi-spinner text-2xl text-indigo-600"></i>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div 
            v-for="art in articles" 
            :key="art.id" 
            class="bg-white p-5 rounded-2xl border border-slate-200 hover:border-indigo-400 cursor-pointer shadow-sm hover:shadow-md transition-all flex flex-col justify-between"
            @click="selectArticle(art)"
          >
            <div>
              <span class="text-[9px] font-bold text-indigo-600 uppercase tracking-widest block mb-2">{{ art.category_name }}</span>
              <h3 class="font-bold text-slate-800 text-sm leading-snug">{{ art.title }}</h3>
            </div>
            <div class="flex justify-between items-center text-[10px] text-slate-400 mt-4 pt-2 border-t border-slate-100">
              <span>Lượt xem: {{ art.views_count }}</span>
              <span class="text-indigo-600 font-semibold flex items-center gap-1">Xem chi tiết <i class="pi pi-angle-right"></i></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.help-center {
  font-family: 'Inter', sans-serif;
}
</style>
