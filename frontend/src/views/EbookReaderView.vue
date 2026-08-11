<template>
  <div
    class="h-screen w-full flex flex-col md:flex-row transition-all duration-700 overflow-hidden font-inter select-none" 
    :class="[themeClasses[currentTheme].bg, themeClasses[currentTheme].text]"
    @contextmenu.prevent
    role="application"
    aria-label="Trình đọc ebook KomiBook"
  >
    <Toast />
    <!-- ─── PREMIUM DESKTOP SIDEBAR ─── -->
    <nav
      v-show="!focusMode"
      class="hidden md:flex flex-col h-full py-xl w-72 bg-surface-container-low dark:bg-surface-container border-r border-outline-variant/50 shadow-[4px_0_24px_rgba(0,0,0,0.1)] z-50 flex-shrink-0 transition-all duration-500 ease-in-out"
      aria-label="Công cụ trình đọc"
    >
      <!-- Brand & Header -->
      <div class="px-6 mb-xl flex items-center gap-3.5 group cursor-pointer" @click="$router.push('/')" title="Trở về Trang chủ KomiBook">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center transition-transform group-hover:scale-105 shrink-0 overflow-hidden bg-surface-container-lowest p-1 shadow-md border border-outline-variant/20">
          <img v-if="logoExists" src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-contain" />
          <div v-else class="w-full h-full bg-primary flex items-center justify-center rounded-xl text-on-primary">
            <span class="material-symbols-outlined text-2xl">auto_stories</span>
          </div>
        </div>
        <div>
          <h1 class="text-xl font-black text-on-surface tracking-tight uppercase leading-none">KomiBook</h1>
          <p class="text-[10px] text-primary font-extrabold tracking-wider uppercase mt-1">Trình đọc ebook</p>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex-1 px-4 space-y-3">
        <button 
          v-for="tab in tabs" 
          :key="tab.id"
          type="button"
          :aria-pressed="activeTab === tab.id"
          @click="selectTab(tab.id)"
          class="w-full flex items-center gap-4 px-5 py-4 rounded-[22px] transition-all duration-300 group relative overflow-hidden"
          :class="[activeTab === tab.id ? 'bg-primary text-on-primary shadow-xl shadow-primary/20 scale-[1.02]' : 'text-on-surface-variant hover:bg-primary/5 hover:text-primary']"
        >
          <span class="material-symbols-outlined text-[24px] z-10" :style="{ 'font-variation-settings': activeTab === tab.id ? `'FILL' 1` : `'FILL' 0` }">{{ tab.icon }}</span>
          <span class="font-bold text-sm uppercase tracking-wider z-10">{{ tab.label }}</span>
          <div v-if="activeTab === tab.id" class="absolute inset-0 bg-gradient-to-r from-primary to-primary-fixed opacity-10"></div>
        </button>
      </div>

      <!-- Footer Info -->
      <div class="px-6 mt-auto">
        <div
          v-if="ebookVersions.length"
          class="bg-surface-container-high/40 p-4 rounded-[22px] border border-outline-variant/10 mb-4"
        >
          <label for="reader-version" class="block text-sm font-bold text-primary mb-2">
            Phiên bản đang đọc
          </label>
          <select
            id="reader-version"
            v-model.number="selectedEbookVersionId"
            :disabled="switchingVersion || ebookVersions.length === 1"
            class="min-h-11 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-sm font-bold text-on-surface disabled:opacity-70"
            @change="switchEbookVersion"
          >
            <option v-for="version in ebookVersions" :key="version.id" :value="version.id">
              Phiên bản {{ version.version }}
            </option>
          </select>
          <p class="mt-2 text-sm leading-relaxed text-on-surface-variant">
            Quyền đọc bắt đầu từ phiên bản {{ purchaseVersionNumber }}. Các bản cập nhật mới hơn được giữ lại cùng bản đã mua.
          </p>
        </div>

        <div class="bg-surface-container-high/40 backdrop-blur-md p-lg rounded-[28px] border border-outline-variant/10 mb-6 group hover:border-primary/20 transition-all duration-500">
          <div class="flex justify-between items-center mb-3">
            <span class="text-xs font-bold text-primary">Tiến độ đọc</span>
            <span class="text-sm font-bold text-primary">{{ readingProgress }}%</span>
          </div>
          <div class="w-full h-2 bg-surface-container-highest/50 rounded-full overflow-hidden p-[2px]">
            <div class="h-full bg-primary rounded-full transition-all duration-1000 ease-out shadow-[0_0_12px_rgba(var(--primary-rgb),0.4)]" :style="{ width: readingProgress + '%' }"></div>
          </div>
          <p class="text-xs text-on-surface-variant mt-3 text-center font-bold">Bạn đang đọc trang {{ currentPage }} của {{ totalPages }}</p>
        </div>

        <button @click="$router.push('/my-library')" class="w-full flex items-center justify-center gap-3 py-4.5 rounded-[22px] bg-on-surface text-surface font-bold text-xs uppercase tracking-widest hover:opacity-90 active:scale-95 transition-all shadow-xl">
          <span class="material-symbols-outlined text-[22px]">library_books</span>
          Thư viện của tôi
        </button>
      </div>
    </nav>

    <!-- ─── MOBILE PREMIUM HEADER ─── -->
    <header class="md:hidden flex justify-between items-center px-6 w-full h-20 bg-surface-container-low/95 backdrop-blur-xl border-b border-outline-variant/30 z-50 sticky top-0 transition-all duration-300">
      <div class="flex items-center gap-4">
        <button type="button" aria-label="Về Tủ sách" @click="$router.push('/my-library')" class="w-11 h-11 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant transition-colors">
          <span class="material-symbols-outlined text-[22px]">arrow_back</span>
        </button>
        <span class="font-bold text-xl tracking-tighter text-on-surface">Komibook</span>
      </div>
      <div class="flex gap-3">
        <button type="button" aria-label="Mở cài đặt đọc" @click="showSettings = true" class="w-11 h-11 rounded-full bg-primary/10 flex items-center justify-center text-primary transition-colors">
          <span class="material-symbols-outlined text-[22px]">settings</span>
        </button>
      </div>
    </header>

    <div v-if="ebookVersions.length" class="md:hidden w-full bg-surface-container-low px-4 py-3 border-b border-outline-variant/20">
      <label for="reader-version-mobile" class="block text-sm font-bold text-primary mb-1">
        Phiên bản đang đọc
      </label>
      <select
        id="reader-version-mobile"
        v-model.number="selectedEbookVersionId"
        :disabled="switchingVersion || ebookVersions.length === 1"
        class="min-h-11 w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3 py-2 text-sm font-bold text-on-surface disabled:opacity-70"
        @change="switchEbookVersion"
      >
        <option v-for="version in ebookVersions" :key="version.id" :value="version.id">
          Phiên bản {{ version.version }}
        </option>
      </select>
      <p class="mt-1 text-sm text-on-surface-variant">
        Quyền đọc từ phiên bản {{ purchaseVersionNumber }} trở đi.
      </p>
    </div>

    <!-- ─── MAIN CONTENT AREA ─── -->
    <main class="flex-1 flex flex-col h-full overflow-hidden relative transition-all duration-500">
      <!-- Floating Brand Logo Badge (Shown in Focus Mode) -->
      <div 
        v-if="focusMode" 
        class="fixed top-5 left-5 z-[90] flex items-center gap-3 bg-surface-container-lowest/90 backdrop-blur-2xl px-4 py-2 rounded-2xl border border-outline-variant/30 shadow-lg cursor-pointer hover:scale-105 transition-all"
        @click="$router.push('/')"
        title="Trở về Trang chủ KomiBook"
      >
        <div class="w-7 h-7 flex items-center justify-center shrink-0">
          <img v-if="logoExists" src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-contain" />
          <span v-else class="material-symbols-outlined text-primary text-lg">auto_stories</span>
        </div>
        <span class="font-black text-sm text-on-surface tracking-tight uppercase">KomiBook</span>
      </div>
      
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
            <button @click="openAddNoteDialog" class="w-12 h-12 rounded-2xl flex items-center justify-center text-primary bg-primary/10 hover:bg-primary hover:text-on-primary transition-all" title="Thêm ghi chú trang này">
              <span class="material-symbols-outlined text-[26px]">edit_note</span>
            </button>
            <button
              @click="toggleBookmarkCurrentPage"
              class="w-12 h-12 rounded-2xl flex items-center justify-center transition-all cursor-pointer"
              :class="isCurrentPageBookmarked ? 'bg-primary text-on-primary shadow-md' : 'text-on-surface hover:text-primary hover:bg-primary/10'"
              :title="isCurrentPageBookmarked ? 'Bỏ đánh dấu trang này' : 'Đánh dấu trang hiện tại'"
            >
              <span class="material-symbols-outlined text-[26px]" :style="{ 'font-variation-settings': isCurrentPageBookmarked ? `'FILL' 1` : `'FILL' 0` }">bookmark</span>
            </button>
            <button
              @click="toggleViewMode"
              class="w-12 h-12 rounded-2xl flex items-center justify-center transition-all cursor-pointer"
              :class="viewMode === 'double' ? 'bg-primary text-on-primary shadow-md' : 'text-on-surface hover:text-primary hover:bg-primary/10'"
              :title="viewMode === 'double' ? 'Chuyển về 1 trang' : 'Chuyển sang 2 trang song song'"
            >
              <span class="material-symbols-outlined text-[26px]">{{ viewMode === 'double' ? 'menu_book' : 'auto_stories' }}</span>
            </button>
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
            class="w-full relative transition-all duration-700 ease-[cubic-bezier(0.34,1.56,0.64,1)]" 
            :class="viewMode === 'double' ? 'max-w-6xl' : 'max-w-3xl'"
            :style="{ transform: `scale(${scale})`, transformOrigin: 'top center', perspective: '1500px' }"
          >
            
            <!-- Shimmer Loading -->
            <div v-if="loading" class="absolute inset-0 flex flex-col items-center justify-center min-h-[800px] z-20 bg-surface-container-lowest/50 backdrop-blur-sm rounded-[32px]">
              <div class="relative w-24 h-24 mb-8">
                <div class="absolute inset-0 border-4 border-primary/20 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-t-primary rounded-full animate-spin"></div>
              </div>
              <p class="font-bold text-primary uppercase tracking-[0.2em] text-sm animate-pulse">Đang trải thảm tri thức...</p>
            </div>

            <!-- Error State -->
            <div v-if="error" class="bg-surface-container-lowest/90 backdrop-blur-xl p-8 md:p-12 rounded-3xl shadow-xl border border-error/20 text-center animate-fade-in" role="alert">
              <div class="w-24 h-24 bg-error/10 rounded-full flex items-center justify-center mx-auto mb-xl">
                <span class="material-symbols-outlined text-[56px] text-error">error_medley</span>
              </div>
              <h2 class="text-3xl font-bold text-on-surface mb-md tracking-tight">Không thể mở ebook</h2>
              <p class="text-on-surface-variant mb-xl max-w-md mx-auto leading-relaxed">{{ error }}</p>
              <button type="button" @click="fetchEbookData" class="min-h-11 bg-error text-on-error px-10 py-3 rounded-xl text-sm font-bold shadow-lg shadow-error/20 transition-colors">Thử mở lại</button>
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
              <!-- Single Page Mode -->
              <template v-if="viewMode === 'single'">
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
              </template>

              <!-- Dual Page Spread Mode (2 Trang song song) -->
              <template v-else>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-0 relative bg-slate-100 dark:bg-slate-900">
                  <!-- Left Page -->
                  <div class="bg-white overflow-hidden relative shadow-xs">
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
                    <span class="absolute bottom-2 left-3 text-[10px] font-bold text-slate-400 opacity-60">Trang {{ currentPage }}</span>
                  </div>

                  <!-- Book Spine Shadow (Nếp gáy sách ở giữa) -->
                  <div class="hidden md:block absolute left-1/2 top-0 bottom-0 w-6 -translate-x-1/2 bg-gradient-to-r from-black/15 via-black/35 to-black/15 pointer-events-none z-20 shadow-inner"></div>

                  <!-- Right Page -->
                  <div class="bg-white overflow-hidden relative shadow-xs">
                    <VuePdfEmbed 
                      v-if="pdfUrl && hasSecondPage"
                      :source="pdfUrl" 
                      :page="secondPageNumber"
                      :text-layer="false"
                      :annotation-layer="false"
                      class="w-full" 
                    />
                    <div v-else-if="!hasSecondPage" class="w-full h-full min-h-[500px] flex items-center justify-center bg-slate-50 text-slate-400 italic text-xs">
                      (Hết sách)
                    </div>
                    <span v-if="hasSecondPage" class="absolute bottom-2 right-3 text-[10px] font-bold text-slate-400 opacity-60">Trang {{ secondPageNumber }}</span>
                  </div>
                </div>
              </template>
              <!-- Social DRM Watermark Overlay -->
              <div v-if="watermarkEmail" class="absolute inset-0 pointer-events-none z-10 flex flex-wrap justify-around items-center overflow-hidden opacity-[0.06] select-none">
                <div v-for="n in 12" :key="n" class="text-xs font-black rotate-[-30deg] tracking-widest py-12 px-6 whitespace-nowrap text-slate-900">
                  {{ watermarkName }} &lt;{{ watermarkEmail }}&gt;
                </div>
              </div>
            </div>

             <div v-if="!loading && !error" class="mt-12 mb-32 flex flex-col items-center gap-4 animate-fade-in delay-700 pointer-events-none">
                 <div class="h-1.5 w-32 bg-primary/20 rounded-full overflow-hidden">
                    <div class="h-full bg-primary animate-progress-loading"></div>
                 </div>
                 <p class="text-xs font-bold text-on-surface-variant">Mẹo: Vuốt hoặc dùng phím mũi tên để chuyển trang</p>
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
                class="w-14 h-14 bg-outline-variant/20 border-none text-center rounded-2xl font-bold text-2xl text-on-surface focus:bg-primary focus:text-on-primary transition-all outline-none"
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
          <h2 class="text-xl font-bold text-on-surface tracking-tight">{{ activeTabInfo?.label }}</h2>
        </div>
      </template>

      <!-- ══ DRAWER TAB: CONTENTS ══ -->
      <div v-show="activeTab === 'contents'" class="h-full overflow-y-auto no-scrollbar scroll-smooth pb-8">
        <div class="max-w-4xl mx-auto">
          <header class="flex flex-col border-b border-outline-variant/20 pb-6 mb-8 gap-4">
            <div class="animate-slide-up">
              <div class="flex items-center gap-3 mb-2">
                 <div class="w-1.5 h-6 bg-primary rounded-full"></div>
                 <span class="text-xs font-bold text-primary uppercase tracking-[0.3em]">Hành trình khám phá</span>
              </div>
              <h2 class="text-3xl font-bold text-on-surface tracking-tight">Mục lục tác phẩm</h2>
            </div>
            <div class="inline-flex w-max items-center gap-3 bg-surface-container-high/50 px-4 py-2 rounded-xl border border-outline-variant/10 animate-fade-in">
               <span class="material-symbols-outlined text-primary text-sm">list_alt</span>
               <span class="text-sm font-bold text-on-surface tracking-tighter">{{ outline.length }} Chương & Mục</span>
            </div>
          </header>

          <div v-if="!outline || outline.length === 0" class="flex flex-col items-center justify-center py-16 bg-surface-container-low/40 rounded-3xl border-2 border-dashed border-outline-variant/20 animate-fade-in">
            <div class="w-20 h-20 bg-surface-container-high rounded-full flex items-center justify-center mb-6">
              <span class="material-symbols-outlined text-[40px] text-outline/30">content_paste_off</span>
            </div>
            <h3 class="text-lg font-bold text-on-surface mb-1">Chưa có mục lục</h3>
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
                  <span class="text-sm font-bold tracking-tighter">{{ (index + 1).toString().padStart(2, '0') }}</span>
                </div>
                <div>
                  <span class="text-base font-bold text-on-surface group-hover:text-primary transition-colors block leading-tight">{{ item.title }}</span>
                  <span class="text-xs font-bold text-outline mt-1 block">Chương tác phẩm</span>
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
        <div class="max-w-4xl mx-auto space-y-6">
          <header class="flex flex-col gap-6 border-b border-outline-variant/20 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-slide-up">
              <div>
                <div class="flex items-center gap-3 mb-1">
                   <div class="w-1.5 h-6 bg-secondary rounded-full"></div>
                   <span class="text-xs font-bold text-secondary uppercase tracking-[0.3em]">Kho tàng cảm xúc</span>
                </div>
                <h2 class="text-3xl font-bold text-on-surface tracking-tight">Ghi chú & Đánh dấu</h2>
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="openAddNoteDialog"
                  class="min-h-10 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-on-primary font-bold text-xs shadow-md hover:bg-primary/90 transition-all cursor-pointer"
                >
                  <span class="material-symbols-outlined text-lg">add_notes</span>
                  <span>Thêm ghi chú</span>
                </button>
                <button
                  type="button"
                  @click="exportNotesMarkdown"
                  class="min-h-10 inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-surface-container-high border border-outline-variant/30 text-on-surface-variant font-bold text-xs hover:bg-primary/10 hover:text-primary transition-all cursor-pointer"
                  title="Xuất danh sách ghi chú ra file Markdown (.md)"
                >
                  <span class="material-symbols-outlined text-lg">download</span>
                  <span class="hidden sm:inline">Xuất .md</span>
                </button>
              </div>
            </div>

            <!-- Search input -->
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
              <input
                type="text"
                v-model="annotationSearchQuery"
                placeholder="Tìm kiếm từ khóa trong ghi chú hoặc trích dẫn..."
                class="w-full h-11 pl-10 pr-4 rounded-xl border border-outline-variant/30 bg-surface-container-lowest text-xs font-bold text-on-surface focus:border-primary outline-none"
              />
            </div>

            <!-- Filter tabs -->
            <div class="flex flex-wrap gap-2 animate-fade-in">
              <button @click="annotationFilter = 'all'" :class="annotationFilter === 'all' ? 'bg-primary text-on-primary shadow-md shadow-primary/20' : 'bg-surface-container-high text-on-surface-variant'" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">Tất cả ({{ annotations.length }})</button>
              <button @click="annotationFilter = 'highlight'" :class="annotationFilter === 'highlight' ? 'bg-secondary text-on-secondary shadow-md shadow-secondary/20' : 'bg-surface-container-high text-on-surface-variant'" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">Highlights</button>
              <button @click="annotationFilter = 'note'" :class="annotationFilter === 'note' ? 'bg-tertiary text-on-tertiary shadow-md shadow-tertiary/20' : 'bg-surface-container-high text-on-surface-variant'" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">Ghi chú</button>
              <button @click="annotationFilter = 'bookmark'" :class="annotationFilter === 'bookmark' ? 'bg-primary-container text-primary border border-primary/30 shadow-xs' : 'bg-surface-container-high text-on-surface-variant'" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">Bookmarks (Đã ghim)</button>
            </div>
          </header>

          <div v-if="filteredAnnotations.length === 0" class="flex flex-col items-center justify-center py-16 bg-surface-container-low/40 rounded-3xl border-2 border-dashed border-outline-variant/20 animate-fade-in">
            <div class="w-20 h-20 bg-surface-container-high rounded-full flex items-center justify-center mb-4">
              <span class="material-symbols-outlined text-[40px] text-outline/30">draw_abstract</span>
            </div>
            <h3 class="text-lg font-bold text-on-surface mb-1">Chưa có dữ liệu phù hợp</h3>
            <p class="text-xs text-on-surface-variant font-medium opacity-60">Hãy bắt đầu tạo ghi chú hoặc đánh dấu trang đầu tiên cho cuốn sách này.</p>
          </div>

          <div class="grid grid-cols-1 gap-4 items-start">
            <article 
              v-for="(note, idx) in filteredAnnotations" 
              :key="note.id"
              class="bg-surface-container-lowest rounded-3xl shadow-xs p-5 relative overflow-hidden flex flex-col gap-4 border border-outline-variant/10 hover:shadow-lg transition-all duration-300 group animate-slide-up"
              :style="{ animationDelay: `${idx * 60}ms` }"
            >
              <!-- Color Indicator Bar -->
              <div class="absolute left-0 top-0 bottom-0 w-2 transition-all group-hover:w-3" :style="{ backgroundColor: note.color || '#eab308' }"></div>
              
              <div class="flex justify-between items-center pl-2">
                <!-- Jump to page clickable header -->
                <button
                  type="button"
                  @click="jumpToNotePage(note)"
                  class="flex items-center gap-2 group/jump cursor-pointer"
                  title="Nhấn để chuyển đến trang này"
                >
                  <div class="w-8 h-8 rounded-full bg-primary/10 group-hover/jump:bg-primary group-hover/jump:text-on-primary flex items-center justify-center text-primary transition-colors">
                    <span class="material-symbols-outlined text-sm">{{ note.type === 'bookmark' ? 'bookmark' : 'auto_stories' }}</span>
                  </div>
                  <span class="text-xs font-bold text-on-surface group-hover/jump:text-primary transition-colors">
                    Trang {{ note.page_number || note.page }} {{ note.type === 'bookmark' ? '(Đã ghim)' : '' }}
                  </span>
                  <span class="material-symbols-outlined text-xs text-outline group-hover/jump:text-primary opacity-0 group-hover/jump:opacity-100 transition-opacity">open_in_new</span>
                </button>

                <div class="flex items-center gap-3">
                  <span class="text-[11px] font-bold text-outline">{{ formatDate(note.created_at) }}</span>
                  <!-- Action Buttons: Edit & Delete -->
                  <div class="flex items-center gap-1">
                    <button
                      v-if="note.type !== 'bookmark'"
                      type="button"
                      @click.stop="openEditDialog(note)"
                      class="w-7 h-7 rounded-lg hover:bg-primary/10 hover:text-primary text-outline flex items-center justify-center transition-colors cursor-pointer"
                      title="Chỉnh sửa ghi chú"
                    >
                      <span class="material-symbols-outlined text-base">edit</span>
                    </button>
                    <button
                      type="button"
                      @click.stop="deleteAnnotation(note.id)"
                      class="w-7 h-7 rounded-lg hover:bg-error/10 hover:text-error text-outline flex items-center justify-center transition-colors cursor-pointer"
                      title="Xóa bản ghi này"
                    >
                      <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                  </div>
                </div>
              </div>

              <div v-if="note.highlighted_text" class="flex flex-col gap-2">
                <blockquote class="font-literata text-xs text-on-surface italic pl-4 border-l-3 border-outline-variant/30 my-0.5 leading-relaxed opacity-90">
                  "{{ note.highlighted_text }}"
                </blockquote>
                <div class="flex justify-end pr-1">
                  <button
                    type="button"
                    @click.stop="openQuoteCardModal(note)"
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-primary/10 text-primary text-[11px] font-bold hover:bg-primary hover:text-on-primary transition-all cursor-pointer shadow-xs"
                    title="Tạo ảnh thẻ trích dẫn nghệ thuật để chia sẻ"
                  >
                    <span class="material-symbols-outlined text-sm">share</span>
                    <span>Tạo thẻ chia sẻ</span>
                  </button>
                </div>
              </div>

              <div v-if="note.note_content" class="bg-surface-container-low p-4 rounded-2xl border border-outline-variant/10 relative">
                <div class="flex items-center gap-1.5 mb-1.5 text-primary opacity-70">
                  <span class="material-symbols-outlined text-xs">{{ note.type === 'bookmark' ? 'bookmark' : 'edit_square' }}</span>
                  <span class="text-[9px] font-bold uppercase tracking-wider">{{ note.type === 'bookmark' ? 'Đánh dấu trang' : 'Suy tư của bạn' }}</span>
                </div>
                <p class="text-xs font-semibold text-on-surface leading-relaxed whitespace-pre-wrap">{{ note.note_content }}</p>
              </div>
            </article>
          </div>
        </div>
      </div>

      <!-- ══ DRAWER TAB: SEARCH ══ -->
      <div v-show="activeTab === 'search'" class="h-full overflow-y-auto no-scrollbar scroll-smooth pb-8">
        <div class="max-w-4xl mx-auto space-y-6">
          <header class="flex flex-col gap-4 border-b border-outline-variant/20 pb-6">
            <div class="animate-slide-up">
              <div class="flex items-center gap-3 mb-1">
                 <div class="w-1.5 h-6 bg-primary rounded-full"></div>
                 <span class="text-xs font-bold text-primary uppercase tracking-[0.3em]">Tra cứu toàn văn</span>
              </div>
              <h2 class="text-3xl font-bold text-on-surface tracking-tight">Tìm kiếm trong Sách</h2>
              <p class="text-on-surface-variant font-medium mt-1 text-xs">Tìm từ khóa trực tiếp trong toàn bộ nội dung văn bản của cuốn sách.</p>
            </div>

            <form @submit.prevent="searchBookText" class="flex gap-2">
              <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
                <input
                  type="text"
                  v-model="searchKeyword"
                  placeholder="Nhập từ khóa cần tìm trong sách..."
                  class="w-full h-11 pl-10 pr-10 rounded-xl border border-outline-variant/30 bg-surface-container-lowest text-xs font-bold text-on-surface focus:border-primary outline-none"
                />
                <button v-if="searchKeyword" type="button" @click="clearBookSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface">
                  <span class="material-symbols-outlined text-base">close</span>
                </button>
              </div>
              <button
                type="submit"
                :disabled="isSearchingBook || !searchKeyword.trim()"
                class="px-5 py-2.5 rounded-xl bg-primary text-on-primary font-bold text-xs shadow-md hover:bg-primary/90 disabled:opacity-50 transition-all flex items-center gap-2 shrink-0 cursor-pointer"
              >
                <span v-if="isSearchingBook" class="material-symbols-outlined text-base animate-spin">sync</span>
                <span>{{ isSearchingBook ? `Đang quét ${searchProgress}%` : 'Tìm kiếm' }}</span>
              </button>
            </form>
          </header>

          <!-- Searching Progress Bar -->
          <div v-if="isSearchingBook" class="space-y-2 py-4">
            <div class="flex justify-between text-xs font-bold text-primary">
              <span>Đang quét các trang sách...</span>
              <span>{{ searchProgress }}%</span>
            </div>
            <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
              <div class="h-full bg-primary transition-all duration-200" :style="{ width: searchProgress + '%' }"></div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="!isSearchingBook && searchResults.length === 0 && searchKeyword" class="flex flex-col items-center justify-center py-16 bg-surface-container-low/40 rounded-3xl border-2 border-dashed border-outline-variant/20">
            <div class="w-16 h-16 bg-surface-container-high rounded-full flex items-center justify-center mb-3 text-outline">
              <span class="material-symbols-outlined text-3xl">search_off</span>
            </div>
            <h3 class="text-sm font-bold text-on-surface mb-1">Không tìm thấy kết quả</h3>
            <p class="text-xs text-on-surface-variant font-medium opacity-60">Hãy thử nhập từ khóa ngắn hơn hoặc bằng từ khác.</p>
          </div>

          <!-- Search Results List -->
          <div v-if="!isSearchingBook && searchResults.length > 0" class="space-y-3">
            <div class="flex items-center justify-between text-xs font-bold text-on-surface-variant px-1">
              <span>Tìm thấy <strong>{{ searchResults.length }}</strong> đoạn văn chứa từ khóa</span>
            </div>

            <div class="grid grid-cols-1 gap-3">
              <button
                v-for="res in searchResults"
                :key="res.id"
                @click="jumpToSearchResult(res)"
                class="w-full text-left bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/15 hover:border-primary/40 hover:shadow-md transition-all group cursor-pointer flex flex-col gap-2"
              >
                <div class="flex items-center justify-between">
                  <span class="inline-flex items-center gap-1.5 text-xs font-bold text-primary group-hover:underline">
                    <span class="material-symbols-outlined text-sm">auto_stories</span>
                    <span>Trang {{ res.page }}</span>
                  </span>
                  <span class="material-symbols-outlined text-xs text-outline group-hover:text-primary transition-colors">east</span>
                </div>
                <p class="text-xs font-literata leading-relaxed text-on-surface-variant group-hover:text-on-surface transition-colors">
                  {{ res.snippet }}
                </p>
              </button>
            </div>
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
                  <img v-if="book?.cover_image" :src="book.cover_image" :alt="`Bìa sách ${book.title}`" class="w-full h-full object-contain" />
               </div>
            </div>

            <div class="text-center space-y-4">
              <span class="inline-block px-3 py-1 bg-primary/10 text-primary text-xs font-bold rounded-full border border-primary/20">Ebook</span>
              <h1 class="text-3xl font-bold text-on-surface leading-tight tracking-tight">{{ book?.title }}</h1>
              <p class="text-base text-on-surface-variant font-bold tracking-tight">Bởi {{ book?.author }}</p>
            </div>
          </div>

          <!-- Reading Session Analytics Card -->
          <div class="bg-surface-container-low p-5 rounded-3xl border border-outline-variant/15 mb-8 space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                  <span class="material-symbols-outlined text-lg">timer</span>
                </div>
                <h4 class="font-bold text-sm text-on-surface">Thống kê phiên đọc hiện tại</h4>
              </div>
              <span class="text-[10px] font-bold text-primary bg-primary/10 px-2.5 py-1 rounded-full animate-pulse">Đang ghi nhận</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div class="bg-surface-container-lowest p-3 rounded-2xl text-center border border-outline-variant/10">
                <span class="text-[9px] font-bold uppercase text-outline block mb-1">Thời gian đọc</span>
                <span class="text-sm font-bold text-on-surface">{{ formattedSessionTime }}</span>
              </div>
              <div class="bg-surface-container-lowest p-3 rounded-2xl text-center border border-outline-variant/10">
                <span class="text-[9px] font-bold uppercase text-outline block mb-1">Trang đã xem</span>
                <span class="text-sm font-bold text-on-surface">{{ sessionPagesRead }} trang</span>
              </div>
              <div class="bg-surface-container-lowest p-3 rounded-2xl text-center border border-outline-variant/10">
                <span class="text-[9px] font-bold uppercase text-outline block mb-1">Tốc độ đọc</span>
                <span class="text-sm font-bold text-on-surface">{{ readingSpeed }}</span>
              </div>
              <div class="bg-surface-container-lowest p-3 rounded-2xl text-center border border-outline-variant/10">
                <span class="text-[9px] font-bold uppercase text-outline block mb-1">Thời gian còn lại</span>
                <span class="text-sm font-bold text-primary">{{ estRemainingTime }}</span>
              </div>
            </div>
          </div>

          <!-- Quick Stats Grid -->
          <div class="grid grid-cols-2 gap-3 mb-8">
            <div v-for="stat in bookStats" :key="stat.label" class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/10 shadow-sm hover:border-primary/30 transition-all text-center">
              <span class="material-symbols-outlined text-primary mb-2 text-xl">{{ stat.icon }}</span>
              <p class="text-[8px] uppercase font-bold text-outline tracking-widest mb-1">{{ stat.label }}</p>
              <p class="text-base font-bold text-on-surface">{{ stat.value }}</p>
            </div>
          </div>

          <!-- Description -->
          <div class="prose max-w-none mb-10">
            <div class="flex items-center gap-2 mb-4">
               <div class="w-1.5 h-5 bg-primary rounded-full"></div>
               <h3 class="text-xl font-bold text-on-surface tracking-tight">Tóm tắt nội dung</h3>
            </div>
            <p class="font-literata text-sm text-on-surface-variant leading-relaxed text-justify opacity-80">{{ book?.description }}</p>
          </div>

          <button class="w-full bg-primary text-on-primary py-4 rounded-2xl font-bold text-xs uppercase tracking-widest shadow-xl shadow-primary/20 flex items-center justify-center gap-2 hover:opacity-90 active:scale-95 transition-all">
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
        <span class="text-xs font-bold mt-2">{{ tab.label }}</span>
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
            <h2 class="text-2xl font-bold text-on-surface tracking-tight">Cá nhân hóa</h2>
         </div>
      </template>
      
      <div class="flex flex-col gap-12 py-8">
        <!-- Themes -->
        <div>
          <h3 class="text-sm font-bold text-primary mb-8 flex items-center gap-2">
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
              <span class="text-xs font-bold text-on-surface">{{ theme.name }}</span>
              <div v-if="currentTheme === key" class="absolute top-2 right-2">
                 <span class="material-symbols-outlined text-primary text-sm">check_circle</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Typography -->
        <div>
          <h3 class="text-sm font-bold text-primary mb-8 flex items-center gap-2">
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
                      <p class="text-4xl font-bold text-primary tracking-tighter">{{ Math.round(scale * 100) }}%</p>
                      <p class="text-xs font-bold text-on-surface-variant mt-1">Độ phóng đại</p>
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
                 <h4 class="text-lg font-bold text-on-surface leading-tight">Chế độ tập trung</h4>
                 <p class="text-sm text-on-surface-variant font-medium mt-1">Ẩn bớt các thanh công cụ để đắm chìm vào từng trang sách.</p>
              </div>
              <button @click="toggleFocusMode" class="w-16 h-10 rounded-full relative transition-all duration-500 overflow-hidden border border-outline-variant/30" :class="focusMode ? 'bg-primary' : 'bg-surface-container-highest'">
                 <div class="absolute top-1 left-1 w-8 h-8 rounded-full bg-white transition-all duration-500 shadow-md" :class="focusMode ? 'translate-x-6' : 'translate-x-0'"></div>
              </button>
           </div>
        </div>

        <button @click="showSettings = false" class="mt-auto w-full py-5 bg-on-surface text-surface rounded-[24px] font-bold text-xs uppercase tracking-[0.3em] shadow-2xl hover:opacity-90 active:scale-95 transition-all">Tiếp tục hành trình</button>
      </div>
    </Drawer>

    <!-- Intellectual Property Print Consent Dialog -->
    <Dialog v-model:visible="showPrintConsent" modal header="Cam kết Bảo vệ Quyền Sở hữu Trí tuệ" :style="{ width: '90vw', maxWidth: '550px' }">
      <div class="space-y-4 text-xs text-slate-600 leading-relaxed">
        <div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100 flex items-start gap-3 text-slate-700">
          <i class="pi pi-shield text-indigo-700 text-lg mt-0.5 shrink-0"></i>
          <div>
            <h4 class="font-bold text-indigo-900 uppercase tracking-wider text-[11px]">Cam kết Quyền Tác Giả</h4>
            <p class="mt-1">
              Tác phẩm này được đăng ký bản quyền số và thuộc sở hữu trí tuệ hợp pháp của chủ sở hữu quyền hoặc Nhà xuất bản.
            </p>
          </div>
        </div>

        <p class="text-slate-700">
          Bằng việc nhấn <strong>"Đồng ý & Tiến hành"</strong>, bạn cam kết thực hiện các điều khoản sau:
        </p>
        <ul class="list-disc pl-5 space-y-1.5">
          <li>Chỉ thực hiện in ấn phục vụ nhu cầu đọc cá nhân hoặc nghiên cứu phi thương mại.</li>
          <li>Tuyệt đối không sao chép, chia sẻ, số hóa lại hoặc phân phối bất hợp pháp bản in này lên không gian mạng.</li>
          <li>Mọi bản in/tải đều có dấu bản quyền số nhúng chìm chứa thông tin tài khoản của bạn để xác thực nguồn gốc sở hữu.</li>
        </ul>
      </div>
      <template #footer>
        <Button label="Hủy bỏ" class="p-button-text p-button-sm text-xs" @click="showPrintConsent = false" />
        <Button label="Đồng ý & Tiến hành" class="p-button-primary bg-indigo-600 text-white p-button-sm text-xs font-bold" @click="confirmPrint" />
      </template>
    </Dialog>

    <!-- Panel Nổi Thêm Ghi chú bên phải (Không che/làm mờ trang sách) -->
    <transition name="slide-panel">
      <div
        v-if="showAddNoteDialog"
        class="fixed right-4 sm:right-6 top-20 sm:top-24 bottom-20 sm:bottom-24 w-[calc(100vw-32px)] sm:w-[380px] bg-surface-container-lowest/95 backdrop-blur-2xl rounded-3xl border border-outline-variant/30 shadow-[0_20px_60px_rgba(0,0,0,0.18)] z-[95] flex flex-col p-5 overflow-y-auto text-on-surface"
      >
        <header class="flex items-center justify-between pb-3 border-b border-outline-variant/20 mb-4">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
              <span class="material-symbols-outlined text-lg">edit_note</span>
            </div>
            <div>
              <h3 class="font-bold text-sm text-on-surface">Thêm Ghi chú</h3>
              <p class="text-[10px] text-primary font-bold">Trang {{ noteForm.page_number }} / {{ totalPages || 1 }}</p>
            </div>
          </div>
          <button type="button" @click="showAddNoteDialog = false" class="w-8 h-8 rounded-full hover:bg-surface-container-high text-on-surface-variant flex items-center justify-center transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-base">close</span>
          </button>
        </header>

        <form @submit.prevent="saveNewAnnotation" class="flex-1 flex flex-col justify-between space-y-4">
          <div class="space-y-3">
            <!-- Vị trí trang -->
            <div class="flex items-center justify-between bg-surface-container-low p-2.5 rounded-xl border border-outline-variant/15">
              <label class="text-xs font-bold text-on-surface-variant">Vị trí trang</label>
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-bold text-primary">Trang</span>
                <input type="number" v-model.number="noteForm.page_number" min="1" :max="totalPages || 9999" class="w-14 h-8 bg-surface-container-lowest text-center rounded-lg border border-outline-variant/30 font-bold text-xs outline-none focus:border-primary" />
              </div>
            </div>

            <!-- Màu điểm nhấn -->
            <div>
              <label class="block text-[11px] font-bold text-on-surface-variant mb-1.5">Màu điểm nhấn</label>
              <div class="flex items-center justify-between bg-surface-container-low p-2 rounded-xl border border-outline-variant/15">
                <button
                  v-for="c in colorPresets"
                  :key="c.value"
                  type="button"
                  @click="noteForm.color = c.value"
                  class="w-7 h-7 rounded-full border-2 transition-all flex items-center justify-center cursor-pointer"
                  :class="noteForm.color === c.value ? 'border-primary scale-110 shadow-sm' : 'border-transparent opacity-75 hover:opacity-100'"
                  :style="{ backgroundColor: c.value }"
                  :title="c.name"
                >
                  <span v-if="noteForm.color === c.value" class="material-symbols-outlined text-xs text-white drop-shadow">check</span>
                </button>
              </div>
            </div>

            <!-- Đoạn trích dẫn -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-[11px] font-bold text-on-surface-variant">Đoạn trích dẫn</label>
                <span class="text-[9px] text-outline font-semibold">Tùy chọn</span>
              </div>
              <textarea
                v-model="noteForm.highlighted_text"
                rows="3"
                placeholder="Nhìn trang sách bên cạnh để trích dẫn..."
                class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-2.5 text-xs font-literata italic text-on-surface focus:border-primary outline-none resize-none"
              ></textarea>
            </div>

            <!-- Suy tư / Ghi chú -->
            <div>
              <label class="block text-[11px] font-bold text-on-surface-variant mb-1">Ghi chú & Suy tư <span class="text-error">*</span></label>
              <textarea
                v-model="noteForm.note_content"
                rows="4"
                placeholder="Viết cảm nhận, đúc kết của bạn..."
                class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-2.5 text-xs font-medium text-on-surface focus:border-primary outline-none resize-none"
                required
              ></textarea>
            </div>
          </div>

          <!-- Footer hành động -->
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-outline-variant/15 mt-auto">
            <button type="button" @click="showAddNoteDialog = false" class="px-4 py-2 rounded-xl text-xs font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors cursor-pointer">Hủy</button>
            <button type="submit" :disabled="submittingNote" class="px-5 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold shadow-md hover:bg-primary/90 disabled:opacity-50 transition-all cursor-pointer">
              {{ submittingNote ? 'Đang lưu...' : 'Lưu ghi chú' }}
            </button>
          </div>
        </form>
      </div>
    </transition>

    <!-- Panel Nổi Chỉnh sửa Ghi chú bên phải -->
    <transition name="slide-panel">
      <div
        v-if="showEditNoteDialog"
        class="fixed right-4 sm:right-6 top-20 sm:top-24 bottom-20 sm:bottom-24 w-[calc(100vw-32px)] sm:w-[380px] bg-surface-container-lowest/95 backdrop-blur-2xl rounded-3xl border border-outline-variant/30 shadow-[0_20px_60px_rgba(0,0,0,0.18)] z-[95] flex flex-col p-5 overflow-y-auto text-on-surface"
      >
        <header class="flex items-center justify-between pb-3 border-b border-outline-variant/20 mb-4">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
              <span class="material-symbols-outlined text-lg">edit</span>
            </div>
            <h3 class="font-bold text-sm text-on-surface">Chỉnh sửa Ghi chú</h3>
          </div>
          <button type="button" @click="showEditNoteDialog = false" class="w-8 h-8 rounded-full hover:bg-surface-container-high text-on-surface-variant flex items-center justify-center transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-base">close</span>
          </button>
        </header>

        <form @submit.prevent="saveEditAnnotation" class="flex-1 flex flex-col justify-between space-y-4">
          <div class="space-y-3">
            <!-- Màu điểm nhấn -->
            <div>
              <label class="block text-[11px] font-bold text-on-surface-variant mb-1.5">Màu điểm nhấn</label>
              <div class="flex items-center justify-between bg-surface-container-low p-2 rounded-xl border border-outline-variant/15">
                <button
                  v-for="c in colorPresets"
                  :key="c.value"
                  type="button"
                  @click="editNoteForm.color = c.value"
                  class="w-7 h-7 rounded-full border-2 transition-all flex items-center justify-center cursor-pointer"
                  :class="editNoteForm.color === c.value ? 'border-primary scale-110 shadow-sm' : 'border-transparent opacity-75 hover:opacity-100'"
                  :style="{ backgroundColor: c.value }"
                  :title="c.name"
                >
                  <span v-if="editNoteForm.color === c.value" class="material-symbols-outlined text-xs text-white drop-shadow">check</span>
                </button>
              </div>
            </div>

            <!-- Đoạn trích dẫn -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-[11px] font-bold text-on-surface-variant">Đoạn trích dẫn</label>
                <span class="text-[9px] text-outline font-semibold">Tùy chọn</span>
              </div>
              <textarea
                v-model="editNoteForm.highlighted_text"
                rows="3"
                placeholder="Nhập đoạn văn trích dẫn..."
                class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-2.5 text-xs font-literata italic text-on-surface focus:border-primary outline-none resize-none"
              ></textarea>
            </div>

            <!-- Nội dung suy tư -->
            <div>
              <label class="block text-[11px] font-bold text-on-surface-variant mb-1">Ghi chú & Suy tư <span class="text-error">*</span></label>
              <textarea
                v-model="editNoteForm.note_content"
                rows="4"
                placeholder="Nội dung ghi chú..."
                class="w-full rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-2.5 text-xs font-medium text-on-surface focus:border-primary outline-none resize-none"
                required
              ></textarea>
            </div>
          </div>

          <!-- Footer hành động -->
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-outline-variant/15 mt-auto">
            <button type="button" @click="showEditNoteDialog = false" class="px-4 py-2 rounded-xl text-xs font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors cursor-pointer">Hủy</button>
            <button type="submit" :disabled="submittingNote" class="px-5 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold shadow-md hover:bg-primary/90 disabled:opacity-50 transition-all cursor-pointer">
              {{ submittingNote ? 'Đang lưu...' : 'Cập nhật' }}
            </button>
          </div>
        </form>
      </div>
    </transition>

    <!-- Side Panel Tạo Thẻ Trích Dẫn Chia Sẻ -->
    <transition name="slide-panel">
      <div
        v-if="showQuoteCardModal"
        class="fixed right-4 sm:right-6 top-20 sm:top-24 bottom-20 sm:bottom-24 w-[calc(100vw-32px)] sm:w-[420px] bg-surface-container-lowest/95 backdrop-blur-2xl rounded-3xl border border-outline-variant/30 shadow-[0_20px_60px_rgba(0,0,0,0.22)] z-[96] flex flex-col p-5 overflow-y-auto text-on-surface"
      >
        <header class="flex items-center justify-between pb-3 border-b border-outline-variant/20 mb-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
              <span class="material-symbols-outlined text-lg">style</span>
            </div>
            <div>
              <h3 class="font-bold text-sm text-on-surface">Thẻ Trích Dẫn Chia Sẻ</h3>
              <p class="text-[10px] text-on-surface-variant font-medium">Tải về ảnh PNG nghệ thuật để đăng Story/MXH</p>
            </div>
          </div>
          <button type="button" @click="showQuoteCardModal = false" class="w-8 h-8 rounded-full hover:bg-surface-container-high text-on-surface-variant flex items-center justify-center transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-base">close</span>
          </button>
        </header>

        <div class="flex-1 flex flex-col justify-between space-y-4">
          <!-- Theme Selection -->
          <div>
            <label class="block text-[11px] font-bold text-on-surface-variant mb-2">Chọn phong cách Thẻ</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="t in quoteCardThemes"
                :key="t.id"
                type="button"
                @click="selectedQuoteTheme = t.id"
                class="p-2.5 rounded-xl border transition-all text-left flex items-center gap-2.5 cursor-pointer"
                :class="selectedQuoteTheme === t.id ? 'border-primary ring-2 ring-primary/20 bg-surface-container-high' : 'border-outline-variant/20 bg-surface-container-low hover:border-outline-variant/40'"
              >
                <div class="w-6 h-6 rounded-full shadow-inner border border-white/20 shrink-0" :style="{ background: t.bg }"></div>
                <span class="text-xs font-bold text-on-surface truncate">{{ t.name }}</span>
              </button>
            </div>
          </div>

          <!-- Preview Card -->
          <div class="flex-1 flex flex-col items-center justify-center">
            <div
              class="w-full aspect-[4/5] rounded-2xl p-6 shadow-xl flex flex-col justify-between relative overflow-hidden transition-all duration-300 border border-white/10"
              :style="{ background: currentQuoteThemeObj.bg, color: currentQuoteThemeObj.text }"
            >
              <!-- Top Header -->
              <div class="flex items-center justify-between opacity-70 text-[10px] font-bold uppercase tracking-wider">
                <span class="truncate max-w-[200px]">{{ book?.title || 'Komibook Ebook' }}</span>
                <span>Trang {{ selectedQuoteForCard.page }}</span>
              </div>

              <!-- Quote Content -->
              <div class="my-auto space-y-3 py-4">
                <span class="material-symbols-outlined text-4xl opacity-20 block">format_quote</span>
                <p class="font-literata text-xs italic leading-relaxed font-normal line-clamp-6">
                  "{{ selectedQuoteForCard.text }}"
                </p>
                <div class="h-0.5 w-10 rounded-full" :style="{ backgroundColor: currentQuoteThemeObj.accent }"></div>
                <p class="text-xs font-bold opacity-80">— {{ book?.author || 'Tác giả' }}</p>
              </div>

              <!-- Bottom Footer -->
              <div class="flex items-center justify-between pt-3 border-t border-white/10 text-[9px] opacity-60 font-semibold">
                <span class="flex items-center gap-1">
                  <span class="material-symbols-outlined text-xs">auto_stories</span>
                  <span>Komibook Reader</span>
                </span>
                <span>komibook.vn</span>
              </div>
            </div>
          </div>

          <!-- Action Footer -->
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-outline-variant/15 mt-auto">
            <button type="button" @click="showQuoteCardModal = false" class="px-4 py-2 rounded-xl text-xs font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors cursor-pointer">Đóng</button>
            <button type="button" @click="downloadQuoteCardImage" class="px-5 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold shadow-md hover:bg-primary/90 transition-all flex items-center gap-2 cursor-pointer">
              <span class="material-symbols-outlined text-base">download</span>
              <span>Tải ảnh PNG</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, shallowRef, markRaw, computed, defineAsyncComponent, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Toast from 'primevue/toast'
