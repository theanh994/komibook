<template>
  <div 
    class="h-screen w-full flex flex-col md:flex-row transition-all duration-700 overflow-hidden font-outfit select-none" 
    :class="[themeClasses[currentTheme].bg, themeClasses[currentTheme].text]"
    @contextmenu.prevent
  >
    <Toast />
    <!-- ─── PREMIUM DESKTOP SIDEBAR ─── -->
    <nav 
      v-show="!focusMode"
      class="hidden md:flex flex-col h-full py-xl w-72 bg-surface-container-low dark:bg-surface-container border-r border-outline-variant/50 shadow-[4px_0_24px_rgba(0,0,0,0.1)] z-50 flex-shrink-0 transition-all duration-500 ease-in-out"
    >
      <!-- Brand & Header -->
      <div class="px-8 mb-xl flex items-center gap-4 group cursor-pointer" @click="$router.push('/')">
        <div class="w-12 h-12 rounded-2xl bg-primary flex items-center justify-center text-on-primary shadow-lg shadow-primary/20 group-hover:scale-105 transition-transform duration-500">
          <span class="material-symbols-outlined text-[28px] fill-1">menu_book</span>
        </div>
        <div>
          <h1 class="text-2xl font-black text-on-surface tracking-tighter leading-none">KomiBook</h1>
          <p class="text-[9px] uppercase tracking-[0.2em] text-primary font-black mt-1.5 opacity-80">Premium Reader v2.0</p>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex-1 px-4 space-y-3">
        <button 
          v-for="tab in tabs" 
          :key="tab.id"
          @click="selectTab(tab.id)"
          class="w-full flex items-center gap-4 px-5 py-4 rounded-[22px] transition-all duration-300 group relative overflow-hidden"
          :class="[activeTab === tab.id ? 'bg-primary text-on-primary shadow-xl shadow-primary/20 scale-[1.02]' : 'text-on-surface-variant hover:bg-primary/5 hover:text-primary']"
        >
          <span class="material-symbols-outlined text-[24px] z-10" :style="{ 'font-variation-settings': activeTab === tab.id ? `'FILL' 1` : `'FILL' 0` }">{{ tab.icon }}</span>
          <span class="font-black text-sm uppercase tracking-wider z-10">{{ tab.label }}</span>
          <div v-if="activeTab === tab.id" class="absolute inset-0 bg-gradient-to-r from-primary to-primary-fixed opacity-10"></div>
        </button>
      </div>

      <!-- Footer Info -->
      <div class="px-6 mt-auto">
        <div class="bg-surface-container-high/40 backdrop-blur-md p-lg rounded-[28px] border border-outline-variant/10 mb-6 group hover:border-primary/20 transition-all duration-500">
          <div class="flex justify-between items-center mb-3">
            <span class="text-[10px] font-black uppercase text-primary tracking-widest">Tiến độ đọc</span>
            <span class="text-sm font-black text-primary">{{ readingProgress }}%</span>
          </div>
          <div class="w-full h-2 bg-surface-container-highest/50 rounded-full overflow-hidden p-[2px]">
            <div class="h-full bg-primary rounded-full transition-all duration-1000 ease-out shadow-[0_0_12px_rgba(var(--primary-rgb),0.4)]" :style="{ width: readingProgress + '%' }"></div>
          </div>
          <p class="text-[9px] text-on-surface-variant mt-3 text-center font-bold uppercase tracking-tighter opacity-50">Bạn đang đọc trang {{ currentPage }} của {{ totalPages }}</p>
        </div>

        <button @click="$router.push('/my-library')" class="w-full flex items-center justify-center gap-3 py-4.5 rounded-[22px] bg-on-surface text-surface font-black text-xs uppercase tracking-widest hover:opacity-90 active:scale-95 transition-all shadow-xl">
          <span class="material-symbols-outlined text-[22px]">library_books</span>
          Thư viện của tôi
        </button>
      </div>
    </nav>

    <!-- ─── MOBILE PREMIUM HEADER ─── -->
    <header class="md:hidden flex justify-between items-center px-6 w-full h-20 bg-surface-container-low/95 backdrop-blur-xl border-b border-outline-variant/30 z-50 sticky top-0 transition-all duration-300">
      <div class="flex items-center gap-4">
        <button @click="$router.push('/my-library')" class="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant active:scale-90 transition-all">
          <span class="material-symbols-outlined text-[22px]">arrow_back</span>
        </button>
        <span class="font-black text-xl tracking-tighter text-on-surface">Komibook</span>
      </div>
      <div class="flex gap-3">
        <button @click="showSettings = true" class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary active:scale-90 transition-all">
          <span class="material-symbols-outlined text-[22px]">settings_nightight</span>
        </button>
      </div>
    </header>

    <!-- ─── MAIN CONTENT AREA ─── -->
    <main class="flex-1 flex flex-col h-full overflow-hidden relative transition-all duration-500">
      
      <!-- ══ READER WORKSPACE (Always active) ══ -->
      <div class="flex-1 flex flex-col h-full relative overflow-hidden">
        
        <!-- Premium Floating Controls -->
        <div 
          class="hidden lg:flex fixed right-6 top-1/2 -translate-y-1/2 flex-col gap-6 bg-surface-container-low/95 dark:bg-surface-container/95 backdrop-blur-3xl rounded-[32px] shadow-[0_10px_40px_rgba(0,0,0,0.2)] border border-outline-variant/40 p-4 z-50 transition-all duration-500"
          :class="focusMode ? 'opacity-20 hover:opacity-100 translate-x-8 hover:translate-x-0' : 'opacity-100'"
        >
          <div class="flex flex-col gap-2">
            <button @click="zoomIn" class="w-12 h-12 rounded-2xl flex items-center justify-center text-on-surface hover:text-primary hover:bg-primary/10 transition-all" title="Phóng to">
              <span class="material-symbols-outlined text-[26px]">add_circle</span>
            </button>
            <button @click="zoomOut" class="w-12 h-12 rounded-2xl flex items-center justify-center text-on-surface hover:text-primary hover:bg-primary/10 transition-all" title="Thu nhỏ">
              <span class="material-symbols-outlined text-[26px]">remove_circle</span>
            </button>
          </div>
          <div class="h-px w-8 mx-auto bg-outline-variant/20"></div>
          <div class="flex flex-col gap-2">
            <button @click="toggleTheme" class="w-12 h-12 rounded-2xl flex items-center justify-center text-on-surface hover:text-primary hover:bg-primary/10 transition-all" title="Chuyển chế độ">
              <span class="material-symbols-outlined text-[26px]">{{ currentTheme === 'dark' ? 'light_mode' : 'dark_mode' }}</span>
            </button>
            <button @click="showSettings = true" class="w-12 h-12 rounded-2xl flex items-center justify-center text-on-surface hover:text-primary hover:bg-primary/10 transition-all" title="Cài đặt">
              <span class="material-symbols-outlined text-[26px]">display_settings</span>
            </button>
          </div>
          <div class="h-px w-8 mx-auto bg-outline-variant/20"></div>
          <button @click="toggleFocusMode" class="w-12 h-12 rounded-2xl flex items-center justify-center transition-all" :class="focusMode ? 'bg-primary text-on-primary' : 'text-on-surface hover:bg-primary/10 hover:text-primary'" title="Chế độ tập trung">
            <span class="material-symbols-outlined text-[26px]">{{ focusMode ? 'fullscreen_exit' : 'fullscreen' }}</span>
          </button>
        </div>

        <!-- Reader Workspace with its own scroll container -->
        <div class="flex-1 w-full overflow-y-auto overflow-x-hidden scroll-smooth" ref="scrollContainer">
          <div class="flex flex-col items-center py-xl px-2 relative min-h-full">
            
            <!-- PDF Background Shadow Decor -->
          <div class="absolute inset-0 pointer-events-none overflow-hidden opacity-20">
             <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-primary/20 blur-[160px] rounded-full"></div>
          </div>

          <div 
            class="w-full max-w-3xl relative transition-all duration-700 ease-[cubic-bezier(0.34,1.56,0.64,1)]" 
            :style="{ transform: `scale(${scale})`, transformOrigin: 'top center', perspective: '1500px' }"
          >
            
            <!-- Shimmer Loading -->
            <div v-if="loading" class="absolute inset-0 flex flex-col items-center justify-center min-h-[800px] z-20 bg-surface-container-lowest/50 backdrop-blur-sm rounded-[32px]">
              <div class="relative w-24 h-24 mb-8">
                <div class="absolute inset-0 border-4 border-primary/20 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-t-primary rounded-full animate-spin"></div>
              </div>
              <p class="font-black text-primary uppercase tracking-[0.2em] text-sm animate-pulse">Đang trải thảm tri thức...</p>
            </div>

            <!-- Error State -->
            <div v-if="error" class="bg-surface-container-lowest/80 backdrop-blur-xl p-xxl rounded-[40px] shadow-2xl border border-error/20 text-center animate-fade-in">
              <div class="w-24 h-24 bg-error/10 rounded-full flex items-center justify-center mx-auto mb-xl">
                <span class="material-symbols-outlined text-[56px] text-error">error_medley</span>
              </div>
              <h2 class="text-3xl font-black text-on-surface mb-md tracking-tight">Opps! Sách bị kẹt rồi</h2>
              <p class="text-on-surface-variant mb-xl max-w-md mx-auto leading-relaxed">{{ error }}</p>
              <button @click="fetchEbookData" class="bg-error text-on-error px-12 py-4 rounded-2xl font-black shadow-xl shadow-error/20 hover:scale-105 active:scale-95 transition-all">Thử mở lại</button>
            </div>

            <!-- The PDF Content -->
            <div 
              class="pdf-wrapper relative rounded-2xl shadow-[0_40px_100px_rgba(0,0,0,0.12)] border border-outline-variant/30 overflow-hidden bg-white transition-all duration-300 select-none"
              :class="[
                {'opacity-0 scale-95': loading, 'opacity-100 scale-100': !loading},
                isFlippingNext ? 'animate-page-flip-next' : '',
                isFlippingPrev ? 'animate-page-flip-prev' : '',
                isDragging ? 'cursor-grabbing' : 'cursor-grab'
              ]"
              :style="[ { filter: themeClasses[currentTheme].filter }, dragTransform ]"
              @dblclick="handleDoubleClick"
              @pointerdown="startDrag"
              @pointermove="onDrag"
              @pointerup="endDrag"
              @pointercancel="endDrag"
              @pointerleave="endDrag"
            >
              <VuePdfEmbed 
                v-if="pdfUrl"
                ref="pdfRef"
                :source="pdfUrl" 
                :page="currentPage"
                :text-layer="false"
                :annotation-layer="false"
                @loaded="onPdfLoaded"
                @rendered="onPdfRendered"
                @error="onPdfError"
                class="w-full" 
              />
            </div>

             <div v-if="!loading && !error" class="mt-12 mb-32 flex flex-col items-center gap-4 animate-fade-in delay-700 pointer-events-none">
                 <div class="h-1.5 w-32 bg-primary/20 rounded-full overflow-hidden">
                    <div class="h-full bg-primary animate-progress-loading"></div>
                 </div>
                 <p class="text-[10px] font-black uppercase tracking-[0.3em] text-outline opacity-40">Mẹo: Vuốt hoặc nhấn đúp chuột để chuyển trang</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Absolute Navigation Controls (Always floating at bottom) -->
        <div 
          class="fixed bottom-6 max-w-[400px] w-[calc(100%-40px)] bg-surface-container-high/95 dark:bg-surface-container-highest/95 backdrop-blur-3xl border border-outline-variant/30 rounded-[32px] px-6 py-4 z-[90] flex items-center justify-between shadow-[0_20px_60px_rgba(0,0,0,0.2)] transition-all duration-500"
          :class="focusMode ? 'left-1/2 -translate-x-1/2 opacity-30 hover:opacity-100 translate-y-12 hover:translate-y-0' : 'left-1/2 -translate-x-1/2 lg:left-auto lg:translate-x-0 lg:right-6 opacity-100'"
        >
          <button 
            @click="prevPage" 
            :disabled="currentPage <= 1"
            class="w-12 h-12 rounded-full flex items-center justify-center bg-outline-variant/20 text-on-surface hover:bg-primary hover:text-on-primary transition-all disabled:opacity-20"
          >
            <span class="material-symbols-outlined text-[28px]">arrow_back_ios_new</span>
          </button>
          
          <div class="flex items-center gap-6">
            <div class="flex items-baseline gap-2">
              <input 
                type="number" 
                v-model.number="inputPage"
                @keyup.enter="jumpToPage"
                class="w-14 h-14 bg-outline-variant/20 border-none text-center rounded-2xl font-black text-2xl text-on-surface focus:bg-primary focus:text-on-primary transition-all outline-none"
                min="1"
                :max="totalPages"
              />
              <span class="text-xl font-bold text-on-surface-variant">/ {{ totalPages }}</span>
            </div>
          </div>

          <button 
            @click="nextPage" 
            :disabled="currentPage >= totalPages"
            class="w-12 h-12 rounded-full flex items-center justify-center bg-outline-variant/20 text-on-surface hover:bg-primary hover:text-on-primary transition-all disabled:opacity-20"
          >
            <span class="material-symbols-outlined text-[28px]">arrow_forward_ios</span>
          </button>
        </div>
      </div>
    </main>

    <!-- ─── FLOATING SUBMENU DRAWER ─── -->
    <Drawer 
      v-model:visible="isDrawerVisible" 
      position="left" 
      class="!w-full md:!w-[480px] lg:!w-[540px] !bg-surface-container-lowest/95 !backdrop-blur-3xl border-r border-outline-variant/10 shadow-2xl"
      @hide="activeTab = 'reader'"
    >
      <template #header>
        <div class="flex items-center gap-3 py-2">
          <div class="w-10 h-10 rounded-2xl bg-primary/10 flex items-center justify-center text-primary">
             <span class="material-symbols-outlined">{{ activeTabInfo?.icon }}</span>
          </div>
          <h2 class="text-xl font-black text-on-surface tracking-tight">{{ activeTabInfo?.label }}</h2>
        </div>
      </template>

      <!-- ══ DRAWER TAB: CONTENTS ══ -->
      <div v-show="activeTab === 'contents'" class="h-full overflow-y-auto no-scrollbar scroll-smooth pb-8">
        <div class="max-w-4xl mx-auto">
          <header class="flex flex-col border-b border-outline-variant/20 pb-6 mb-8 gap-4">
            <div class="animate-slide-up">
              <div class="flex items-center gap-3 mb-2">
                 <div class="w-1.5 h-6 bg-primary rounded-full"></div>
                 <span class="text-xs font-black text-primary uppercase tracking-[0.3em]">Hành trình khám phá</span>
              </div>
              <h2 class="text-3xl font-black text-on-surface tracking-tight">Mục lục tác phẩm</h2>
            </div>
            <div class="inline-flex w-max items-center gap-3 bg-surface-container-high/50 px-4 py-2 rounded-xl border border-outline-variant/10 animate-fade-in">
               <span class="material-symbols-outlined text-primary text-sm">list_alt</span>
               <span class="text-sm font-black text-on-surface tracking-tighter">{{ outline.length }} Chương & Mục</span>
            </div>
          </header>

          <div v-if="!outline || outline.length === 0" class="flex flex-col items-center justify-center py-16 bg-surface-container-low/40 rounded-3xl border-2 border-dashed border-outline-variant/20 animate-fade-in">
            <div class="w-20 h-20 bg-surface-container-high rounded-full flex items-center justify-center mb-6">
              <span class="material-symbols-outlined text-[40px] text-outline/30">content_paste_off</span>
            </div>
            <h3 class="text-lg font-black text-on-surface mb-1">Chưa có mục lục</h3>
            <p class="text-sm text-on-surface-variant font-medium opacity-60 text-center px-4">Tác phẩm này đang được cập nhật chỉ mục kỹ thuật số.</p>
          </div>

          <div class="grid grid-cols-1 gap-3">
            <button 
              v-for="(item, index) in outline" 
              :key="index"
              @click="goToTocItem(item); isDrawerVisible = false; activeTab = 'reader'"
              class="group flex items-center justify-between p-4 rounded-2xl bg-surface-container-lowest border border-outline-variant/10 hover:border-primary/40 hover:bg-primary/[0.02] hover:shadow-[0_10px_20px_rgba(0,0,0,0.03)] transition-all duration-300 text-left animate-slide-up"
              :style="{ animationDelay: `${index * 50}ms` }"
            >
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-xl bg-surface-container-high group-hover:bg-primary group-hover:text-on-primary transition-all duration-300">
                  <span class="text-sm font-black tracking-tighter">{{ (index + 1).toString().padStart(2, '0') }}</span>
                </div>
                <div>
                  <span class="text-base font-bold text-on-surface group-hover:text-primary transition-colors block leading-tight">{{ item.title }}</span>
                  <span class="text-[9px] font-black uppercase text-outline opacity-40 group-hover:opacity-100 transition-all tracking-[0.2em] mt-1 block">Chương tác phẩm</span>
                </div>
              </div>
              <div class="w-8 h-8 flex-shrink-0 rounded-full border border-outline-variant/30 flex items-center justify-center group-hover:bg-primary group-hover:border-primary group-hover:translate-x-1 transition-all duration-300">
                <span class="material-symbols-outlined text-sm text-outline group-hover:text-on-primary">east</span>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- ══ DRAWER TAB: ANNOTATIONS ══ -->
      <div v-show="activeTab === 'annotations'" class="h-full overflow-y-auto no-scrollbar scroll-smooth pb-8">
        <div class="max-w-4xl mx-auto">
          <header class="flex flex-col gap-6 mb-10 border-b border-outline-variant/20 pb-8">
            <div class="animate-slide-up">
              <div class="flex items-center gap-3 mb-3">
                 <div class="w-1.5 h-6 bg-secondary rounded-full"></div>
                 <span class="text-xs font-black text-secondary uppercase tracking-[0.3em]">Kho tàng cảm xúc</span>
              </div>
              <h2 class="text-3xl font-black text-on-surface tracking-tight">Ghi chú & Đánh dấu</h2>
              <p class="text-on-surface-variant font-medium mt-2 text-sm">Lưu giữ từng khoảnh khắc bừng sáng của trí tuệ.</p>
            </div>
            <div class="flex flex-wrap gap-2 animate-fade-in">
              <button @click="annotationFilter = 'all'" :class="annotationFilter === 'all' ? 'bg-primary text-on-primary shadow-md shadow-primary/20' : 'bg-surface-container-high text-on-surface-variant'" class="px-5 py-2.5 rounded-xl text-xs font-black transition-all">Tất cả ({{ annotations.length }})</button>
              <button @click="annotationFilter = 'highlight'" :class="annotationFilter === 'highlight' ? 'bg-secondary text-on-secondary shadow-md shadow-secondary/20' : 'bg-surface-container-high text-on-surface-variant'" class="px-5 py-2.5 rounded-xl text-xs font-black transition-all">Highlights</button>
              <button @click="annotationFilter = 'note'" :class="annotationFilter === 'note' ? 'bg-tertiary text-on-tertiary shadow-md shadow-tertiary/20' : 'bg-surface-container-high text-on-surface-variant'" class="px-5 py-2.5 rounded-xl text-xs font-black transition-all">Ghi chú</button>
            </div>
          </header>

          <div v-if="filteredAnnotations.length === 0" class="flex flex-col items-center justify-center py-20 bg-surface-container-low/40 rounded-3xl border-2 border-dashed border-outline-variant/20 animate-fade-in">
            <div class="w-24 h-24 bg-surface-container-high rounded-full flex items-center justify-center mb-6">
              <span class="material-symbols-outlined text-[48px] text-outline/30">draw_abstract</span>
            </div>
            <h3 class="text-xl font-black text-on-surface mb-2">Trang giấy còn trống</h3>
            <p class="text-sm text-on-surface-variant font-medium opacity-60">Hãy bắt đầu tô điểm hành trình đọc sách của bạn.</p>
          </div>

          <div class="grid grid-cols-1 gap-6 items-start">
            <article 
              v-for="(note, idx) in filteredAnnotations" 
              :key="note.id"
              class="bg-surface-container-lowest rounded-3xl shadow-sm p-6 relative overflow-hidden flex flex-col gap-5 border border-outline-variant/10 hover:shadow-xl hover:-translate-y-1 transition-all duration-500 group animate-slide-up"
              :style="{ animationDelay: `${idx * 100}ms` }"
            >
              <!-- Color Indicator -->
              <div class="absolute left-0 top-0 bottom-0 w-2 transition-all group-hover:w-3" :style="{ backgroundColor: note.color || '#ba0035' }"></div>
              
              <div class="flex justify-between items-center pl-2">
                <div class="flex items-center gap-2">
                   <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center">
                      <span class="material-symbols-outlined text-sm text-primary">auto_stories</span>
                   </div>
                   <span class="text-[10px] font-black uppercase tracking-widest text-on-surface">Trang {{ note.page }}</span>
                </div>
                <span class="text-[9px] font-bold text-outline uppercase tracking-tighter">{{ formatDate(note.created_at) }}</span>
              </div>

              <blockquote v-if="note.highlighted_text" class="font-literata text-base text-on-surface italic pl-5 border-l-4 border-outline-variant/20 my-1 leading-relaxed opacity-80 group-hover:opacity-100 transition-opacity">
                "{{ note.highlighted_text }}"
              </blockquote>

              <div class="bg-surface-container-low p-5 rounded-2xl border border-outline-variant/10 relative">
                <div class="flex items-center gap-2 mb-2 text-primary opacity-60">
                  <span class="material-symbols-outlined text-xs">edit_square</span>
                  <span class="text-[8px] font-black uppercase tracking-[0.2em]">Suy tư của bạn</span>
                </div>
                <p class="text-sm font-bold text-on-surface leading-relaxed">{{ note.note_content }}</p>
                <span class="absolute bottom-3 right-3 material-symbols-outlined text-2xl text-primary/5 group-hover:text-primary/20 transition-all">format_quote</span>
              </div>
            </article>
          </div>
        </div>
      </div>

      <!-- ══ DRAWER TAB: DETAILS ══ -->
      <div v-show="activeTab === 'details'" class="h-full overflow-y-auto no-scrollbar scroll-smooth pb-8">
        <div class="max-w-4xl mx-auto">
          <!-- Mini Hero -->
          <div class="flex flex-col gap-8 mb-12 animate-fade-in">
            <div class="w-32 lg:w-40 flex-shrink-0 mx-auto">
               <div class="aspect-[2/3] rounded-3xl overflow-hidden shadow-xl border border-outline-variant/10 bg-white">
                  <img v-if="book?.cover_image" :src="book.cover_image" :alt="book.title" class="w-full h-full object-cover" />
               </div>
            </div>

            <div class="text-center space-y-4">
              <span class="inline-block px-3 py-1 bg-primary/10 text-primary text-[9px] font-black uppercase tracking-widest rounded-full border border-primary/20">Digital Edition</span>
              <h1 class="text-3xl font-black text-on-surface leading-tight tracking-tight">{{ book?.title }}</h1>
              <p class="text-base text-on-surface-variant font-bold tracking-tight">Bởi {{ book?.author }}</p>
            </div>
          </div>

          <!-- Quick Stats Grid -->
          <div class="grid grid-cols-2 gap-3 mb-8">
            <div v-for="stat in bookStats" :key="stat.label" class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/10 shadow-sm hover:border-primary/30 transition-all text-center">
              <span class="material-symbols-outlined text-primary mb-2 text-xl">{{ stat.icon }}</span>
              <p class="text-[8px] uppercase font-black text-outline tracking-widest mb-1">{{ stat.label }}</p>
              <p class="text-base font-black text-on-surface">{{ stat.value }}</p>
            </div>
          </div>

          <!-- Description -->
          <div class="prose max-w-none mb-10">
            <div class="flex items-center gap-2 mb-4">
               <div class="w-1.5 h-5 bg-primary rounded-full"></div>
               <h3 class="text-xl font-black text-on-surface tracking-tight">Tóm tắt nội dung</h3>
            </div>
            <p class="font-literata text-sm text-on-surface-variant leading-relaxed text-justify opacity-80">{{ book?.description }}</p>
          </div>

          <button class="w-full bg-primary text-on-primary py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 flex items-center justify-center gap-2 hover:opacity-90 active:scale-95 transition-all">
             <span class="material-symbols-outlined text-lg">share</span>
             Chia sẻ tác phẩm
          </button>
        </div>
      </div>
    </Drawer>

    <!-- ─── PREMIUM MOBILE BOTTOM BAR ─── -->
    <nav v-show="!focusMode" class="md:hidden bg-surface-container-low/95 backdrop-blur-3xl border-t border-outline-variant/30 flex justify-around items-center h-24 px-6 pb-safe shadow-[0_-20px_40px_rgba(0,0,0,0.1)] z-[100] fixed bottom-0 w-full">
      <button 
        v-for="tab in tabs" 
        :key="tab.id"
        @click="selectTab(tab.id)"
        class="flex flex-col items-center justify-center min-w-[72px] transition-all relative"
        :class="activeTab === tab.id ? 'text-primary' : 'text-on-surface-variant opacity-40'"
      >
        <div 
          class="w-14 h-10 rounded-full flex items-center justify-center transition-all duration-500"
          :class="activeTab === tab.id ? 'bg-primary/10' : ''"
        >
          <span class="material-symbols-outlined text-[28px]" :style="{ 'font-variation-settings': activeTab === tab.id ? `'FILL' 1` : `'FILL' 0` }">{{ tab.icon }}</span>
        </div>
        <span class="text-[9px] font-black uppercase tracking-widest mt-2">{{ tab.label }}</span>
        <div v-if="activeTab === tab.id" class="absolute -top-2 w-1.5 h-1.5 bg-primary rounded-full animate-bounce"></div>
      </button>
    </nav>

    <!-- ─── SETTINGS DRAWER (UPGRADED) ─── -->
    <Drawer v-model:visible="showSettings" position="right" class="!w-full md:!w-[420px] !bg-surface-container-lowest/95 !backdrop-blur-3xl border-l border-outline-variant/10 shadow-2xl">
      <template #header>
         <div class="flex items-center gap-3 py-2">
            <div class="w-10 h-10 rounded-2xl bg-primary/10 flex items-center justify-center text-primary">
               <span class="material-symbols-outlined">settings_suggest</span>
            </div>
            <h2 class="text-2xl font-black text-on-surface tracking-tight">Cá nhân hóa</h2>
         </div>
      </template>
      
      <div class="flex flex-col gap-12 py-8">
        <!-- Themes -->
        <div>
          <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-primary mb-8 flex items-center gap-2">
             <span>Không gian đọc</span>
             <div class="h-px flex-1 bg-primary/20"></div>
          </h3>
          <div class="grid grid-cols-3 gap-6">
            <button 
              v-for="(theme, key) in themeClasses" 
              :key="key"
              @click="currentTheme = key"
              class="flex flex-col items-center gap-4 p-5 rounded-[28px] border-2 transition-all group relative overflow-hidden"
              :class="[currentTheme === key ? 'border-primary bg-primary/5 shadow-xl' : 'border-outline-variant/10 hover:border-primary/20 bg-surface-container-low']"
            >
              <div class="w-12 h-12 rounded-2xl shadow-inner border border-black/5" :class="theme.bg"></div>
              <span class="text-[10px] font-black uppercase tracking-widest text-on-surface">{{ theme.name }}</span>
              <div v-if="currentTheme === key" class="absolute top-2 right-2">
                 <span class="material-symbols-outlined text-primary text-sm">check_circle</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Typography -->
        <div>
          <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-primary mb-8 flex items-center gap-2">
             <span>Phông chữ & Cỡ chữ</span>
             <div class="h-px flex-1 bg-primary/20"></div>
          </h3>
          <div class="space-y-8">
             <!-- Font Family Placeholder (as PDF reader is fixed font, we show scale) -->
             <div class="bg-surface-container-low p-8 rounded-[32px] border border-outline-variant/10">
                <div class="flex items-center justify-between mb-8">
                   <button @click="zoomOut" class="w-14 h-14 rounded-2xl bg-white dark:bg-white/10 flex items-center justify-center text-on-surface shadow-sm hover:bg-primary hover:text-on-primary transition-all">
                      <span class="material-symbols-outlined">text_decrease</span>
                   </button>
                   <div class="text-center">
                      <p class="text-4xl font-black text-primary tracking-tighter">{{ Math.round(scale * 100) }}%</p>
                      <p class="text-[9px] font-black uppercase tracking-widest opacity-40 mt-1">Độ phóng đại</p>
                   </div>
                   <button @click="zoomIn" class="w-14 h-14 rounded-2xl bg-white dark:bg-white/10 flex items-center justify-center text-on-surface shadow-sm hover:bg-primary hover:text-on-primary transition-all">
                      <span class="material-symbols-outlined">text_increase</span>
                   </button>
                </div>
                <input type="range" v-model="scale" min="0.5" max="3" step="0.1" class="w-full h-2 bg-surface-container-highest rounded-full appearance-none cursor-pointer accent-primary" />
             </div>
          </div>
        </div>

        <!-- Reading Mode -->
        <div class="bg-primary/5 p-8 rounded-[32px] border border-primary/10">
           <div class="flex items-center justify-between gap-6">
              <div>
                 <h4 class="text-lg font-black text-on-surface leading-tight">Chế độ tập trung</h4>
                 <p class="text-sm text-on-surface-variant font-medium mt-1">Ẩn bớt các thanh công cụ để đắm chìm vào từng trang sách.</p>
              </div>
              <button @click="toggleFocusMode" class="w-16 h-10 rounded-full relative transition-all duration-500 overflow-hidden border border-outline-variant/30" :class="focusMode ? 'bg-primary' : 'bg-surface-container-highest'">
                 <div class="absolute top-1 left-1 w-8 h-8 rounded-full bg-white transition-all duration-500 shadow-md" :class="focusMode ? 'translate-x-6' : 'translate-x-0'"></div>
              </button>
           </div>
        </div>

        <button @click="showSettings = false" class="mt-auto w-full py-5 bg-on-surface text-surface rounded-[24px] font-black text-xs uppercase tracking-[0.3em] shadow-2xl hover:opacity-90 active:scale-95 transition-all">Tiếp tục hành trình</button>
      </div>
    </Drawer>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Toast from 'primevue/toast'
