<template>
  <div class="min-h-screen bg-background text-on-surface antialiased flex flex-col font-outfit">
    <!-- Hero Header Section (Immersive MD3) -->
    <header class="relative pt-32 pb-24 px-gutter overflow-hidden border-b border-outline-variant/5 bg-surface-container-low/30">
      <!-- Animated Background Accents -->
      <div class="absolute top-[-20%] right-[-10%] w-[800px] h-[800px] bg-primary/5 blur-[120px] rounded-full animate-pulse"></div>
      <div class="absolute bottom-[-10%] left-[-5%] w-[600px] h-[600px] bg-secondary/5 blur-[100px] rounded-full"></div>
      
      <div class="max-w-[1400px] mx-auto relative z-10">
        <div class="flex flex-col items-center text-center space-y-8">
           <div class="inline-flex items-center gap-3 px-6 py-2.5 rounded-full bg-surface-container-high border border-outline-variant/10 shadow-sm animate-fade-in">
              <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
              <span class="text-[10px] font-black uppercase tracking-[0.3em] text-primary">KomiBook Editorial</span>
           </div>
           
           <h1 class="text-6xl md:text-8xl font-black text-on-surface tracking-tighter leading-none animate-fade-in delay-100">
             Tin tức <span class="text-primary italic font-serif">&</span> Sự kiện
           </h1>
           
           <p class="text-on-surface-variant max-w-2xl mx-auto text-lg md:text-xl leading-relaxed font-medium opacity-70 animate-fade-in delay-200">
             Nơi hội tụ những góc nhìn sâu sắc về thế giới văn học số, tin tức xuất bản và những câu chuyện truyền cảm hứng từ cộng đồng yêu sách.
           </p>

           <!-- Search Bar (MD3) -->
           <div class="w-full max-w-xl relative mt-8 animate-fade-in delay-300">
             <input 
               type="text" 
               v-model="searchQuery"
               placeholder="Tìm kiếm bài viết, tác giả..." 
               class="w-full pl-14 pr-6 py-5 bg-surface-container-highest/50 backdrop-blur-xl border border-outline-variant/10 rounded-[28px] focus:bg-white focus:ring-4 focus:ring-primary/10 transition-all outline-none text-base font-bold shadow-2xl"
             />
             <span class="material-symbols-outlined absolute left-5 top-1/2 -translate-y-1/2 text-primary text-[28px]">search</span>
           </div>
        </div>
      </div>
    </header>

    <main class="flex-grow w-full px-gutter max-w-[1400px] mx-auto py-xl md:py-xxl">
      <!-- Layout Grid -->
      <div class="flex flex-col lg:flex-row gap-xl">
        
        <!-- Main Content Area -->
        <div class="flex-1 min-w-0 space-y-xl">
          
          <!-- Category Quick Filters -->
          <div class="flex gap-3 overflow-x-auto no-scrollbar pb-4 sticky top-24 z-20 bg-background/80 backdrop-blur-md py-2">
            <button 
              v-for="cat in categories" 
              :key="cat"
              @click="activeCategory = cat"
              class="px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] transition-all border-none cursor-pointer whitespace-nowrap"
              :class="activeCategory === cat ? 'bg-primary text-on-primary shadow-xl shadow-primary/20 scale-105' : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface'"
            >
              {{ cat }}
            </button>
          </div>

          <!-- Featured Bento Grid Section -->
          <section v-if="activeCategory === 'Tất cả' && !searchQuery" class="grid grid-cols-1 md:grid-cols-12 gap-8 animate-fade-in delay-400">
            <!-- Large Featured Card -->
            <div @click="goToPost(featuredPost)" class="md:col-span-8 group relative aspect-[16/9] md:aspect-auto md:h-[600px] rounded-[48px] overflow-hidden border border-outline-variant/10 shadow-2xl cursor-pointer">
               <img :src="featuredPost.image" :alt="featuredPost.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-1000" />
               <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent flex flex-col justify-end p-8 md:p-14">
                  <div class="flex items-center gap-4 mb-6">
                    <span class="px-5 py-2 bg-primary text-on-primary text-[9px] font-black uppercase tracking-widest rounded-xl">Tiêu điểm</span>
                    <span class="text-white/60 text-[10px] font-black uppercase tracking-widest">{{ featuredPost.date }}</span>
                  </div>
                  <h2 class="text-3xl md:text-5xl font-black text-white leading-tight mb-6 group-hover:text-primary transition-colors">{{ featuredPost.title }}</h2>
                  <p class="text-white/70 text-lg max-w-2xl line-clamp-2 font-medium mb-8">{{ featuredPost.excerpt }}</p>
                  <div class="flex items-center gap-6">
                     <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-white font-black">K</div>
                        <span class="text-sm font-black text-white">Komi Editorial</span>
                     </div>
                     <div class="h-8 w-px bg-white/20"></div>
                     <div class="flex items-center gap-2 text-white/60">
                        <span class="material-symbols-outlined text-[20px]">timer</span>
                        <span class="text-xs font-bold">8 phút đọc</span>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Side Featured Items -->
            <div class="md:col-span-4 flex flex-col gap-8">
               <div v-for="post in posts.slice(0, 2)" :key="post.id" @click="goToPost(post)" class="flex-1 group relative rounded-[40px] overflow-hidden border border-outline-variant/10 shadow-xl cursor-pointer bg-surface-container-low">
                  <img :src="post.image" :alt="post.title" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000 opacity-60" />
                  <div class="absolute inset-0 bg-gradient-to-t from-surface-container-lowest via-transparent to-transparent p-8 flex flex-col justify-end">
                     <span class="text-primary text-[9px] font-black uppercase tracking-widest mb-2">{{ post.category }}</span>
                     <h3 class="text-xl font-black text-on-surface leading-tight group-hover:text-primary transition-colors line-clamp-2">{{ post.title }}</h3>
                  </div>
               </div>
            </div>
          </section>

          <!-- Standard Article Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8 pt-12 animate-fade-in delay-500">
            <article 
              v-for="post in filteredPosts" 
              :key="post.id" 
              @click="goToPost(post)"
              class="group flex flex-col bg-surface-container-lowest rounded-[40px] border border-outline-variant/10 overflow-hidden hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 cursor-pointer"
            >
              <div class="aspect-[4/3] overflow-hidden relative">
                <img :src="post.image" :alt="post.title" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" />
                <div class="absolute top-6 right-6">
                   <div class="w-10 h-10 rounded-full bg-white/90 backdrop-blur-md flex items-center justify-center text-primary shadow-lg opacity-0 group-hover:opacity-100 translate-y-4 group-hover:translate-y-0 transition-all">
                      <span class="material-symbols-outlined text-[20px]">arrow_outward</span>
                   </div>
                </div>
              </div>
              <div class="p-8 flex flex-col flex-1">
                <div class="flex items-center justify-between mb-4">
                  <span class="px-3 py-1 bg-surface-container-high rounded-lg text-[9px] font-black text-primary uppercase tracking-widest">{{ post.category }}</span>
                  <time class="text-[10px] font-black text-outline uppercase tracking-widest">{{ post.date }}</time>
                </div>
                <h3 class="text-2xl font-black text-on-surface mb-4 group-hover:text-primary transition-colors line-clamp-2 leading-tight tracking-tight">{{ post.title }}</h3>
                <p class="text-sm font-medium text-on-surface-variant line-clamp-3 leading-relaxed mb-8 opacity-70">{{ post.excerpt }}</p>
                
                <div class="mt-auto pt-6 border-t border-outline-variant/5 flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-surface-container-high border border-outline-variant/10 overflow-hidden shadow-sm">
                      <div class="w-full h-full bg-primary-container flex items-center justify-center text-xs font-black text-on-primary-container">
                        {{ post.author?.charAt(0) }}
                      </div>
                    </div>
                    <span class="text-xs font-black text-on-surface">{{ post.author }}</span>
                  </div>
                  <div class="flex items-center gap-2 text-outline">
                    <span class="material-symbols-outlined text-[16px]">chat_bubble</span>
                    <span class="text-[10px] font-bold">24</span>
                  </div>
                </div>
              </div>
            </article>
          </div>

          <!-- Empty Search State -->
          <div v-if="filteredPosts.length === 0" class="py-32 text-center">
             <div class="w-24 h-24 bg-surface-container-low rounded-[32px] flex items-center justify-center mx-auto mb-8 text-outline/20 transform rotate-12">
                <span class="material-symbols-outlined text-6xl">search_off</span>
             </div>
             <h3 class="text-2xl font-black text-on-surface mb-2">Không tìm thấy bài viết</h3>
             <p class="text-on-surface-variant font-medium">Hãy thử tìm kiếm với từ khóa khác hoặc quay lại danh mục "Tất cả".</p>
          </div>

          <!-- Pagination -->
          <div v-if="filteredPosts.length > 0" class="flex justify-center pt-20">
             <button class="bg-surface-container-highest text-on-surface px-12 py-5 rounded-[24px] font-black text-xs uppercase tracking-[0.3em] hover:bg-primary hover:text-on-primary shadow-xl transition-all border-none cursor-pointer flex items-center gap-4 group">
               Tải thêm bài viết
               <span class="material-symbols-outlined text-[20px] group-hover:translate-y-1 transition-transform">expand_more</span>
             </button>
          </div>
        </div>

        <!-- Sticky Sidebar (MD3) -->
        <aside class="w-full lg:w-80 flex flex-col gap-10">
          
          <!-- Popular Content Card -->
          <div class="bg-surface-container-low/40 p-8 rounded-[48px] border border-outline-variant/10">
            <h3 class="text-xl font-black text-on-surface mb-8 flex items-center gap-3">
              <span class="material-symbols-outlined text-primary">trending_up</span>
              Xu hướng
            </h3>
            <div class="space-y-8">
              <div v-for="(post, index) in popularPosts" :key="post.id" class="group cursor-pointer">
                <div class="flex gap-4">
                  <span class="text-3xl font-black text-outline/10 group-hover:text-primary transition-all">{{ (index + 1).toString().padStart(2, '0') }}</span>
                  <div class="space-y-2">
                    <h4 class="text-sm font-black text-on-surface leading-tight group-hover:text-primary transition-all line-clamp-2 tracking-tight">{{ post.title }}</h4>
                    <div class="flex items-center gap-3">
                      <time class="text-[9px] font-black text-outline uppercase tracking-widest">{{ post.date }}</time>
                      <div class="flex items-center gap-1 text-primary">
                         <span class="material-symbols-outlined text-[12px] fill-1">bolt</span>
                         <span class="text-[9px] font-black uppercase tracking-widest">Hot</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Premium Newsletter -->
          <div class="bg-primary text-on-primary p-10 rounded-[48px] shadow-2xl relative overflow-hidden text-center group">
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-white/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-1000"></div>
            
            <div class="relative z-10">
              <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-6">
                 <span class="material-symbols-outlined text-3xl">mark_email_unread</span>
              </div>
              <h3 class="text-2xl font-black mb-3 leading-tight tracking-tight">Cộng đồng Komi Editorial</h3>
              <p class="text-on-primary/70 text-xs font-medium mb-8 leading-relaxed px-2">Cập nhật những chuyển động mới nhất của thế giới sách mỗi sáng thứ Hai.</p>
              
              <form class="space-y-4">
                <input 
                  type="email" 
                  placeholder="Email của bạn..." 
                  class="w-full px-6 py-4 bg-white/10 border border-white/20 rounded-2xl focus:bg-white focus:text-on-surface transition-all outline-none text-sm font-bold text-white placeholder:text-white/40 text-center"
                />
                <button class="w-full bg-white text-primary px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:scale-105 transition-all shadow-2xl border-none cursor-pointer">Gia nhập ngay</button>
              </form>
            </div>
          </div>

          <!-- Cloud Tags -->
          <div class="bg-surface-container-low/40 p-8 rounded-[48px] border border-outline-variant/10">
            <h3 class="text-xl font-black text-on-surface mb-6">Tags phổ biến</h3>
            <div class="flex flex-wrap gap-2">
              <span v-for="tag in tags" :key="tag" class="px-5 py-2.5 bg-white border border-outline-variant/10 rounded-xl text-[10px] font-black text-on-surface-variant hover:bg-primary hover:text-on-primary hover:shadow-lg transition-all cursor-pointer uppercase tracking-widest">
                {{ tag }}
              </span>
            </div>
          </div>

        </aside>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const activeCategory = ref('Tất cả')