import Drawer from 'primevue/drawer'
import Dialog from 'primevue/dialog'
import apiClient from '@/services/axios'
import { readApiData } from '@/services/apiContract'

const VuePdfEmbed = defineAsyncComponent(() => import('vue-pdf-embed'))

const route = useRoute()
const toast = useToast()

const activeTab = ref('reader')
const loading = ref(true)
const pdfUrl = ref(null)
const error = ref(null)
const ebookVersions = ref([])
const selectedEbookVersionId = ref(null)
const purchaseVersionId = ref(null)
const switchingVersion = ref(false)
const scale = ref(1.0)
const scrollContainer = ref(null)
const book = ref(null)
const focusMode = ref(false)
const logoExists = ref(false)
const viewMode = ref(localStorage.getItem('readerViewMode') || 'single')
const annotationFilter = ref('all')

const secondPageNumber = computed(() => currentPage.value + 1)
const hasSecondPage = computed(() => secondPageNumber.value <= totalPages.value)
const pageStep = computed(() => (viewMode.value === 'double' ? 2 : 1))

const toggleViewMode = () => {
  viewMode.value = viewMode.value === 'single' ? 'double' : 'single'
  localStorage.setItem('readerViewMode', viewMode.value)
  toast.add({
    severity: 'info',
    summary: 'Chế độ đọc',
    detail: viewMode.value === 'double' ? 'Đã chuyển sang đọc 2 trang song song' : 'Đã chuyển sang đọc 1 trang',
    life: 2500
  })
}