import Drawer from 'primevue/drawer'
import apiClient from '@/services/axios'
import VuePdfEmbed from 'vue-pdf-embed'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const activeTab = ref('reader')
const loading = ref(true)
const pdfUrl = ref(null)
const error = ref(null)
const scale = ref(1.0)
const scrollContainer = ref(null)
const book = ref(null)
const focusMode = ref(false)
const annotationFilter = ref('all')

const currentPage = ref(1)
const totalPages = ref(0)
const inputPage = ref(1)

const isDrawerVisible = ref(false)
const showSettings = ref(false)
const outline = ref([])
const annotations = ref([])
const isFlippingNext = ref(false)
const isFlippingPrev = ref(false)
const isDragging = ref(false)
const dragStartX = ref(0)
const currentDragX = ref(0)

function triggerFlip(direction = 'next') {
  isFlippingNext.value = false
  isFlippingPrev.value = false
  setTimeout(() => {
    if (direction === 'next') isFlippingNext.value = true
    else isFlippingPrev.value = true
  }, 10)
}

const dragTransform = computed(() => {
  if (!isDragging.value) return {}
  const diff = currentDragX.value - dragStartX.value
  const rotation = Math.max(-20, Math.min(20, diff * 0.05))
  return {
    transform: `rotateY(${rotation}deg)`,
    transition: 'none'
  }
})

