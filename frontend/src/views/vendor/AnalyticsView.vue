<script setup>
import { ref } from 'vue'

const metrics = ref([
  { id: 1, title: 'Tổng lượt đọc', value: '124,592', change: '+12.5%', isUp: true, icon: 'auto_stories', color: 'from-blue-500 to-indigo-500' },
  { id: 2, title: 'Độc giả trung thành', value: '8,241', change: '+5.2%', isUp: true, icon: 'workspace_premium', color: 'from-amber-400 to-orange-500' },
  { id: 3, title: 'Thời gian đọc TB', value: '45 phút', change: '-2.1%', isUp: false, icon: 'timer', color: 'from-emerald-400 to-teal-500' },
  { id: 4, title: 'Tỷ lệ hoàn thành', value: '68.4%', change: '+8.4%', isUp: true, icon: 'flag', color: 'from-purple-500 to-pink-500' }
])

const demographics = ref([
  { age: '18-24', percentage: 45, color: 'bg-indigo-500' },
  { age: '25-34', percentage: 30, color: 'bg-blue-400' },
  { age: '35-44', percentage: 15, color: 'bg-teal-400' },
  { age: '45+', percentage: 10, color: 'bg-slate-300' }
])

const topBooks = ref([
  { title: 'Bí Mật Của Gió', reads: '45K', rating: 4.9, cover: 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?q=80&w=200&auto=format&fit=crop' },
  { title: 'Hành Trình Về Phương Đông', reads: '32K', rating: 4.8, cover: 'https://images.unsplash.com/photo-1512820790803-83ca734da794?q=80&w=200&auto=format&fit=crop' },
  { title: 'Tâm Lý Học Tội Phạm', reads: '28K', rating: 4.7, cover: 'https://images.unsplash.com/photo-1589829085413-56de8ae18c73?q=80&w=200&auto=format&fit=crop' }
])
</script>

<template>
  <div class="px-md md:px-xl pb-xl max-w-container-max mx-auto pt-6">
    <div class="flex justify-between items-end mb-lg">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-primary">Phân tích hành vi độc giả</h1>
        <p class="text-on-surface-variant mt-xs">Tổng quan hiệu suất cho "The Art of Immutable Design"</p>
      </div>
      <div class="flex gap-sm">
        <button class="border border-outline text-on-surface px-md py-xs rounded-lg font-label-md text-label-md flex items-center gap-xs hover:bg-surface-container-low transition-colors">
          <span class="material-symbols-outlined text-[18px]">calendar_month</span>
          30 Ngày qua
        </button>
      </div>
    </div>
    <!-- Bento Grid Layout -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
      <!-- Book Context Card (Span 4) -->
      <div class="md:col-span-4 bg-surface-container-lowest rounded-xl p-md shadow-[0px_12px_24px_0px_rgba(26,58,90,0.04)] border border-surface-container-high flex flex-col gap-md">
        <div class="flex gap-md">
          <img alt="Book Cover" class="w-[80px] h-[120px] object-cover rounded-lg shadow-sm border border-outline-variant/30" src="https://images.unsplash.com/photo-1544947950-fa07a98d237f?q=80&w=200&auto=format&fit=crop"/>
          <div class="flex flex-col justify-center">
            <h3 class="font-headline-md text-[18px] text-primary leading-snug">The Art of Immutable Design</h3>
            <p class="text-on-surface-variant text-body-md mt-xs">Sarah Jenkins</p>
            <div class="flex items-center gap-xs mt-sm">
              <span class="material-symbols-outlined text-secondary text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="font-label-md text-label-md text-on-surface">4.8</span>
              <span class="text-on-surface-variant text-[12px]">(124)</span>
            </div>
          </div>
        </div>
        <div class="h-[1px] w-full bg-surface-container-high"></div>
        <div class="grid grid-cols-2 gap-sm">
          <div class="bg-surface-container-low p-sm rounded-lg">
            <span class="text-on-surface-variant text-[12px] block">Tổng lượt xem</span>
            <span class="font-headline-md text-[20px] text-primary">12,450</span>
          </div>
          <div class="bg-surface-container-low p-sm rounded-lg">
            <span class="text-on-surface-variant text-[12px] block">Lượt đọc thử</span>
            <span class="font-headline-md text-[20px] text-primary">3,120</span>
          </div>
        </div>
      </div>
      <!-- Preview Rate & Conversion (Span 8) -->
      <div class="md:col-span-8 grid grid-cols-1 md:grid-cols-2 gap-gutter">
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-[0px_12px_24px_0px_rgba(26,58,90,0.04)] border border-surface-container-high flex flex-col justify-center items-center text-center">
          <h4 class="font-label-md text-label-md text-on-surface-variant mb-xs">Tỷ lệ đọc thử (Preview Rate)</h4>
          <div class="relative w-32 h-32 flex items-center justify-center my-md">
            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
              <path class="text-surface-container-high" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-dasharray="100, 100" stroke-width="3"></path>
              <path class="text-primary" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-dasharray="25, 100" stroke-linecap="round" stroke-width="3"></path>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="font-headline-lg text-[28px] text-primary leading-none">25%</span>
            </div>
          </div>
          <p class="text-body-md text-on-surface-variant text-sm"><span class="text-secondary font-medium">↑ 2.4%</span> so với tháng trước</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-[0px_12px_24px_0px_rgba(26,58,90,0.04)] border border-surface-container-high flex flex-col justify-between">
          <h4 class="font-label-md text-label-md text-on-surface-variant mb-md">Phễu chuyển đổi</h4>
          <div class="space-y-md w-full">
            <div>
              <div class="flex justify-between text-[12px] mb-xs">
                <span class="text-on-surface">Lượt xem trang</span>
                <span class="font-medium text-primary">12,450</span>
              </div>
              <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
                <div class="bg-primary-fixed-dim h-full w-full"></div>
              </div>
            </div>
            <div>
              <div class="flex justify-between text-[12px] mb-xs">
                <span class="text-on-surface">Bắt đầu đọc thử</span>
                <span class="font-medium text-primary">3,120</span>
              </div>
              <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
                <div class="bg-primary-container h-full w-[25%]"></div>
              </div>
            </div>
            <div>
              <div class="flex justify-between text-[12px] mb-xs">
                <span class="text-on-surface">Thêm vào giỏ / Mua</span>
                <span class="font-medium text-primary">480</span>
              </div>
              <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
                <div class="bg-primary h-full w-[15%]"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Views Chart (Span 12) -->
      <div class="md:col-span-12 bg-surface-container-lowest rounded-xl p-lg shadow-[0px_12px_24px_0px_rgba(26,58,90,0.04)] border border-surface-container-high">
        <div class="flex justify-between items-center mb-lg">
          <h3 class="font-headline-md text-[20px] text-primary">Lượt xem theo ngày</h3>
          <div class="flex gap-sm">
            <span class="flex items-center gap-xs text-[12px] text-on-surface-variant"><div class="w-3 h-3 rounded-sm bg-primary"></div> Lượt xem</span>
            <span class="flex items-center gap-xs text-[12px] text-on-surface-variant"><div class="w-3 h-3 rounded-sm bg-primary-fixed-dim"></div> Đọc thử</span>
          </div>
        </div>
        <!-- CSS Bar Chart Representation -->
        <div class="h-64 flex items-end justify-between gap-xs md:gap-sm mt-md px-sm relative">
          <!-- Grid lines -->
          <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-10">
            <div class="w-full h-[1px] bg-primary"></div>
            <div class="w-full h-[1px] bg-primary"></div>
            <div class="w-full h-[1px] bg-primary"></div>
            <div class="w-full h-[1px] bg-primary"></div>
            <div class="w-full h-[1px] bg-primary"></div>
          </div>
          <!-- Bars (Sample Data) -->
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[10%] rounded-t-sm group-hover:opacity-80 transition-opacity"></div>
            <div class="w-full md:w-3/4 bg-primary h-[40%] rounded-t-sm group-hover:opacity-80 transition-opacity"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">01</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[15%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[55%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">02</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[20%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[70%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">03</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[12%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[45%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">04</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[25%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[85%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">05</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[30%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[95%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">06</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[18%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[60%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">07</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[15%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[50%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">08</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[22%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[75%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">09</span>
          </div>
          <div class="w-full flex flex-col justify-end items-center group">
            <div class="w-full md:w-3/4 bg-primary-fixed-dim h-[10%] rounded-t-sm"></div>
            <div class="w-full md:w-3/4 bg-primary h-[35%] rounded-t-sm"></div>
            <span class="text-[10px] text-on-surface-variant mt-xs hidden md:block">10</span>
          </div>
        </div>
      </div>
      <!-- Recent Reviews (Span 12) -->
      <div class="md:col-span-12">
        <h3 class="font-headline-md text-[20px] text-primary mb-md">Đánh giá / Nhận xét mới nhất</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-md">
          <!-- Review Card 1 -->
          <div class="bg-surface-container-lowest rounded-xl p-md shadow-[0px_4px_12px_0px_rgba(26,58,90,0.02)] border border-surface-container-high">
            <div class="flex items-center justify-between mb-sm">
              <div class="flex items-center gap-sm">
                <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center text-primary font-medium text-[14px]">MN</div>
                <span class="font-label-md text-label-md text-on-surface">Minh Ngọc</span>
              </div>
              <span class="text-[12px] text-on-surface-variant">2 giờ trước</span>
            </div>
            <div class="flex gap-[2px] mb-sm text-secondary">
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
            </div>
            <p class="font-body-md text-body-md text-on-surface line-clamp-3">"Cuốn sách thực sự mở mang tầm mắt về cách cấu trúc thiết kế. Phần chương 3 giải thích rất rõ về immutable principles. Đọc thử xong phải mua ngay bản full."</p>
          </div>
          <!-- Review Card 2 -->
          <div class="bg-surface-container-lowest rounded-xl p-md shadow-[0px_4px_12px_0px_rgba(26,58,90,0.02)] border border-surface-container-high">
            <div class="flex items-center justify-between mb-sm">
              <div class="flex items-center gap-sm">
                <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center text-primary font-medium text-[14px]">TH</div>
                <span class="font-label-md text-label-md text-on-surface">Tuấn Hoàng</span>
              </div>
              <span class="text-[12px] text-on-surface-variant">1 ngày trước</span>
            </div>
            <div class="flex gap-[2px] mb-sm text-secondary">
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px] text-outline-variant" style="font-variation-settings: 'FILL' 0;">star</span>
            </div>
            <p class="font-body-md text-body-md text-on-surface line-clamp-3">"Nội dung cô đọng, dễ hiểu. Tuy nhiên tôi muốn có thêm nhiều ví dụ thực tế hơn ở phần cuối. Bản preview cho thấy chất lượng sách khá tốt so với mặt bằng chung."</p>
          </div>
          <!-- Review Card 3 -->
          <div class="bg-surface-container-lowest rounded-xl p-md shadow-[0px_4px_12px_0px_rgba(26,58,90,0.02)] border border-surface-container-high hidden lg:block">
            <div class="flex items-center justify-between mb-sm">
              <div class="flex items-center gap-sm">
                <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center text-primary font-medium text-[14px]">LAN</div>
                <span class="font-label-md text-label-md text-on-surface">Phương Lan</span>
              </div>
              <span class="text-[12px] text-on-surface-variant">3 ngày trước</span>
            </div>
            <div class="flex gap-[2px] mb-sm text-secondary">
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
              <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
            </div>
            <p class="font-body-md text-body-md text-on-surface line-clamp-3">"Khuyên dùng cho các bạn mới bước chân vào ngành UI/UX. Hệ thống kiến thức rất logic. Mình đã đọc đi đọc lại phần màu sắc 3 lần."</p>
          </div>
        </div>
        <div class="mt-md text-center">
          <button class="text-primary font-label-md text-label-md hover:underline bg-transparent px-md py-sm">Xem tất cả 124 đánh giá</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up {
  opacity: 0;
  transform: translateY(20px);
  animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.fill-1 {
  font-variation-settings: 'FILL' 1;
}
</style>