const watermarkEmail = ref('')
const watermarkName = ref('')
const showPrintConsent = ref(false)

const currentPage = ref(1)
const totalPages = ref(0)
const inputPage = ref(1)
const progressVersion = ref(null)
const savedPage = ref(null)
let progressTimer = null

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

const endDrag = () => {
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
const pdfDocument = shallowRef(null)

const tabs = [
  { id: 'reader', label: 'Trình đọc', icon: 'auto_stories' },
  { id: 'contents', label: 'Mục lục', icon: 'format_list_bulleted' },
  { id: 'annotations', label: 'Ghi chú', icon: 'edit_note' },
  { id: 'search', label: 'Tìm kiếm', icon: 'search' },
  { id: 'details', label: 'Thông tin', icon: 'info' }
]

const activeTabInfo = computed(() => tabs.find(t => t.id === activeTab.value))
const purchaseVersionNumber = computed(() => {
  return ebookVersions.value.find(version => version.id === purchaseVersionId.value)?.version ?? 'đã mua'
})

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

const showAddNoteDialog = ref(false)
const showEditNoteDialog = ref(false)
const submittingNote = ref(false)
const annotationSearchQuery = ref('')

const colorPresets = [
  { name: 'Vàng trí tuệ', value: '#eab308' },
  { name: 'Xanh lá thư giãn', value: '#10b981' },
  { name: 'Hồng điểm nhấn', value: '#f43f5e' },
  { name: 'Tím tư duy', value: '#8b5cf6' },
  { name: 'Xanh dương sâu lắng', value: '#3b82f6' }
]

const noteForm = ref({
  page_number: 1,
  highlighted_text: '',
  note_content: '',
  color: '#eab308'
})

const editNoteForm = ref({
  id: null,
  highlighted_text: '',
  note_content: '',
  color: '#eab308'
})

const filteredAnnotations = computed(() => {
  let list = annotations.value
  if (annotationFilter.value === 'highlight') {
    list = list.filter(a => a.highlighted_text && !a.note_content && a.type !== 'bookmark')
  } else if (annotationFilter.value === 'note') {
    list = list.filter(a => a.note_content && a.type !== 'bookmark')
  } else if (annotationFilter.value === 'bookmark') {
    list = list.filter(a => a.type === 'bookmark')
  }
  
  if (annotationSearchQuery.value.trim()) {
    const q = annotationSearchQuery.value.toLowerCase().trim()
    list = list.filter(a =>
      (a.note_content && a.note_content.toLowerCase().includes(q)) ||
      (a.highlighted_text && a.highlighted_text.toLowerCase().includes(q))
    )
  }
  return list
})

// --- In-Book Search & Bookmark State & Methods ---
const searchKeyword = ref('')
const searchResults = ref([])
const isSearchingBook = ref(false)
const searchProgress = ref(0)

const currentBookmark = computed(() => {
  return annotations.value.find(a => Number(a.page_number || a.page) === currentPage.value && a.type === 'bookmark')
})

const isCurrentPageBookmarked = computed(() => {
  return !!currentBookmark.value
})

const toggleBookmarkCurrentPage = async () => {
  if (isCurrentPageBookmarked.value) {
    const bm = currentBookmark.value
    if (bm) {
      await deleteAnnotation(bm.id)
    }
  } else {
    const bookId = route.params.bookId
    try {
      const payload = {
        book_id: Number(bookId),
        page_number: currentPage.value,
        page: currentPage.value,
        type: 'bookmark',
        note_content: `Đã đánh dấu trang ${currentPage.value}`,
        color: '#3b82f6'
      }
      const response = await apiClient.post('/api/annotations', payload)
      const newAnnot = readApiData(response.data) || response.data.data || response.data
      annotations.value.unshift(newAnnot)
      toast.add({ severity: 'success', summary: 'Đã ghim trang', detail: `Đã đánh dấu trang ${currentPage.value}`, life: 2500 })
    } catch (err) {
      console.error('Error toggling bookmark:', err)
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể đánh dấu trang này.', life: 3000 })
    }
  }
}

const searchBookText = async () => {
  const query = searchKeyword.value.trim().toLowerCase()
  const doc = pdfRef.value?.doc || pdfDocument.value
  if (!query || !doc || isSearchingBook.value) {
    if (!doc) {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Tài liệu PDF chưa tải xong. Vui lòng đợi trong giây lát.', life: 3000 })
    }
    return
  }

  isSearchingBook.value = true
  searchResults.value = []
  searchProgress.value = 0

  const total = doc.numPages || totalPages.value || 0
  const results = []

  try {
    for (let pageNum = 1; pageNum <= total; pageNum++) {
      searchProgress.value = Math.round((pageNum / total) * 100)
      const page = await doc.getPage(pageNum)
      const textContent = await page.getTextContent()
      const rawText = (textContent.items || []).map(item => item.str || '').join(' ')
      const lowerText = rawText.toLowerCase()

      let matchIndex = lowerText.indexOf(query)
      let matchCount = 0

      while (matchIndex !== -1 && matchCount < 3) {
        matchCount++
        const start = Math.max(0, matchIndex - 35)
        const end = Math.min(rawText.length, matchIndex + query.length + 35)
        let snippet = rawText.substring(start, end)
        if (start > 0) snippet = '...' + snippet
        if (end < rawText.length) snippet = snippet + '...'

        results.push({
          id: `${pageNum}-${matchIndex}`,
          page: pageNum,
          snippet,
          matchIndex
        })

        matchIndex = lowerText.indexOf(query, matchIndex + query.length)
      }
    }
    searchResults.value = results
    if (results.length === 0) {
      toast.add({ severity: 'info', summary: 'Tìm kiếm', detail: 'Không tìm thấy kết quả phù hợp trong sách.', life: 3000 })
    }
  } catch (err) {
    console.error('[Reader] In-book search error details:', err)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: `Không thể trích xuất văn bản sách để tìm kiếm: ${err.message || 'Lỗi không xác định'}`, life: 3000 })
  } finally {
    isSearchingBook.value = false
  }
}