const startDrag = (e) => {
  isDragging.value = true
  dragStartX.value = e.clientX
  currentDragX.value = e.clientX
  e.preventDefault()
}

const onDrag = (e) => {
  if (!isDragging.value) return
  currentDragX.value = e.clientX
}

const endDrag = (e) => {
  if (!isDragging.value) return
  isDragging.value = false
  const diff = currentDragX.value - dragStartX.value
  if (diff > 100) {
    prevPage()
  } else if (diff < -100) {
    nextPage()
  }
  dragStartX.value = 0
  currentDragX.value = 0
}

const handleDoubleClick = (e) => {
  const rect = e.currentTarget.getBoundingClientRect()
  const clickX = e.clientX - rect.left
  if (clickX < rect.width / 2) {
    prevPage()
  } else {
    nextPage()
  }
}

function selectTab(tabId) {
  if (tabId === 'reader') {
    isDrawerVisible.value = false
    activeTab.value = 'reader'
  } else {
    activeTab.value = tabId
    isDrawerVisible.value = true
  }
}
const pdfRef = ref(null)
const pdfDocument = ref(null)

const tabs = [
  { id: 'reader', label: 'Trình đọc', icon: 'auto_stories' },
  { id: 'contents', label: 'Mục lục', icon: 'format_list_bulleted' },
  { id: 'annotations', label: 'Ghi chú', icon: 'edit_note' },
  { id: 'details', label: 'Thông tin', icon: 'info' }
]

