<template>
  <div class="min-h-screen bg-background font-inter antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-40">
       <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-primary/10 blur-[100px] rounded-full"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-secondary/10 blur-[80px] rounded-full"></div>
    </div>

    <div class="w-full px-gutter max-w-[1280px] mx-auto py-6 relative z-10">

      <!-- Compact Breadcrumb -->
      <nav class="mb-4 flex items-center gap-1 overflow-x-auto text-xs font-bold text-outline animate-fade-in" aria-label="Đường dẫn">
        <router-link to="/" class="flex min-h-11 min-w-11 items-center justify-center transition-colors hover:text-primary" aria-label="Trang chủ">
          <span class="material-symbols-outlined text-[18px] group-hover:scale-110 transition-transform">home</span>
        </router-link>
        <span class="material-symbols-outlined text-[12px]">chevron_right</span>
        <router-link to="/catalog" class="inline-flex min-h-11 items-center px-1 transition-colors hover:text-primary">Danh mục</router-link>
        <template v-if="displayCategories.length > 0">
          <span class="material-symbols-outlined text-[12px]">chevron_right</span>
          <router-link            :to="{ name: 'catalog', query: { category_id: displayCategories[0].id } }"            class="inline-flex min-h-11 items-center px-1 text-primary transition-colors hover:underline"
          >
            {{ displayCategories[0].name }}
          </router-link>
        </template>
        <span class="material-symbols-outlined text-[12px]">chevron_right</span>
        <span class="text-on-surface truncate max-w-[180px] opacity-100">{{ book?.title || '...' }}</span>
      </nav>

      <!-- Loading State (MD3 Shimmer) -->
      <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-4 aspect-[3/4] bg-surface-container-low rounded-[32px] animate-pulse"></div>
        <div class="lg:col-span-8 space-y-6">
           <div class="h-12 w-3/4 bg-surface-container-low rounded-2xl animate-pulse"></div>
           <div class="h-6 w-1/4 bg-surface-container-low rounded-full animate-pulse"></div>
           <div class="space-y-3">
              <div class="h-4 w-full bg-surface-container-low rounded animate-pulse"></div>
              <div class="h-4 w-full bg-surface-container-low rounded animate-pulse"></div>
           </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="!book" class="flex flex-col items-center justify-center py-24 bg-surface-container-lowest rounded-[40px] shadow-xl border border-outline-variant/10 text-center animate-fade-in">
        <div class="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mb-6">
           <span class="material-symbols-outlined text-[48px] text-error">sentiment_dissatisfied</span>
        </div>
        <h2 class="text-2xl font-bold text-on-surface mb-3 tracking-tight">Tác phẩm chưa xuất hiện</h2>
        <p class="text-on-surface-variant mb-8 max-w-md mx-auto text-sm font-medium leading-relaxed">Có thể sách đã được ẩn hoặc chuyển đến một không gian khác.</p>
        <router-link to="/" class="bg-primary text-on-primary px-8 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
          Khám phá sách khác
        </router-link>
      </div>

      <!-- ═══ MAIN CONTENT ═══ -->
      <div v-else class="animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          <!-- ─── LEFT COLUMN: COVER & INTERACTIVE GALLERY (STICKY ON DESKTOP) ─── -->
          <div class="lg:col-span-5 lg:sticky lg:top-24 self-start" :class="isZooming ? 'z-40' : 'z-10'">
            <div class="flex flex-col md:flex-row gap-3 items-start">
              <!-- Thumbnails Sidebar (Fixed 5 items height matching 15% larger main image 530px) -->
              <div v-if="allImages.length > 1" class="relative group/thumbs order-2 md:order-1 shrink-0 w-full md:w-20 max-h-[530px] h-full">
                <!-- Floating Small Top Scroll Arrow Button (shown if > 5 images) -->
                <button
                  v-if="allImages.length > 5"
                  @click="scrollThumbnails(-1)"
                  class="hidden md:flex absolute -top-2 left-1/2 -translate-x-1/2 z-30 w-7 h-7 bg-white/95 backdrop-blur-sm hover:bg-primary hover:text-white text-slate-700 rounded-full border border-slate-300 shadow-md items-center justify-center transition-all cursor-pointer border-none"
                  title="Cuộn lên"
                >
                  <span class="material-symbols-outlined text-sm">keyboard_arrow_up</span>
                </button>

                <!-- Thumbnails Scrollable List (Full 530px height, no native scrollbars) -->
                <div                  ref="thumbnailScrollContainer"
                  class="flex flex-row md:flex-col gap-2 w-full h-full overflow-x-auto md:overflow-y-auto max-h-[530px] scrollbar-none scroll-smooth py-0.5"
                >
                  <div                    v-for="(img, idx) in allImages"                    :key="img"
                    class="w-20 h-[99.6px] rounded-none overflow-hidden border border-slate-300 border-solid cursor-pointer shrink-0 transition-all duration-300 relative group bg-white flex items-center justify-center"
                    :class="activeImageIndex === idx ? '!border-2 !border-primary ring-2 ring-primary/40 scale-[1.02] shadow-sm' : 'hover:border-primary/60 opacity-75 hover:opacity-100'"
                    @mouseover="activeImageIndex = idx"
                    @click="activeImageIndex = idx"
                  >
                    <img :src="getCoverUrl(img)" :alt="`${book.title} - ảnh ${idx + 1}`" class="w-full h-full object-contain mx-auto rounded-none" @error="handleGalleryImageError(img)" />
                  </div>
                </div>

                <!-- Floating Small Bottom Scroll Arrow Button (shown if > 5 images) -->
                <button
                  v-if="allImages.length > 5"
                  @click="scrollThumbnails(1)"
                  class="hidden md:flex absolute -bottom-2 left-1/2 -translate-x-1/2 z-30 w-7 h-7 bg-white/95 backdrop-blur-sm hover:bg-primary hover:text-white text-slate-700 rounded-full border border-slate-300 shadow-md items-center justify-center transition-all cursor-pointer border-none"
                  title="Cuộn xuống"
                >
                  <span class="material-symbols-outlined text-sm">keyboard_arrow_down</span>
                </button>
              </div>

              <!-- Main Cover Image Container (15% Larger: 530px height, 4-side thin border) -->
              <div class="flex-1 w-full order-1 md:order-2 perspective-1000 group/cover relative">
                <div                  class="relative transform-gpu transition-all duration-500 ease-out preserve-3d group-hover/cover:scale-[1.01]"
                >
                  <!-- Cover Box (15% larger max-h-[530px] & 4-side thin solid border) -->
                  <div                    class="aspect-[3/4.2] max-h-[530px] max-w-full bg-white rounded-none overflow-hidden shadow-md border border-slate-300 border-solid relative z-20 cursor-crosshair mx-auto flex items-center justify-center"
                    @mousemove="onMouseMove"
                    @mouseleave="onMouseLeave"
                    @dblclick="openLightbox"
                  >
                    <img v-if="activeImageUrl" :src="activeImageUrl" :alt="book.title" class="w-full h-full object-contain mx-auto select-none rounded-none pointer-events-none" @error="handleGalleryImageError(activeImagePath)" />
                    <div v-else class="flex h-full w-full flex-col items-center justify-center gap-3 bg-surface-container-low px-6 text-center text-on-surface-variant" role="status">
                      <span class="material-symbols-outlined text-5xl text-outline" aria-hidden="true">image_not_supported</span>
                      <p class="text-sm font-semibold">Ảnh đang được cập nhật</p>
                    </div>
                    <!-- Lens Overlay when Hovering -->
                    <div                      v-if="isZooming"                      class="absolute border-2 border-primary bg-primary/20 pointer-events-none z-30 rounded-none shadow-md backdrop-blur-[1px]"
                      :style="{                        width: lensWidth + 'px',                        height: lensHeight + 'px',
                        left: lensX + 'px',                        top: lensY + 'px'                      }"
                    >
                      <div class="absolute bottom-1 right-1 bg-black/70 text-white text-[8px] px-1 py-0.5 font-bold uppercase rounded-none">Kính lúp</div>
                    </div>

                    <!-- Double Click Hint Badge -->
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 bg-black/70 backdrop-blur-md text-white text-[9px] font-bold px-3 py-1 rounded-none z-30 opacity-0 group-hover/cover:opacity-100 transition-opacity pointer-events-none tracking-wider uppercase shadow-md">
                      Nhấp đúp xem full màn hình
                    </div>

                    <!-- Sale Overlay Badge -->
                    <div v-if="book.sale_price && book.price > book.sale_price" class="absolute top-5 right-5 bg-error text-on-error text-[10px] font-bold px-3 py-1.5 rounded-none shadow-lg z-30 transform rotate-6 scale-105">
                      -{{ Math.round((1 - book.sale_price / book.price) * 100) }}%
                    </div>
                  </div>
                </div>

                <!-- FLOATING MAGNIFIER ZOOM PANEL (ONLY ON DESKTOP XL SCREEN) -->
                <div                  v-if="isZooming"                  class="hidden xl:block absolute left-[103%] top-0 bg-surface-container-lowest rounded-2xl overflow-hidden shadow-[0_30px_60px_rgba(0,0,0,0.25)] border-2 border-primary z-50 pointer-events-none animate-fade-in"
                  :style="{
                    width: panelWidth + 'px',
                    height: panelHeight + 'px',
                    backgroundImage: `url(${activeImageUrl})`,
                    backgroundPosition: `${zoomBgX}px ${zoomBgY}px`,
                    backgroundSize: `${zoomBgWidth}px ${zoomBgHeight}px`,
                    backgroundRepeat: 'no-repeat'
                  }"
                >
                  <div class="absolute bottom-3 right-3 bg-primary text-on-primary text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-none shadow">
                    Chế độ kính lúp
                  </div>
                </div>
              </div>

            </div>

            <!-- Quick Stats Cards -->
            <div class="grid grid-cols-3 gap-3 mt-4">
              <div v-for="stat in quickStats" :key="stat.label" class="bg-surface-container-lowest rounded-2xl p-3 text-center border border-outline-variant/10 shadow-sm hover:scale-105 transition-all">
                <span class="material-symbols-outlined text-primary text-xl mb-1">{{ stat.icon }}</span>
                <p class="text-sm font-bold text-on-surface leading-none mb-1">{{ stat.value }}</p>
                <p class="text-xs font-bold uppercase tracking-wider text-outline">{{ stat.label }}</p>
              </div>
            </div>
          </div>

          <!-- ─── RIGHT COLUMN: DETAILS & SPECIFICATIONS (7 cols) ─── -->
          <div class="lg:col-span-7 space-y-6 relative z-20">
            <div class="bg-surface-container-lowest rounded-[32px] p-6 lg:p-8 border border-outline-variant/10 shadow-sm relative">
              <!-- Header Info -->
              <div class="border-b border-outline-variant/10 pb-6 mb-6">
                <div class="flex items-center gap-2 mb-3">
                  <router-link                    v-for="cat in displayCategories"                    :key="cat.id"                    :to="{ name: 'catalog', query: { category_id: cat.id } }"
                    class="inline-flex min-h-11 items-center rounded-full bg-primary/10 px-3 text-xs font-bold uppercase tracking-wider text-primary no-underline transition-colors hover:bg-primary hover:text-white"
                  >
                    {{ cat.name }}
                  </router-link>
                  <span v-if="book.type === 'ebook'" class="flex min-h-11 items-center gap-1 rounded-full bg-secondary/10 px-3 text-xs font-bold uppercase tracking-wider text-secondary">
                    <span class="material-symbols-outlined text-[14px]">auto_stories</span> E-book Digital
                  </span>
                  <span v-if="book.type === 'ebook'" class="flex min-h-11 items-center rounded-full bg-primary/10 px-3 text-xs font-bold uppercase tracking-wider text-primary">
                    Phiên bản {{ book.latest_ebook_version?.version || 1 }}
                  </span>
                </div>

                <h1 class="text-2xl lg:text-3xl font-extrabold text-on-surface tracking-tight leading-tight mb-3">
                  {{ book.display_title || book.title }}
                </h1>

                <!-- Điểm đánh giá và thông tin người viết của ấn phẩm -->
                <div class="flex flex-wrap items-center gap-4 text-xs">
                   <div class="flex items-center gap-1.5 bg-surface-container-low px-3 py-1.5 rounded-full border border-outline-variant/10">
                      <div class="flex">
                        <span v-for="i in 5" :key="i" class="material-symbols-outlined text-[16px]" :style="{ 'font-variation-settings': i <= averageRating ? `'FILL' 1` : `'FILL' 0`, color: i <= averageRating ? '#ba0035' : '#c3c6ce' }">star</span>
                      </div>
                      <span class="font-bold text-on-surface text-sm ml-1">{{ averageRating }}.0</span>
                      <span class="text-outline text-[11px]">({{ book.reviews?.length || 0 }} đánh giá)</span>
                   </div>

                   <div class="h-4 w-[1px] bg-outline-variant/30"></div>

                   <div>
                      <p class="text-xs font-bold uppercase tracking-widest text-outline">Tác giả</p>
                      <p class="text-base font-bold text-on-surface tracking-tight">{{ book.author }}</p>
                   </div>
                </div>
              </div>

              <!-- Price & CTA Bar -->
              <div class="bg-surface-container-low/50 rounded-2xl p-6 border border-outline-variant/10 mb-6">
                 <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
                    <div class="text-center sm:text-left shrink-0">
                       <p class="mb-1 text-xs font-bold uppercase tracking-widest text-primary">Giá bán niêm yết</p>
                       <div class="flex items-center gap-3">
                          <span class="text-3xl font-bold text-red-600 dark:text-red-400 tracking-tight">{{ formatCurrency(book.sale_price || book.price) }}</span>
                          <span v-if="book.sale_price && book.price > book.sale_price" class="text-lg text-outline/50 line-through font-bold">{{ formatCurrency(book.price) }}</span>
                       </div>
                    </div>

                    <div class="flex items-center gap-2.5 sm:gap-3 w-full sm:w-auto justify-center sm:justify-end flex-nowrap overflow-x-auto py-1">
                       <template v-if="book.type === 'ebook' && ownershipData.owned">
                          <button @click="goToReader" class="h-12 bg-on-surface text-surface px-6 rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer border-none">
                            <span class="material-symbols-outlined text-[20px]">auto_stories</span>
                            Đọc Sách Ngay
                          </button>
                       </template>
                       <template v-else-if="book.type !== 'ebook' && (Number(book.stock) <= 0 || (book.status && book.status !== 'published'))">
                          <div class="h-12 px-5 rounded-xl bg-surface-container-high text-outline font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 whitespace-nowrap cursor-not-allowed border border-outline-variant/30">
                            <span class="material-symbols-outlined text-[20px]">remove_shopping_cart</span>
                            Sách đã hết hàng (Tạm ngừng bán)
                          </div>
                          <button @click="toggleWishlist" class="h-12 w-12 shrink-0 rounded-xl border border-outline-variant/30 flex items-center justify-center hover:bg-surface-container-high transition-all shadow-sm cursor-pointer" :aria-label="wishlistStore.isFavorite(book?.id) ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích'">
                            <span class="material-symbols-outlined text-[22px]" :class="wishlistStore.isFavorite(book?.id) ? 'text-error fill-1' : 'text-outline'">favorite</span>
                          </button>
                       </template>
                       <template v-else>
                          <button @click="addToCart" class="h-12 px-4 sm:px-5 rounded-xl border-2 border-primary text-primary font-bold text-xs uppercase tracking-wider hover:bg-primary/5 transition-all flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer">
                            <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                            Thêm vào giỏ
                          </button>
                          <button @click="buyNow" class="h-12 bg-primary text-on-primary px-6 sm:px-8 rounded-xl font-bold text-xs uppercase tracking-wider shadow-md shadow-primary/20 hover:scale-105 active:scale-95 transition-all whitespace-nowrap cursor-pointer border-none flex items-center justify-center">
                            Mua ngay
                          </button>
                          <button @click="toggleWishlist" class="h-12 w-12 shrink-0 rounded-xl border border-outline-variant/30 flex items-center justify-center hover:bg-surface-container-high transition-all shadow-sm cursor-pointer" :aria-label="wishlistStore.isFavorite(book?.id) ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích'">
                            <span class="material-symbols-outlined text-[22px]" :class="wishlistStore.isFavorite(book?.id) ? 'text-error fill-1' : 'text-outline'">favorite</span>
                          </button>
                       </template>
                    </div>
                 </div>
              </div>

              <!-- Metadata Specifications Grid -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6 py-4 border-y border-outline-variant/10">
                <div v-for="meta in bookMeta" :key="meta.label" class="space-y-1 p-2 rounded-xl hover:bg-surface-container-low/40 transition-colors">
                   <div class="flex items-center gap-1.5">
                     <p class="text-xs font-bold uppercase tracking-wider text-outline">{{ meta.label }}</p>
                     <InfoTip v-if="meta.note" :text="meta.note" :label="`Giải thích ${meta.label}`" />
                   </div>
                   <p class="text-sm font-bold text-on-surface tracking-tight">{{ meta.value }}</p>
                </div>
              </div>

              <section class="mb-6 rounded-2xl border border-outline-variant/30 bg-surface-container-low p-5" aria-labelledby="commercial-parties-title">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-primary">Nguồn sách đã khai báo</p>
                    <h2 id="commercial-parties-title" class="mt-1 text-lg font-bold text-on-surface">Thông tin xuất bản và cung ứng</h2>
                  </div>
                  <div v-if="book.vendor" class="flex flex-wrap gap-2">
                    <router-link v-if="book.vendor.slug" :to="{ name: 'vendor-storefront', params: { slug: book.vendor.slug } }" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-primary px-4 text-sm font-bold text-primary no-underline transition-colors hover:bg-primary hover:text-on-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                      <span class="material-symbols-outlined text-lg" aria-hidden="true">storefront</span>
                      Xem gian hàng {{ book.vendor.name }}
                    </router-link>
                    <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 text-sm font-bold text-on-surface" :disabled="followLoading || (authStore.isAuthenticated && !followAvailable)" @click="toggleVendorFollow">
                      <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ followingVendor ? 'notifications_active' : 'add_alert' }}</span>
                      {{ followingVendor ? 'Đang theo dõi' : 'Theo dõi gian hàng' }}
                    </button>
                  </div>
                </div>
                <div v-if="book.commercial_parties && Object.keys(book.commercial_parties).length" class="mt-5 grid gap-3 md:grid-cols-3">
                  <div v-for="(party, role) in book.commercial_parties" :key="role" class="min-h-24 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4 transition-shadow duration-200 hover:shadow-md">
                    <div class="flex items-start gap-1.5">
                      <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ role === 'publisher' ? 'Nhà xuất bản' : role === 'supplier' ? 'Nhà cung cấp' : 'Đơn vị chịu trách nhiệm được khai báo' }}</p>
                      <InfoTip v-if="party.is_demo" text="Dữ liệu mô phỏng phục vụ trình diễn, không phải xác minh quan hệ pháp lý." :label="`Giải thích dữ liệu mô phỏng của ${party.display_name}`" />
                    </div>
                    <router-link :to="{ name: 'organization-public', params: { slug: party.slug } }" class="mt-2 block font-bold text-on-surface no-underline hover:text-primary focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-fixed-dim">{{ party.display_name }}</router-link>
                    <span v-if="!party.is_demo" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-primary"><span class="material-symbols-outlined text-sm" aria-hidden="true">verified</span>Đã xác minh</span>
                  </div>
                </div>
                <p v-else-if="!book.commercial_parties || !Object.keys(book.commercial_parties).length" class="mt-4 rounded-xl bg-surface-container p-4 text-sm text-on-surface-variant">Sách chưa được gắn Nhà xuất bản, Nhà cung cấp và đơn vị chịu trách nhiệm.</p>
              </section>

              <!-- Compact Description -->
              <div class="w-full max-w-full overflow-hidden">
                <h3 class="text-lg font-bold text-on-surface mb-3 flex items-center gap-2">
                  <span class="w-1 h-5 bg-primary rounded-full"></span>
                  Giới thiệu tác phẩm
                </h3>
                <div                  class="font-inter text-sm text-on-surface-variant leading-relaxed opacity-90 max-w-full overflow-hidden book-description"                  v-html="formatDescription(book.description)"
                ></div>
              </div>

              <!-- Dynamic Tags -->
              <div v-if="bookTags && bookTags.length > 0" class="mt-6 pt-4 border-t border-outline-variant/10">
                <div class="flex flex-wrap gap-2 items-center">
                  <span class="text-xs font-bold text-on-surface-variant mr-1">Tags:</span>
                  <button                    v-for="tag in bookTags"                    :key="tag"                    type="button"                    @click="onTagClick(tag)"
                    class="flex min-h-11 items-center gap-1 rounded-full border border-outline-variant/20 bg-surface-container-low px-3 text-xs font-semibold text-on-surface-variant shadow-xs transition-colors hover:border-secondary hover:bg-secondary hover:text-on-secondary"
                    title="Bấm để lọc các sách liên quan"
                  >
                    #{{ tag }}
                  </button>
                </div>
              </div>

              <!-- Ebook Chapters Preview Section -->
              <div v-if="book.type === 'ebook'" class="mt-6 bg-surface-container-low/30 rounded-2xl p-5 border border-outline-variant/10">
                <h3 class="text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                  <span class="w-1 h-4 bg-primary rounded-full"></span>
                  Mục lục & Đọc thử
                </h3>

                <div v-if="book.chapters && book.chapters.length > 0" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                  <div                    v-for="chapter in book.chapters.sort((a,b) => a.order - b.order)"                    :key="chapter.id"
                    class="flex items-center justify-between p-3 rounded-xl border border-outline-variant/20 hover:border-primary/20 transition-all bg-surface-container-lowest"
                  >
                    <div class="flex items-center gap-3">
                      <span class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs">
                        {{ chapter.order }}
                      </span>
                      <span class="text-xs font-bold text-on-surface">{{ chapter.title }}</span>
                    </div>

                    <div>
                      <button                        v-if="chapter.is_free"                        @click="openPreviewChapter(chapter)"
                        class="min-h-11 rounded-lg border border-emerald-100 bg-emerald-50 px-3 text-xs font-black uppercase tracking-wider text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white"
                      >
                        Đọc thử
                      </button>
                      <div v-else class="flex items-center gap-1 text-[10px] text-outline opacity-60 font-medium">
                        <span class="material-symbols-outlined text-[14px]">lock</span>
                        Khóa
                      </div>
                    </div>
                  </div>
                </div>

                <div v-else class="text-center py-6 bg-surface-container-lowest rounded-xl border border-dashed border-outline-variant/20">
                  <p class="text-xs text-on-surface-variant font-medium opacity-60">Cuốn e-book này chưa mở chương đọc thử.</p>
                </div>
              </div>
            </div>

            <!-- Review Section -->
            <section class="bg-surface-container-lowest rounded-[32px] p-6 lg:p-8 border border-outline-variant/10 shadow-sm relative">
              <header class="flex items-center justify-between gap-4 mb-6">
                <div>
                  <h3 class="text-lg font-bold text-on-surface tracking-tight mb-1 flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary text-2xl">forum</span>
                    Cảm nhận độc giả
                  </h3>
                  <p class="text-xs text-on-surface-variant font-medium opacity-60">Đánh giá từ các độc giả đã trải nghiệm tác phẩm.</p>
                </div>
                <button @click="showReviewModal = true" class="min-h-11 rounded-xl border-none bg-primary px-5 text-xs font-bold uppercase tracking-wider text-on-primary shadow-sm transition-colors hover:bg-primary/90">
                  Viết đánh giá
                </button>
              </header>

              <!-- Review List -->
              <div v-if="book.reviews?.length > 0" class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                <article v-for="review in book.reviews" :key="review.id" class="p-4 rounded-2xl bg-surface-container-low/30 border border-outline-variant/10 flex gap-4">
                  <div class="shrink-0">
                     <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">
                        {{ review.user?.name?.charAt(0) || 'U' }}
                     </div>
                  </div>
                  <div class="flex-1 min-w-0">
                     <div class="flex items-center justify-between mb-1 gap-3">
                        <h4 class="text-xs font-bold text-on-surface truncate">{{ review.user?.name || 'Độc giả KomiBook' }}</h4>
                        <div class="flex items-center gap-2 shrink-0">
                          <button v-if="authStore.isAuthenticated && review.user_id !== authStore.user?.id" @click="reportReview(review)" type="button" class="text-[10px] text-error bg-transparent border-none cursor-pointer hover:underline">Báo cáo</button>
                          <span class="text-[10px] text-outline opacity-50">{{ formatDate(review.created_at) }}</span>
                        </div>
                     </div>
                     <div class="flex items-center gap-1 mb-2">
                        <span v-for="i in 5" :key="i" class="material-symbols-outlined text-[14px]" :style="{ 'font-variation-settings': i <= review.rating ? `'FILL' 1` : `'FILL' 0`, color: i <= review.rating ? '#ba0035' : '#c3c6ce' }">star</span>
                     </div>

                     <p class="text-xs text-on-surface-variant leading-relaxed">{{ review.comment }}</p>
                  </div>
                </article>
              </div>

              <!-- Empty Review State -->
              <div v-else class="text-center py-8 bg-surface-container-low/20 rounded-2xl border border-dashed border-outline-variant/20">
                 <span class="material-symbols-outlined text-3xl text-outline/30 mb-2">rate_review</span>
                 <p class="text-xs text-on-surface-variant font-medium opacity-60">Chưa có đánh giá nào. Hãy là người đầu tiên chia sẻ cảm nhận!</p>
              </div>
            </section>

            <SeriesOrbitCarousel
              v-if="seriesBooks.length"
              :books="seriesBooks"
              :current-book-id="book.id"
              :series-title="book.series?.title"
            />

          </div>

        </div>

        <!-- SÁCH LIÊN QUAN CÙNG THỂ LOẠI (DƯỚI CÙNG TRANG - TOP 5 XEM NHIỀU NHẤT) -->
        <section v-if="relatedCategoryBooks && relatedCategoryBooks.length > 0" class="mt-12 pt-8 border-t border-outline-variant/20">
          <div class="flex justify-between items-end mb-6">
            <div>
              <h2 class="text-2xl font-bold text-primary tracking-tight uppercase flex items-center gap-2">
                Sách liên quan cùng thể loại
              </h2>
              <p class="text-xs text-on-surface-variant font-medium opacity-60">Top 5 đầu sách có lượt khám phá cao nhất cùng thể loại.</p>
            </div>
            <router-link to="/catalog" class="text-sm font-bold text-secondary hover:underline flex items-center gap-1 no-underline">
              Xem tất cả <span class="material-symbols-outlined text-[16px]">chevron_right</span>
            </router-link>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <div
              v-for="rBook in relatedCategoryBooks.slice(0, 5)"
              :key="rBook.id"
              @click="goToDetail(rBook.slug)"
              class="group bg-surface-container-lowest rounded-b-2xl rounded-t-none overflow-hidden border border-outline-variant/30 shadow-sm hover:shadow-lg hover:-translate-y-1.5 transition-all duration-300 flex flex-col justify-between cursor-pointer"
            >
              <!-- Cover Image: Edge-to-edge top, square corners at top -->
              <div class="relative w-full aspect-[3/4.2] overflow-hidden bg-surface-variant/10">
                <img v-if="rBook.cover_image && !brokenRelatedCoverIds.includes(rBook.id)" :src="getCoverUrl(rBook.cover_image)" :alt="rBook.title" class="h-full w-full rounded-none object-contain p-2" loading="lazy" @error="markRelatedCoverBroken(rBook.id)" />
                <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-3 text-center text-outline"><span class="material-symbols-outlined text-4xl" aria-hidden="true">image_not_supported</span><span class="text-xs font-semibold">Ảnh đang cập nhật</span></div>
                <span v-if="rBook.sale_price && rBook.price > rBook.sale_price" class="absolute top-2 right-2 bg-secondary text-on-secondary text-[10px] font-extrabold px-2 py-0.5 rounded-md shadow-sm z-10">
                  -{{ Math.round((1 - rBook.sale_price / rBook.price) * 100) }}%
                </span>
              </div>

              <!-- Bottom Info -->
              <div class="p-3.5 pt-3">
                <h3 class="text-xs font-bold text-on-surface line-clamp-2 leading-snug mb-1.5 group-hover:text-primary transition-colors min-h-[32px]">{{ rBook.title }}</h3>
                <div class="flex items-baseline gap-1.5">
                  <span class="text-xs font-extrabold text-secondary">{{ formatCurrency(rBook.sale_price || rBook.price) }}</span>
                  <span v-if="rBook.sale_price && rBook.price > rBook.sale_price" class="text-[10px] text-outline line-through font-normal">{{ formatCurrency(rBook.price) }}</span>
                </div>
              </div>
            </div>
          </div>
        </section>

      </div>

    </div>

    <!-- REVIEW MODAL -->
    <Dialog      v-model:visible="showReviewModal"      modal      header="Đánh giá tác phẩm"      class="!max-w-md !w-[90vw] !rounded-[32px] !bg-surface-container-lowest"
    >
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-xs font-bold uppercase text-outline mb-2">Số sao đánh giá</label>
          <div class="flex items-center gap-2">
             <button               v-for="star in 5"               :key="star"               type="button"               @click="reviewForm.rating = star"
               class="flex h-11 w-11 items-center justify-center border-none bg-transparent"
               :aria-label="`${star} sao`"
               :aria-pressed="reviewForm.rating === star"
             >
                <span class="material-symbols-outlined text-2xl" :style="{ 'font-variation-settings': star <= reviewForm.rating ? `'FILL' 1` : `'FILL' 0`, color: star <= reviewForm.rating ? '#ba0035' : '#c3c6ce' }">star</span>
             </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase text-outline mb-2">Nội dung chia sẻ</label>
          <textarea            v-model="reviewForm.comment"            rows="4"            placeholder="Cảm nhận của bạn về nội dung, hình thức và thông điệp của cuốn sách..."
            class="w-full p-3 rounded-xl border border-outline-variant/30 text-xs bg-surface-container-low text-on-surface focus:outline-none focus:border-primary"
          ></textarea>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="showReviewModal = false" class="min-h-11 rounded-lg border-none bg-transparent px-4 text-xs font-bold text-outline hover:bg-surface-container-high">Hủy</button>
          <button @click="submitReview" :disabled="isSubmittingReview" class="min-h-11 rounded-lg border-none bg-primary px-6 text-xs font-bold uppercase tracking-wider text-on-primary shadow">
            {{ isSubmittingReview ? 'Đang gửi...' : 'Gửi đánh giá' }}
          </button>
        </div>
      </template>
    </Dialog>

    <!-- PREVIEW CHAPTER DIALOG -->
    <Dialog      v-model:visible="previewDialogVisible"      modal      :header="activePreviewChapter?.title || 'Đọc thử'"      class="!max-w-2xl !w-[90vw] !rounded-[32px] !bg-surface-container-lowest"
    >
      <div class="p-6 font-literata text-base text-on-surface-variant leading-relaxed overflow-y-auto max-h-[50vh] text-justify whitespace-pre-wrap select-none no-copy">
        {{ activePreviewChapter?.content || 'Không có nội dung hiển thị.' }}
      </div>
      <template #footer>
        <div class="p-3 bg-indigo-50/50 rounded-xl border border-indigo-100 flex items-center justify-between text-xs text-slate-600 w-full mt-2">
          <span>Đọc thử miễn phí KomiBook DRM.</span>
          <button @click="previewDialogVisible = false" class="min-h-11 rounded-lg border-none bg-primary px-4 text-xs font-bold uppercase tracking-widest text-on-primary">Đóng</button>
        </div>
      </template>
    </Dialog>

    <!-- DOUBLE-CLICK LIGHTBOX MODAL -->
    <Teleport to="body">
      <div        v-if="lightboxVisible"        class="fixed inset-0 z-[9999] bg-black/95 backdrop-blur-md flex flex-col justify-between p-6 select-none animate-fade-in"
      >
        <!-- Top Toolbar Bar -->
        <div class="flex items-center justify-between text-white/80 w-full max-w-7xl mx-auto z-10">
          <div class="text-base font-bold tracking-widest text-white/90 font-mono">
            {{ activeImageIndex + 1 }} / {{ allImages.length }}
          </div>

          <div class="flex items-center gap-4 text-white/80">
            <a :href="activeImageUrl" target="_blank" download title="Tải ảnh về" class="hover:text-white transition-colors cursor-pointer text-white/80">
              <span class="material-symbols-outlined text-[24px]">download</span>
            </a>
            <button @click="lightboxVisible = false" title="Đóng (Esc)" class="hover:text-error transition-colors border-none bg-transparent cursor-pointer text-white/80 flex items-center">
              <span class="material-symbols-outlined text-[32px]">close</span>
            </button>
          </div>
        </div>

        <!-- Center Image Display with Left/Right Chevrons -->
        <div class="relative flex-1 flex items-center justify-center w-full max-w-7xl mx-auto my-auto overflow-hidden">
          <button            v-if="allImages.length > 1"            @click="activeImageIndex = (activeImageIndex - 1 + allImages.length) % allImages.length"            class="absolute left-4 z-20 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center backdrop-blur-md transition-all border-none cursor-pointer"
          >
            <span class="material-symbols-outlined text-3xl">chevron_left</span>
          </button>

          <img v-if="activeImageUrl" :src="activeImageUrl" :alt="book?.title" class="max-h-[82vh] max-w-[85vw] object-contain shadow-[0_0_50px_rgba(0,0,0,0.8)] rounded-xl transition-all duration-300" @error="handleGalleryImageError(activeImagePath)" />
          <div v-else class="flex flex-col items-center gap-3 text-white/80"><span class="material-symbols-outlined text-6xl" aria-hidden="true">image_not_supported</span><p>Ảnh đang được cập nhật</p></div>

          <button            v-if="allImages.length > 1"            @click="activeImageIndex = (activeImageIndex + 1) % allImages.length"            class="absolute right-4 z-20 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center backdrop-blur-md transition-all border-none cursor-pointer"
          >
            <span class="material-symbols-outlined text-3xl">chevron_right</span>
          </button>
        </div>

        <div class="text-center text-white/60 text-xs font-medium tracking-wider z-10 py-2">
          Bấm <kbd class="px-2 py-0.5 bg-white/20 rounded text-white font-mono">Esc</kbd> hoặc biểu tượng ✕ để đóng
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import DOMPurify from 'dompurify'
import apiClient from '@/services/axios'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import InfoTip from '@/components/InfoTip.vue'
import SeriesOrbitCarousel from '@/components/SeriesOrbitCarousel.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()