const clearBookSearch = () => {
  searchKeyword.value = ''
  searchResults.value = []
  searchProgress.value = 0
}

const jumpToSearchResult = (result) => {
  if (result.page >= 1 && result.page <= totalPages.value) {
    triggerFlip(result.page > currentPage.value ? 'next' : 'prev')
    loading.value = true
    currentPage.value = result.page
    inputPage.value = result.page
    isDrawerVisible.value = false
    activeTab.value = 'reader'
    scrollContainer.value?.scrollTo({ top: 0, behavior: 'smooth' })
    toast.add({ severity: 'info', summary: 'Chuyển trang', detail: `Đã chuyển đến trang ${result.page}`, life: 2500 })
  }
}

// --- Quote Card Generator State & Methods ---
const quoteCardThemes = [
  { id: 'dark', name: 'Đêm huyền bí', bg: '#0f172a', text: '#f8fafc', accent: '#38bdf8', border: '#334155' },
  { id: 'classic', name: 'Cổ điển', bg: '#fef3c7', text: '#451a03', accent: '#d97706', border: '#fde68a' },
  { id: 'sunset', name: 'Hoàng hôn', bg: 'linear-gradient(135deg, #4c1d95, #831843)', text: '#ffffff', accent: '#f472b6', border: '#701a75' },
  { id: 'emerald', name: 'Ngọc bích', bg: '#064e3b', text: '#ecfdf5', accent: '#34d399', border: '#047857' }
]