const activeTabInfo = computed(() => tabs.find(t => t.id === activeTab.value))

const currentTheme = ref(localStorage.getItem('readerTheme') || 'light')
const themeClasses = {
  light: {
    name: 'Sáng',
    bg: 'bg-[#faf8ff]',
    bgBase: '#faf8ff',
    text: 'text-on-surface',
    filter: 'none'
  },
  sepia: {
    name: 'Cổ điển',
    bg: 'bg-[#f4ecd8]',
    bgBase: '#f4ecd8',
    text: 'text-[#5b4636]',
    filter: 'sepia(0.3) contrast(0.95) brightness(0.98)'
  },
  dark: {
    name: 'Đêm',
    bg: 'bg-[#0f1420]',
    bgBase: '#0f1420',
    text: 'text-[#e2e2e2]',
    filter: 'invert(0.9) hue-rotate(180deg) contrast(0.9) brightness(0.9)'
  }
}

const readingProgress = computed(() => {
  if (totalPages.value === 0) return 0
  return Math.round((currentPage.value / totalPages.value) * 100)
})

const filteredAnnotations = computed(() => {
  if (annotationFilter.value === 'all') return annotations.value
  if (annotationFilter.value === 'highlight') return annotations.value.filter(a => a.highlighted_text && !a.note_content)
  if (annotationFilter.value === 'note') return annotations.value.filter(a => a.note_content)
  return annotations.value
})