const book = ref(null)
const followingVendor = ref(false)
const followAvailable = ref(true)
const followLoading = ref(false)
const loading = ref(true)
const seriesBooks = ref([])
const relatedCategoryBooks = ref([])
const relatedAuthorBooks = ref([])
const recentAnnotations = ref([])
const ownershipData = ref({ owned: false, order_id: null, book_id: null })

const goToDetail = (slug) => {
  if (!slug) return
  router.push(`/book/${slug}`).then(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  })
}

const showReviewModal = ref(false)
const isSubmittingReview = ref(false)
const reviewForm = ref({ rating: 5, comment: '' })

const previewDialogVisible = ref(false)
const activePreviewChapter = ref(null)

const activeImageIndex = ref(0)
const thumbnailScrollContainer = ref(null)
const failedGalleryImagePaths = ref([])
const brokenRelatedCoverIds = ref([])

const scrollThumbnails = (direction) => {
  if (thumbnailScrollContainer.value) {
    thumbnailScrollContainer.value.scrollBy({ top: direction * 180, behavior: 'smooth' })
  }
}
const isZooming = ref(false)

const lensWidth = ref(120)
const lensHeight = ref(150)
const panelWidth = ref(440)
const panelHeight = ref(550)

const lensX = ref(0)
const lensY = ref(0)
const zoomBgX = ref(0)
const zoomBgY = ref(0)
const zoomBgWidth = ref(0)
const zoomBgHeight = ref(0)
const lightboxVisible = ref(false)