const selectedQuoteTheme = ref('dark')
const showQuoteCardModal = ref(false)
const selectedQuoteForCard = ref({ text: '', page: 1 })

const currentQuoteThemeObj = computed(() => {
  return quoteCardThemes.find(t => t.id === selectedQuoteTheme.value) || quoteCardThemes[0]
})

const openQuoteCardModal = (note) => {
  selectedQuoteForCard.value = {
    text: note.highlighted_text || '',
    page: note.page_number || note.page || 1
  }
  showQuoteCardModal.value = true
}

const downloadQuoteCardImage = () => {
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  const width = 1200
  const height = 1500
  canvas.width = width
  canvas.height = height

  const theme = currentQuoteThemeObj.value

  // Background
  ctx.fillStyle = theme.id === 'sunset' ? '#4c1d95' : theme.bg
  ctx.fillRect(0, 0, width, height)

  // Decorative Quotes
  ctx.fillStyle = theme.accent
  ctx.globalAlpha = 0.15
  ctx.font = 'bold 180px Georgia, serif'
  ctx.fillText('“', 80, 220)
  ctx.globalAlpha = 1.0

  // Header: Book Title & Page
  ctx.fillStyle = theme.text
  ctx.globalAlpha = 0.7
  ctx.font = 'bold 28px sans-serif'
  const titleText = (book.value?.title || 'KOMIBOOK EBOOK').toUpperCase()
  ctx.fillText(titleText, 80, 100)
  const pageText = `TRANG ${selectedQuoteForCard.value.page}`
  ctx.fillText(pageText, width - 80 - ctx.measureText(pageText).width, 100)

  // Divider Line
  ctx.strokeStyle = theme.accent
  ctx.lineWidth = 4
  ctx.beginPath()
  ctx.moveTo(80, 140)
  ctx.lineTo(width - 80, 140)
  ctx.stroke()

  // Quote Text Word Wrap
  ctx.fillStyle = theme.text
  ctx.globalAlpha = 0.95
  ctx.font = 'italic 46px Georgia, serif'
  const words = selectedQuoteForCard.value.text.split(' ')
  let line = ''
  let y = 380
  const maxWidth = width - 160
  const lineHeight = 72

  for (let n = 0; n < words.length; n++) {
    const testLine = line + words[n] + ' '
    const metrics = ctx.measureText(testLine)
    if (metrics.width > maxWidth && n > 0) {
      ctx.fillText(`"${line.trim()}"`, 80, y)
      line = words[n] + ' '
      y += lineHeight
    } else {
      line = testLine
    }
  }
  ctx.fillText(`"${line.trim()}"`, 80, y)

  // Accent Bar
  y += 50
  ctx.fillStyle = theme.accent
  ctx.fillRect(80, y, 120, 6)

  // Author
  y += 60
  ctx.fillStyle = theme.text
  ctx.globalAlpha = 0.85
  ctx.font = 'bold 36px sans-serif'
  ctx.fillText(`— ${book.value?.author || 'Tác giả'}`, 80, y)

  // Footer
  ctx.globalAlpha = 0.5
  ctx.font = 'bold 24px sans-serif'
  ctx.fillText('Komibook Reader • komibook.vn', 80, height - 80)

  // Trigger Download
  const link = document.createElement('a')
  const safeTitle = (book.value?.title || 'Ebook').replace(/\s+/g, '_')
  link.download = `TheTrichDan_${safeTitle}_Trang${selectedQuoteForCard.value.page}.png`
  link.href = canvas.toDataURL('image/png')
  link.click()

  toast.add({ severity: 'success', summary: 'Tải ảnh thẻ', detail: 'Đã tải xuống Thẻ Trích Dẫn PNG nghệ thuật!', life: 3000 })
}