const bookStats = computed(() => [
  { label: 'Ngôn ngữ', value: book.value?.language || 'Tiếng Việt', icon: 'language' },
  { label: 'Định dạng', value: 'E-book (PDF)', icon: 'picture_as_pdf' },
  { label: 'Trang', value: totalPages.value || '...', icon: 'auto_stories' },
  { label: 'Bản quyền', value: book.value?.publisher || 'Komibook', icon: 'verified' }
])

const extraMetadata = computed(() => [
  { label: 'Nhà xuất bản', value: book.value?.publisher || 'NXB Trẻ', icon: 'business' },
  { label: 'Năm xuất bản', value: book.value?.publication_year || '2023', icon: 'calendar_today' },
  { label: 'ISBN', value: book.value?.isbn || '978-604-1-2345-6', icon: 'barcode' },
  { label: 'Bộ sách', value: book.value?.series?.name || 'Không thuộc bộ', icon: 'collections_bookmark' }
])

watch(currentTheme, (newTheme) => localStorage.setItem('readerTheme', newTheme))

const toggleTheme = () => {
  const keys = Object.keys(themeClasses)
  const currentIndex = keys.indexOf(currentTheme.value)
  currentTheme.value = keys[(currentIndex + 1) % keys.length]
}

const toggleFocusMode = () => {
  focusMode.value = !focusMode.value
  if (focusMode.value) {
     toast.add({ severity: 'info', summary: 'Chế độ tập trung', detail: 'Đã ẩn các thanh công cụ để bạn tập trung đọc.', life: 3000 })
  }
}