const candidateImages = computed(() => {
  if (!book.value) return []
  return [...new Set([book.value.cover_image, ...(book.value.gallery_images || [])].filter(Boolean))]
})

const allImages = computed(() => {
  return candidateImages.value.filter((path) => !failedGalleryImagePaths.value.includes(path))
})

const activeImagePath = computed(() => {
  return allImages.value[activeImageIndex.value] || null
})

const activeImageUrl = computed(() => {
  return getCoverUrl(activeImagePath.value)
})

const handleGalleryImageError = (path) => {
  if (path && !failedGalleryImagePaths.value.includes(path)) {
    failedGalleryImagePaths.value.push(path)
  }

  if (activeImageIndex.value >= allImages.value.length) {
    activeImageIndex.value = Math.max(0, allImages.value.length - 1)
  }
  if (allImages.value.length === 0) {
    isZooming.value = false
    lightboxVisible.value = false
  }
}

const markRelatedCoverBroken = (bookId) => {
  if (!brokenRelatedCoverIds.value.includes(bookId)) brokenRelatedCoverIds.value.push(bookId)
}

const displayCategories = computed(() => {
  if (book.value?.categories && book.value.categories.length > 0) {
    return book.value.categories
  }
  return book.value?.category ? [book.value.category] : []
})

const onMouseMove = (e) => {
  const rect = e.currentTarget.getBoundingClientRect()
  const W = rect.width
  const H = rect.height

  const mouseX = e.clientX - rect.left
  const mouseY = e.clientY - rect.top

  const lw = lensWidth.value
  const lh = lensHeight.value

  // Căn con trỏ chuột chính xác ở tâm của khung kính lúp
  const lx = Math.max(0, Math.min(W - lw, mouseX - lw / 2))
  const ly = Math.max(0, Math.min(H - lh, mouseY - lh / 2))

  lensX.value = lx
  lensY.value = ly

  // Tỷ lệ phóng to chính xác 100% dựa trên khung soi
  const zoomLevel = panelWidth.value / lw

  zoomBgWidth.value = W * zoomLevel
  zoomBgHeight.value = H * zoomLevel

  zoomBgX.value = - (lx * zoomLevel)
  zoomBgY.value = - (ly * zoomLevel)

  isZooming.value = true
}