// --- Reading Session Analytics State & Logic ---
const sessionSeconds = ref(0)
const sessionPagesRead = ref(0)
const visitedPages = ref(new Set())
let sessionInterval = null

const startReadingSessionTimer = () => {
  if (sessionInterval) clearInterval(sessionInterval)
  sessionInterval = setInterval(() => {
    if (!document.hidden) {
      sessionSeconds.value++
    }
  }, 1000)
}

watch(currentPage, (newPage) => {
  if (!visitedPages.value.has(newPage)) {
    visitedPages.value.add(newPage)
    sessionPagesRead.value = visitedPages.value.size
  }
}, { immediate: true })

const formattedSessionTime = computed(() => {
  const m = Math.floor(sessionSeconds.value / 60)
  const s = sessionSeconds.value % 60
  return `${m}m ${s < 10 ? '0' : ''}${s}s`
})

const readingSpeed = computed(() => {
  if (sessionPagesRead.value <= 0 || sessionSeconds.value < 10) return 'Đang tính...'
  const minPerPage = (sessionSeconds.value / 60 / sessionPagesRead.value).toFixed(1)
  return `${minPerPage} phút/trang`
})

const estRemainingTime = computed(() => {
  const remainingPages = (totalPages.value || 1) - currentPage.value
  if (remainingPages <= 0) return 'Đã xong'
  if (sessionPagesRead.value <= 0 || sessionSeconds.value < 10) return 'Đang tính...'
  const minPerPage = sessionSeconds.value / 60 / sessionPagesRead.value
  const remMin = Math.ceil(remainingPages * minPerPage)
  return `~${remMin} phút`
})