const searchQuery = ref('')
const categories = ['Tất cả', 'Review Sách', 'Góc Tác Giả', 'Xu Hướng', 'Sự Kiện', 'Kỹ Năng Đọc']

const featuredPost = {
  id: 0,
  title: 'Sự Trỗi Dậy Của Văn Học Đương Đại Trong Kỷ Nguyên Số',
  excerpt: 'Khám phá cách công nghệ đang định hình lại cách chúng ta sáng tác, tiêu thụ và cảm nhận những tác phẩm văn học mới nhất. Một góc nhìn sâu sắc từ các chuyên gia hàng đầu về tương lai của ngành xuất bản.',
  image: 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?q=80&w=2073&auto=format&fit=crop',
  category: 'Xu Hướng',
  date: '20 Tháng 5, 2024',
  author: 'Komi Editorial'
}

const posts = ref([
  {
    id: 1,
    title: "Nghệ Thuật Kể Chuyện: Review Chi Tiết Cuốn 'Ánh Sáng Vô Hình'",
    excerpt: 'Một phân tích sâu sắc về kỹ thuật xây dựng nhân vật và cốt truyện trong tác phẩm đoạt giải Pulitzer mới nhất, mang đến góc nhìn mới cho độc giả yêu thích thể loại tiểu thuyết lịch sử.',
    image: 'https://images.unsplash.com/photo-1474932430478-3a7fb9065ba0?q=80&w=2070&auto=format&fit=crop',
    category: 'Review Sách',
    date: '15 Tháng 5, 2024',
    author: 'Minh Thư'
  },
  {
    id: 2,
    title: 'Phỏng Vấn Độc Quyền: Tác Giả Nguyễn Văn A Về Hành Trình Sáng Tác',
    excerpt: 'Lắng nghe những chia sẻ chân thành về nguồn cảm hứng, những khó khăn và niềm vui trong quá trình hoàn thành tiểu thuyết vĩ đại của ông sau 5 năm ấp ủ.',
    image: 'https://images.unsplash.com/photo-1455390582262-044cdead277a?q=80&w=2073&auto=format&fit=crop',
    category: 'Góc Tác Giả',
    date: '12 Tháng 5, 2024',
    author: 'Quốc Bảo'
  },
  {
    id: 3,
    title: 'Audiobook Sẽ Là Tương Lai Của Việc Đọc?',
    excerpt: 'Sách nói đang ngày càng phổ biến. Liệu chúng sẽ thay thế sách giấy truyền thống hay chỉ là một phương tiện bổ sung trong thời đại bận rộn hiện nay?',
    image: 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?q=80&w=2074&auto=format&fit=crop',
    category: 'Xu Hướng',
    date: '10 Tháng 5, 2024',
    author: 'Thanh Hằng'
  },
  {
    id: 4,
    title: 'Top 10 Tiểu Thuyết Khoa Học Viễn Tưởng Đáng Đọc Nhất Năm',
    excerpt: 'Tuyển tập những tác phẩm sci-fi xuất sắc nhất, đưa bạn vào những chuyến phiêu lưu kỳ thú qua các vì sao và những tương lai đầy bất ngờ mà bạn chưa bao giờ tưởng tượng tới.',
    image: 'https://images.unsplash.com/photo-1532012197267-da84d127e765?q=80&w=1974&auto=format&fit=crop',
    category: 'Sự Kiện',
    date: '08 Tháng 5, 2024',
    author: 'Hoàng Long'
  },
  {
    id: 5,
    title: 'Nhìn Lại Lịch Sử Phát Triển Của Ngành Xuất Bản',
    excerpt: 'Từ những bản thảo viết tay đầu tiên đến kỷ nguyên in ấn công nghiệp, một hành trình dài và đầy thú vị của những trang sách qua hàng thế kỷ.',
    image: 'https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?q=80&w=2070&auto=format&fit=crop',
    category: 'Kỹ Năng Đọc',
    date: '05 Tháng 5, 2024',
    author: 'Lê Anh'
  },
  {
    id: 6,
    title: 'Làm Thế Nào Để Xây Dựng Thói Quen Đọc Sách Bền Vững',
    excerpt: 'Chia sẻ từ những người đọc nhiều nhất: các mẹo thiết thực để duy trì ngọn lửa đam mê đọc sách mỗi ngày giữa cuộc sống xô bồ và bận rộn.',
    image: 'https://images.unsplash.com/photo-1491841573634-28140fc7ced7?q=80&w=2070&auto=format&fit=crop',
    category: 'Kỹ Năng Đọc',
    date: '01 Tháng 5, 2024',
    author: 'Ngọc Lan'
  }
])