const onMouseLeave = () => {
  isZooming.value = false
}

const openLightbox = () => {
  if (activeImageUrl.value) lightboxVisible.value = true
}

const openPreviewChapter = (chapter) => {
  activePreviewChapter.value = chapter
  previewDialogVisible.value = true
}

const quickStats = computed(() => [
  { label: 'Đánh giá', value: `${averageRating.value}.0 (${book.value?.reviews?.length || 0})`, icon: 'star_rate' },
  { label: 'Yêu thích', value: book.value?.wishlists_count || 0, icon: 'favorite' },
  { label: 'Khám phá', value: book.value?.views || 0, icon: 'visibility' }
])

const onTagClick = (tag) => {
  if (!tag) return
  const cleanTag = tag.startsWith('#') ? tag.slice(1).trim() : tag.trim()
  const matchedCat = displayCategories.value.find(
    c => c.name.toLowerCase() === cleanTag.toLowerCase()
  )
  if (matchedCat) {
    router.push({ name: 'catalog', query: { category_id: matchedCat.id } })
  } else {
    router.push({ name: 'catalog', query: { search: cleanTag } })
  }
}

const bookTags = computed(() => {
  if (!book.value) return []
  const tags = []
  if (book.value.author && book.value.author !== 'Đang cập nhật' && book.value.author !== 'Nhiều Tác Giả') {
    tags.push(book.value.author)
  }
  if (book.value.translator && book.value.translator !== 'Đang cập nhật') {
    tags.push(book.value.translator)
  }
  if (book.value.categories && book.value.categories.length > 0) {
    book.value.categories.forEach(cat => tags.push(cat.name))
  } else if (book.value.category?.name) {
    tags.push(book.value.category.name)
  }
  if (book.value.type === 'ebook') {
    tags.push('E-book')
  } else {
    tags.push(book.value.cover_format || 'Bìa mềm')
  }
  if (book.value.vendor?.name) {
    tags.push(book.value.vendor.name)
  }
  if (book.value.sale_price && book.value.price > book.value.sale_price) {
    tags.push('Khuyến mãi')
  }
  if (book.value.release_date) {
    tags.push(`Năm ${book.value.release_date}`)
  }
  return [...new Set(tags)]
})