const openAddNoteDialog = () => {
  noteForm.value = {
    page_number: currentPage.value || 1,
    highlighted_text: '',
    note_content: '',
    color: '#eab308'
  }
  showAddNoteDialog.value = true
}

const saveNewAnnotation = async () => {
  if (!noteForm.value.note_content?.trim()) return
  const bookId = route.params.bookId
  submittingNote.value = true
  try {
    const payload = {
      book_id: Number(bookId),
      page_number: Number(noteForm.value.page_number) || currentPage.value,
      page: Number(noteForm.value.page_number) || currentPage.value,
      highlighted_text: noteForm.value.highlighted_text,
      note_content: noteForm.value.note_content,
      color: noteForm.value.color,
      type: noteForm.value.note_content ? 'note' : 'highlight'
    }
    const response = await apiClient.post('/api/annotations', payload)
    const newAnnot = readApiData(response.data) || response.data.data || response.data
    annotations.value.unshift(newAnnot)
    showAddNoteDialog.value = false
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã lưu ghi chú cho trang sách.', life: 3000 })
  } catch (err) {
    console.error('Error saving annotation:', err)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: err.response?.data?.message || 'Không thể lưu ghi chú.', life: 3000 })
  } finally {
    submittingNote.value = false
  }
}

const openEditDialog = (note) => {
  editNoteForm.value = {
    id: note.id,
    highlighted_text: note.highlighted_text || '',
    note_content: note.note_content || '',
    color: note.color || '#eab308'
  }
  showEditNoteDialog.value = true
}

const saveEditAnnotation = async () => {
  if (!editNoteForm.value.id || !editNoteForm.value.note_content?.trim()) return
  submittingNote.value = true
  try {
    const payload = {
      note_content: editNoteForm.value.note_content,
      highlighted_text: editNoteForm.value.highlighted_text,
      color: editNoteForm.value.color
    }
    const response = await apiClient.put(`/api/annotations/${editNoteForm.value.id}`, payload)
    const updated = readApiData(response.data) || response.data.data || response.data
    const index = annotations.value.findIndex(a => a.id === editNoteForm.value.id)
    if (index !== -1) {
      annotations.value[index] = {
        ...annotations.value[index],
        ...updated,
        note_content: editNoteForm.value.note_content,
        color: editNoteForm.value.color,
        highlighted_text: editNoteForm.value.highlighted_text
      }
    }
    showEditNoteDialog.value = false
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật ghi chú.', life: 3000 })
  } catch (err) {
    console.error('Error updating annotation:', err)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: err.response?.data?.message || 'Không thể cập nhật ghi chú.', life: 3000 })
  } finally {
    submittingNote.value = false
  }
}

const deleteAnnotation = async (noteId) => {
  if (!confirm('Bạn có chắc chắn muốn xóa ghi chú này không?')) return
  try {
    await apiClient.delete(`/api/annotations/${noteId}`)
    annotations.value = annotations.value.filter(a => a.id !== noteId)
    toast.add({ severity: 'info', summary: 'Đã xóa', detail: 'Đã xóa ghi chú khỏi danh sách.', life: 3000 })
  } catch (err) {
    console.error('Error deleting annotation:', err)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: err.response?.data?.message || 'Không thể xóa ghi chú.', life: 3000 })
  }
}