const filteredPosts = computed(() => {
  let result = posts.value
  if (activeCategory.value !== 'Tất cả') {
    result = result.filter(p => p.category === activeCategory.value)
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(p => p.title.toLowerCase().includes(q) || p.excerpt.toLowerCase().includes(q))
  }
  return result
})

const popularPosts = [
  { id: 1, title: '5 Cuốn Sách Giúp Bạn Cải Thiện Kỹ Năng Tư Duy Phản Biện', date: '22 Tháng 4, 2024' },
  { id: 2, title: "Review 'Sự Tĩnh Lặng Của Bầy Cừu': Tuyệt Tác Tâm Lý Tội Phạm", date: '18 Tháng 4, 2024' },
  { id: 3, title: 'Danh Sách Sách Đề Cử Giải Thưởng Sách Quốc Gia 2023', date: '10 Tháng 4, 2024' }
]

const tags = ['Văn học', 'Sci-fi', 'Phát triển bản thân', 'Công nghệ', 'E-book', 'Review', 'Tác giả', 'Audiobook']

const goToPost = (post) => {
  // Logic điều hướng chi tiết bài viết (giả định route /blog/:id)
  console.log('Điều hướng bài viết:', post.id)
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100;400;900&display=swap');

.font-outfit {
  font-family: 'Outfit', sans-serif;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  opacity: 0;
}

.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }
.delay-400 { animation-delay: 0.4s; }
.delay-500 { animation-delay: 0.5s; }

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.fill-1 {
  font-variation-settings: 'FILL' 1;
}
</style>