const bookMeta = computed(() => {
  if (!book.value) return []
  const meta = [
    {
      label: 'Nhà cung cấp',
      value: book.value.commercial_parties?.supplier?.display_name || 'Chưa khai báo chuỗi cung ứng',
      note: book.value.commercial_parties?.supplier?.is_demo
        ? 'Dữ liệu mô phỏng – không xác minh pháp lý'
        : null,
    },
  ]

  if (book.value.translator) {
    meta.push({ label: 'Người dịch', value: book.value.translator })
  }
  if (book.value.type !== 'ebook') {
    meta.push(
      { label: 'Hình thức bìa', value: book.value.cover_format || 'Bìa mềm' },
      { label: 'Kích thước', value: book.value.dimensions || '13x18 cm' },
      { label: 'Trọng lượng', value: book.value.weight ? (book.value.weight.toString().includes('g') ? book.value.weight : `${book.value.weight} gam`) : '350 gam' }
    )
  }

  meta.push(
    { label: 'Số trang', value: book.value.pages ? `${book.value.pages} trang` : 'Đang cập nhật' },
    { label: 'Năm xuất bản', value: book.value.release_date || '2026' },
    { label: 'Ngôn ngữ', value: book.value.language || 'Tiếng Việt' },
    { label: 'Độ tuổi', value: book.value.target_age || 'Mọi lứa tuổi' }
  )

  if (book.value.type !== 'ebook' && book.value.isbn) {
    meta.push({ label: 'Mã ISBN / SKU', value: book.value.isbn })
  }

  return meta
})