const jumpToNotePage = (note) => {
  const targetPage = Number(note.page_number || note.page) || 1
  if (targetPage >= 1 && targetPage <= (totalPages.value || 9999)) {
    triggerFlip(targetPage > currentPage.value ? 'next' : 'prev')
    loading.value = true
    currentPage.value = targetPage
    inputPage.value = targetPage
    isDrawerVisible.value = false
    activeTab.value = 'reader'
    scrollContainer.value?.scrollTo({ top: 0, behavior: 'smooth' })
    toast.add({ severity: 'info', summary: 'Chuyển trang', detail: `Đã chuyển đến trang ${targetPage}`, life: 2500 })
  }
}

const exportNotesMarkdown = () => {
  if (!annotations.value.length) {
    toast.add({ severity: 'warn', summary: 'Thông báo', detail: 'Chưa có ghi chú nào để xuất file.', life: 3000 })
    return
  }
  const title = book.value?.title || 'Ebook'
  const author = book.value?.author || 'KomiBook'
  const dateStr = new Date().toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
  
  let mdContent = `# Ghi chú tác phẩm: ${title}\n`
  mdContent += `**Tác giả**: ${author} | **Ngày xuất**: ${dateStr}\n`
  mdContent += `**Tổng số ghi chú**: ${annotations.value.length}\n\n`
  mdContent += `---\n\n`

  annotations.value.forEach((note, index) => {
    const pageNum = note.page_number || note.page || 'N/A'
    const noteDate = formatDate(note.created_at)
    mdContent += `### ${index + 1}. Trang ${pageNum} ${noteDate ? `(${noteDate})` : ''}\n`
    if (note.highlighted_text) {
      mdContent += `> "${note.highlighted_text}"\n\n`
    }
    if (note.note_content) {
      mdContent += `**Suy tư / Ghi chú**: ${note.note_content}\n\n`
    }
    mdContent += `---\n\n`
  })

  const blob = new Blob([mdContent], { type: 'text/markdown;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  const safeFilename = title.replace(/[^a-z0-9àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ\s]/gi, '_')
  link.setAttribute('download', `GhiChu_${safeFilename}.md`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
  toast.add({ severity: 'success', summary: 'Đã xuất file', detail: 'Đã tải xuống file Ghi chú Markdown thành công.', life: 3000 })
}

const bookStats = computed(() => [
  { label: 'Ngôn ngữ', value: book.value?.language || 'Tiếng Việt', icon: 'language' },
  { label: 'Định dạng', value: 'E-book (PDF)', icon: 'picture_as_pdf' },
  { label: 'Trang', value: totalPages.value || '...', icon: 'auto_stories' },
  { label: 'Bản quyền', value: book.value?.publisher || 'Komibook', icon: 'verified' }
])

watch(currentTheme, (newTheme) => localStorage.setItem('readerTheme', newTheme))

const syncReadingProgress = async () => {
  const bookId = route.params.bookId
  if (!bookId || totalPages.value < 1) return
  try {
    const response = await apiClient.put(`/api/books/${bookId}/reading-progress`, {
      current_page: currentPage.value,
      total_pages: totalPages.value,
      version: progressVersion.value
    })
    progressVersion.value = response.data.data.version
  } catch (syncError) {
    if (syncError.response?.status === 409) {
      const latest = await apiClient.get(`/api/books/${bookId}/reading-progress`)
      progressVersion.value = latest.data.data?.version ?? null
    } else {
      console.warn('[Reader] progress sync failed:', syncError)
    }
  }
}

watch(currentPage, () => {
  if (progressTimer) clearTimeout(progressTimer)
  progressTimer = setTimeout(syncReadingProgress, 700)
})

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
  const step = pageStep.value
  if (currentPage.value > 1) {
    triggerFlip('prev')
    loading.value = true
    currentPage.value = Math.max(1, currentPage.value - step)
    inputPage.value = currentPage.value
    scrollContainer.value?.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const nextPage = () => {
  const step = pageStep.value
  if (currentPage.value < totalPages.value) {
    triggerFlip('next')
    loading.value = true
    currentPage.value = Math.min(totalPages.value, currentPage.value + step)
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

const normalizeReaderUrl = (rawUrl) => {
  if (!rawUrl) return null

  if (rawUrl.includes('127.0.0.1:8000')) {
    return rawUrl.replace('http://127.0.0.1:8000', window.location.origin)
  }
  if (rawUrl.includes('localhost:8000')) {
    return rawUrl.replace('http://localhost:8000', window.location.origin)
  }
  if (rawUrl.includes('api.komibook.id.vn')) {
    return rawUrl.replace('https://api.komibook.id.vn', window.location.origin)
  }

  try {
    const urlObj = new URL(rawUrl, window.location.origin)
    return urlObj.origin === window.location.origin ? urlObj.href : urlObj.pathname + urlObj.search
  } catch {
    return rawUrl
  }
}

const applyReaderAccess = (accessData) => {
  purchaseVersionId.value = accessData.purchase_version_id || null
  const authorizedVersions = Array.isArray(accessData.available_versions)
    ? accessData.available_versions
    : []
  const purchaseVersion = authorizedVersions.find(version =>
    Number(version.id) === Number(purchaseVersionId.value) || version.is_purchase_version
  )
  ebookVersions.value = purchaseVersion
    ? authorizedVersions.filter(version => Number(version.version) >= Number(purchaseVersion.version))
    : authorizedVersions
  selectedEbookVersionId.value = ebookVersions.value.some(version => Number(version.id) === Number(accessData.version_id))
    ? accessData.version_id
    : (ebookVersions.value[0]?.id || null)
  pdfUrl.value = normalizeReaderUrl(accessData.url)

  try {
    const urlObj = new URL(pdfUrl.value, window.location.origin)
    watermarkEmail.value = urlObj.searchParams.get('email') || ''
    watermarkName.value = urlObj.searchParams.get('name') || ''
  } catch (parseError) {
    console.warn('Could not parse query parameters for watermark', parseError)
  }
}

const switchEbookVersion = async () => {
  const { orderId, bookId } = route.params
  if (!selectedEbookVersionId.value || switchingVersion.value) return
  if (!ebookVersions.value.some(version => Number(version.id) === Number(selectedEbookVersionId.value))) {
    error.value = 'Phiên bản này không thuộc quyền đọc của bạn.'
    return
  }

  switchingVersion.value = true
  loading.value = true
  error.value = null
  currentPage.value = 1
  inputPage.value = 1
  totalPages.value = 0

  try {
    const response = await apiClient.get(`/api/orders/${orderId}/ebooks/${bookId}/generate-link`, {
      params: { version_id: selectedEbookVersionId.value }
    })
    applyReaderAccess(readApiData(response.data))
  } catch (switchError) {
    error.value = switchError.response?.data?.message || 'Không thể chuyển phiên bản ebook.'
    loading.value = false
  } finally {
    switchingVersion.value = false
  }
}

const fetchEbookData = async () => {
  const { orderId, bookId } = route.params
  console.log('[Reader] Fetching data for:', { orderId, bookId })
  
  loading.value = true
  error.value = null
  
  try {
    const [urlRes, bookRes, annotRes, progressRes] = await Promise.all([
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
      }),
      apiClient.get(`/api/books/${bookId}/reading-progress`).catch(() => ({ data: { data: null } }))
    ])

    applyReaderAccess(readApiData(urlRes.data))
    console.log('[Reader] PDF URL received:', pdfUrl.value)
    
    book.value = bookRes.data.data || bookRes.data
    annotations.value = annotRes.data.data || []
    progressVersion.value = progressRes.data.data?.version ?? null
    savedPage.value = progressRes.data.data?.current_page ?? null
    
    console.log('[Reader] PDF URL adjusted to:', pdfUrl.value)
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
  const doc = pdfRef.value?.doc || pdfApp
  if (doc) {
    pdfDocument.value = markRaw(doc)
    totalPages.value = doc.numPages
    if (savedPage.value && savedPage.value <= totalPages.value) {
      currentPage.value = savedPage.value
      inputPage.value = savedPage.value
      savedPage.value = null
    }
    try {
      const toc = await doc.getOutline()
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
  if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
    e.preventDefault()
    showPrintConsent.value = true
    return
  }
  if (activeTab.value !== 'reader') return
  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') nextPage()
  if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') prevPage()
}

const confirmPrint = () => {
  showPrintConsent.value = false
  setTimeout(() => {
    window.print()
  }, 200)
}

onMounted(() => {
  document.body.classList.add('overflow-hidden')
  fetchEbookData()
  startReadingSessionTimer()
  window.addEventListener('keydown', handleKeydown)

  const img = new Image()
  img.src = new URL('@/assets/logo.png', import.meta.url).href
  img.onload = () => { logoExists.value = true }
})

onUnmounted(() => {
  if (progressTimer) clearTimeout(progressTimer)
  if (sessionInterval) clearInterval(sessionInterval)
  syncReadingProgress()
  document.body.classList.remove('overflow-hidden')
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.slide-panel-enter-active,
.slide-panel-leave-active {
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-panel-enter-from,
.slide-panel-leave-to {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}

.pdf-wrapper {
  user-select: none;
  -webkit-user-select: none;
}


.font-literata {
  font-family: Georgia, 'Times New Roman', serif;
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

button,
select {
  min-height: 44px;
}

@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-slide-up,
  .animate-progress-loading,
  .animate-page-flip-next,
  .animate-page-flip-prev {
    animation: none !important;
  }
}
</style>