const zoomIn = () => scale.value = Math.min(scale.value + 0.2, 3)
const zoomOut = () => scale.value = Math.max(scale.value - 0.2, 0.5)

const prevPage = () => {
  if (currentPage.value > 1) {
    triggerFlip('prev')
    loading.value = true
    currentPage.value--
    inputPage.value = currentPage.value
    scrollContainer.value?.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    triggerFlip('next')
    loading.value = true
    currentPage.value++
    inputPage.value = currentPage.value
    scrollContainer.value?.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const jumpToPage = () => {
  let p = parseInt(inputPage.value)
  if (isNaN(p) || p === currentPage.value) {
    inputPage.value = currentPage.value
    return
  }
  p = Math.max(1, Math.min(p, totalPages.value))
  triggerFlip(p > currentPage.value ? 'next' : 'prev')
  loading.value = true
  currentPage.value = p
  inputPage.value = p
  scrollContainer.value?.scrollTo({ top: 0, behavior: 'smooth' })
}

const fetchEbookData = async () => {
  const { orderId, bookId } = route.params
  console.log('[Reader] Fetching data for:', { orderId, bookId })
  
  loading.value = true
  error.value = null
  
  try {
    const [urlRes, bookRes, annotRes] = await Promise.all([
      apiClient.get(`/api/orders/${orderId}/ebooks/${bookId}/generate-link`).catch(e => {
        console.error('[Reader] generate-link failed:', e)
        throw e
      }),
      apiClient.get(`/api/books/${bookId}`).catch(e => {
        console.error(`[Reader] book detail (${bookId}) failed:`, e)
        throw e
      }),
      apiClient.get(`/api/annotations?book_id=${bookId}`).catch(e => {
        console.warn('[Reader] annotations fetch failed (non-critical):', e)
        return { data: { data: [] } }
      })
    ])

    pdfUrl.value = urlRes.data.url
    console.log('[Reader] PDF URL received:', pdfUrl.value)
    
    book.value = bookRes.data.data || bookRes.data
    annotations.value = annotRes.data.data || []
    
    // Nếu pdfUrl trỏ về localhost mà frontend đang chạy trên domain khác, 
    // chúng ta sẽ thử fix nó dựa trên VITE_API_URL
    if (pdfUrl.value && pdfUrl.value.includes('127.0.0.1:8000')) {
      const apiUrl = import.meta.env.VITE_API_URL || 'https://api.komibook.id.vn'
      pdfUrl.value = pdfUrl.value.replace('http://127.0.0.1:8000', apiUrl)
      console.warn('[Reader] Fixed localhost PDF URL to:', pdfUrl.value)
    }

    // Force HTTPS if the frontend is running on HTTPS
    if (pdfUrl.value && window.location.protocol === 'https:') {
      pdfUrl.value = pdfUrl.value.replace('http://', 'https://')
      console.log('[Reader] Force HTTPS PDF URL:', pdfUrl.value)
    }
  } catch (err) {
    console.error('[Reader] fetchEbookData Error:', err)
    error.value = err.response?.data?.message || 'Có lỗi xảy ra khi xác thực sách. Vui lòng kiểm tra lại quyền truy cập.'
    loading.value = false
  }
}

const onPdfError = (err) => {
  console.error('[Reader] PDF Loading Error:', err)
  error.value = 'Không thể tải nội dung PDF. File có thể bị lỗi hoặc link đã hết hạn.'
  loading.value = false
}

const onPdfLoaded = async (pdfApp) => {
  pdfDocument.value = pdfRef.value?.doc || pdfApp
  if (pdfDocument.value) {
    totalPages.value = pdfDocument.value.numPages
    try {
      const toc = await pdfDocument.value.getOutline()
      outline.value = toc || []
    } catch (e) {
      console.warn('Không thể lấy mục lục:', e)
    }
  }
}

const onPdfRendered = () => {
  loading.value = false
}

const goToTocItem = async (item) => {
  if (item.dest && pdfDocument.value) {
    try {
      let destArray = item.dest
      if (typeof item.dest === 'string') destArray = await pdfDocument.value.getDestination(item.dest)
      if (destArray && destArray[0]) {
        const pageIndex = await pdfDocument.value.getPageIndex(destArray[0])
        const p = pageIndex + 1
        activeTab.value = 'reader'
        triggerFlip(p > currentPage.value ? 'next' : 'prev')
        loading.value = true
        currentPage.value = p
        inputPage.value = p
        scrollContainer.value?.scrollTo({ top: 0, behavior: 'smooth' })
      }
    } catch (e) {
      console.error('Lỗi chuyển trang:', e)
    }
  }
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('vi-VN', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Keyboard navigation
const handleKeydown = (e) => {
  if (activeTab.value !== 'reader') return
  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') nextPage()
  if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') prevPage()
}

onMounted(() => {
  document.body.classList.add('overflow-hidden')
  fetchEbookData()
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.body.classList.remove('overflow-hidden')
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Literata:ital,wght@0,400;0,700;1,400;1,700&display=swap');

.pdf-wrapper {
  user-select: none;
  -webkit-user-select: none;
}

.font-outfit {
  font-family: 'Outfit', sans-serif;
}

.font-literata {
  font-family: 'Literata', serif;
}

.perspective-1000 {
  perspective: 1000px;
}

.preserve-3d {
  transform-style: preserve-3d;
}

.rotate-y-12 {
  transform: rotateY(-12deg);
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slide-up {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up {
  animation: slide-up 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes progress-loading {
  0% { left: -100%; }
  100% { left: 100%; }
}

.animate-progress-loading {
  position: relative;
  animation: progress-loading 2s infinite ease-in-out;
  width: 100%;
}

.no-scrollbar::-webkit-scrollbar {
  display: none;
}

.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.delay-700 {
  animation-delay: 0.7s;
}

.fill-1 {
  font-variation-settings: 'FILL' 1;
}

:deep(.p-drawer-content) {
  padding: 0 1.5rem !important;
}

/* Page Flip Animation */
@keyframes page-flip-next {
  0% { transform: rotateY(0deg) scale(1); opacity: 1; }
  50% { transform: rotateY(-15deg) scale(0.95); opacity: 0.5; }
  100% { transform: rotateY(0deg) scale(1); opacity: 1; }
}

@keyframes page-flip-prev {
  0% { transform: rotateY(0deg) scale(1); opacity: 1; }
  50% { transform: rotateY(15deg) scale(0.95); opacity: 0.5; }
  100% { transform: rotateY(0deg) scale(1); opacity: 1; }
}

.animate-page-flip-next {
  animation: page-flip-next 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.animate-page-flip-prev {
  animation: page-flip-prev 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
</style>