const fetchBookDetail = async () => {
  loading.value = true
  try {
    const response = await apiClient.get(`/api/books/${route.params.slug}`)
    const responseData = response.data.data || response.data
    book.value = responseData
    if (responseData.vendor?.id) {
      apiClient.post(`/api/vendors/${responseData.vendor.id}/visit`).catch(() => {})
      if (authStore.isAuthenticated) {
        apiClient.get(`/api/vendors/${responseData.vendor.id}/follow`)
          .then((result) => {
            followingVendor.value = Boolean(result.data?.following)
            followAvailable.value = result.data?.available !== false
          })
          .catch(() => { followingVendor.value = false })
      }
    }

    const promises = [
      fetchSeriesBooks(responseData.id),
      fetchRelatedCategoryBooks(responseData.id),
      fetchAuthorBooks(responseData.id)
    ]
    if (authStore.isAuthenticated && responseData.type === 'ebook') {
      promises.push(checkEbookOwnership(responseData.id))
    }
    await Promise.allSettled(promises)
  } catch (error) {
    console.error('Lỗi tải chi tiết sách:', error)
  } finally {
    loading.value = false
  }
}

const toggleVendorFollow = async () => {
  if (!book.value?.vendor?.id || followLoading.value) return
  if (!authStore.isAuthenticated) {
    await router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  followLoading.value = true
  try {
    const response = await apiClient.post(`/api/vendors/${book.value.vendor.id}/follow`)
    followingVendor.value = Boolean(response.data?.following)
    toast.add({ severity: 'success', summary: followingVendor.value ? 'Đã theo dõi' : 'Đã bỏ theo dõi', detail: response.data?.message, life: 3000 })
  } catch (requestError) {
    toast.add({ severity: 'error', summary: 'Không thể cập nhật', detail: requestError.response?.data?.message || 'Vui lòng thử lại.', life: 3000 })
  } finally {
    followLoading.value = false
  }
}

const fetchSeriesBooks = async (bookId) => {
  try {
    const res = await apiClient.get(`/api/books/${bookId}/series`)
    seriesBooks.value = res.data.data || []
  } catch (error) {
    console.warn('Không thể tải sách cùng bộ:', error)
  }
}

const fetchRelatedCategoryBooks = async (bookId) => {
  try {
    const res = await apiClient.get(`/api/books/${bookId}/related`)
    relatedCategoryBooks.value = res.data.data || []
  } catch (error) {
    console.warn('Không thể tải sách liên quan:', error)
  }
}

const checkEbookOwnership = async (bookId) => {
  try {
    const res = await apiClient.get(`/api/books/${bookId}/check-ownership`)
    ownershipData.value = res.data.data || { owned: false }
  } catch (error) {
    console.warn('Không thể kiểm tra quyền sở hữu ebook:', error)
  }
}

const fetchAuthorBooks = async (bookId) => {
  try {
    const res = await apiClient.get(`/api/books/${bookId}/contributors`)
    relatedAuthorBooks.value = res.data.data || []
  } catch (error) {
    console.warn('Không thể tải sách cùng tác giả:', error)
  }
}

const goToReader = () => {
  if (book.value?.id && ownershipData.value?.order_id) {
    router.push({ name: 'ebook-reader', params: { orderId: ownershipData.value.order_id, bookId: book.value.id } })
  }
}

const averageRating = computed(() => {
  if (!book.value || !book.value.reviews || book.value.reviews.length === 0) return 5
  const sum = book.value.reviews.reduce((acc, curr) => acc + curr.rating, 0)
  return Math.round(sum / book.value.reviews.length)
})

const submitReview = async () => {
  if (!reviewForm.value.comment) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng viết lời nhận xét của bạn.', life: 3000 })
    return
  }
  isSubmittingReview.value = true
  try {
    const response = await apiClient.post(`/api/books/${book.value.id}/reviews`, reviewForm.value)
    toast.add({ severity: 'success', summary: 'Thành công', detail: response.data.message || 'Cảm ơn bạn đã đánh giá!', life: 3000 })
    if (!book.value.reviews) book.value.reviews = []
    const existingIndex = book.value.reviews.findIndex(review => review.user_id === response.data.data.user_id)
    if (existingIndex >= 0) book.value.reviews.splice(existingIndex, 1, response.data.data)
    else book.value.reviews.unshift(response.data.data)
    reviewForm.value = { rating: 5, comment: '' }
    showReviewModal.value = false
  } catch (error) {
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi gửi đánh giá'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    isSubmittingReview.value = false
  }
}

const reportReview = async (review) => {
  if (!window.confirm('Báo cáo đánh giá này là không phù hợp?')) return
  try {
    await apiClient.post(`/api/reviews/${review.id}/reports`, { reason: 'irrelevant' })
    toast.add({ severity: 'success', summary: 'Đã tiếp nhận', detail: 'KomiBook sẽ kiểm tra đánh giá này.', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể báo cáo', detail: error.response?.data?.message || 'Vui lòng thử lại.', life: 3500 })
  }
}

const addToCart = () => {
  if (!book.value) return
  if (book.value.type !== 'ebook' && (Number(book.value.stock) <= 0 || (book.value.status && book.value.status !== 'published'))) {
    toast.add({ severity: 'warn', summary: 'Sách đã hết hàng', detail: `Cuốn sách "${book.value.title}" hiện đã hết hàng.`, life: 3000 })
    return
  }
  const added = cartStore.addToCart({
    id: book.value.id, title: book.value.title, slug: book.value.slug,
    author: book.value.author, cover_image: book.value.cover_image,
    price: book.value.price, sale_price: book.value.sale_price,
    type: book.value.type, stock: book.value.stock, status: book.value.status,
    vendor: book.value.vendor, vendor_id: book.value.vendor?.id
  })
  if (added !== false) {
    toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã thêm "${book.value.title}" vào giỏ hàng!`, life: 3000 })
  }
}

const buyNow = () => {
  if (!book.value) return
  addToCart()
  router.push('/cart')
}

const toggleWishlist = async () => {
  if (!book.value) return
  try {
    const res = await wishlistStore.toggleWishlist(book.value.id)
    if (res.state === 'added') {
      book.value.wishlists_count = (book.value.wishlists_count || 0) + 1
      toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Đã thêm vào danh sách yêu thích', life: 2000 })
    } else if (res.state === 'removed') {
      book.value.wishlists_count = Math.max(0, (book.value.wishlists_count || 1) - 1)
      toast.add({ severity: 'info', summary: 'Đã bỏ', detail: 'Đã xóa khỏi danh sách yêu thích', life: 2000 })
    } else if (res.status === 'unauthorized') {
      toast.add({ severity: 'warn', summary: 'Thông báo', detail: 'Vui lòng đăng nhập để lưu yêu thích', life: 3000 })
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  }
}

const formatDescription = (desc) => {
  if (!desc) return ''
  let clean = desc.replace(/&nbsp;/g, ' ').replace(/\u00a0/g, ' ')
  return DOMPurify.sanitize(clean, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'span', 'sub', 'sup'],
    ALLOWED_ATTR: ['href', 'target', 'rel', 'class']
  })
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(dateString))
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

watch(() => route.params.slug, (newSlug) => {
  if (newSlug) {
    seriesBooks.value = []
    relatedAuthorBooks.value = []
    relatedCategoryBooks.value = []
    ownershipData.value = { owned: false, order_id: null, book_id: null }
    recentAnnotations.value = []
    activeImageIndex.value = 0
    failedGalleryImagePaths.value = []
    brokenRelatedCoverIds.value = []
    fetchBookDetail()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
})

onMounted(() => {
  fetchBookDetail()
  wishlistStore.fetchWishlistIds()
})
</script>

<style scoped>
.perspective-1000 {
  perspective: 1000px;
}

.preserve-3d {
  transform-style: preserve-3d;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slide-up {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up {
  animation: slide-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.fill-1 {
  font-variation-settings: 'FILL' 1;
}

.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

:deep(.book-description) {
  word-break: normal !important;
  overflow-wrap: break-word !important;
  word-wrap: break-word !important;
  hyphens: none !important;
  -webkit-hyphens: none !important;
  -ms-hyphens: none !important;
  text-align: justify;
}

:deep(.book-description p) {
  word-break: normal !important;
  overflow-wrap: break-word !important;
  hyphens: none !important;
  margin-bottom: 0.75rem;
}

:deep(.book-description img),
:deep(.book-description video),
:deep(.book-description iframe) {
  max-width: 100% !important;
  height: auto !important;
}

:deep(.book-description table) {
  display: block !important;
  max-width: 100% !important;
  overflow-x: auto !important;
}

:deep(.book-description pre) {
  max-width: 100% !important;
  overflow-x: auto !important;
  white-space: pre-wrap !important;
}
</style>
