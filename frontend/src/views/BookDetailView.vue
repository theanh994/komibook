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
      <div v-if="fetchState === 'loading'" class="grid grid-cols-1 lg:grid-cols-12 gap-8" aria-busy="true" aria-live="polite">
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

      <!-- Not found State -->
      <div v-else-if="fetchState === 'notFound'" class="flex flex-col items-center justify-center py-24 bg-surface-container-lowest rounded-[40px] shadow-xl border border-outline-variant/10 text-center animate-fade-in">
        <div class="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mb-6">
           <span class="material-symbols-outlined text-[48px] text-error">sentiment_dissatisfied</span>
        </div>
        <h2 class="text-2xl font-bold text-on-surface mb-3 tracking-tight">Tác phẩm chưa xuất hiện</h2>
        <p class="text-on-surface-variant mb-8 max-w-md mx-auto text-sm font-medium leading-relaxed">Có thể sách đã được ẩn hoặc chuyển đến một không gian khác.</p>
        <router-link to="/catalog" class="inline-flex min-h-11 items-center bg-primary text-on-primary px-8 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
          Khám phá sách khác
        </router-link>
      </div>

      <!-- Request error State -->
      <div v-else-if="fetchState === 'error'" class="flex flex-col items-center justify-center py-24 bg-surface-container-lowest rounded-[40px] shadow-xl border border-outline-variant/10 text-center animate-fade-in" role="alert">
        <div class="w-20 h-20 bg-error/10 rounded-full flex items-center justify-center mb-6">
          <span class="material-symbols-outlined text-[48px] text-error" aria-hidden="true">error</span>
        </div>
        <h2 class="text-2xl font-bold text-on-surface mb-3 tracking-tight">Không thể tải thông tin sách</h2>
        <p class="text-on-surface-variant mb-8 max-w-md mx-auto text-sm font-medium leading-relaxed">{{ requestError || 'Vui lòng kiểm tra kết nối và thử lại.' }}</p>
        <button type="button" class="inline-flex min-h-11 items-center bg-primary text-on-primary px-8 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-primary/20 hover:scale-105 active:scale-95 transition-all" @click="fetchBookDetail">
          Thử lại
        </button>
      </div>

      <!-- ═══ MAIN CONTENT ═══ -->
      <div v-else-if="fetchState === 'ready' && book" class="animate-fade-in">
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
                  class="hidden md:flex absolute -top-2 left-1/2 -translate-x-1/2 z-30 min-h-11 min-w-11 bg-white/95 backdrop-blur-sm hover:bg-primary hover:text-white text-slate-700 rounded-full border border-slate-300 shadow-md items-center justify-center transition-all cursor-pointer border-none"
                  title="Cuộn lên"
                  aria-label="Cuộn danh sách ảnh lên"
                >
                  <span class="material-symbols-outlined text-sm">keyboard_arrow_up</span>
                </button>

                <!-- Thumbnails Scrollable List (Full 530px height, no native scrollbars) -->
                <div                  ref="thumbnailScrollContainer"
                  class="flex flex-row md:flex-col gap-2 w-full h-full overflow-x-auto md:overflow-y-auto max-h-[530px] scrollbar-none scroll-smooth py-0.5"
                >
                  <button                    v-for="(img, idx) in allImages"                    :key="img"                    type="button"
                    class="w-20 h-[99.6px] rounded-none overflow-hidden border border-slate-300 border-solid cursor-pointer shrink-0 transition-all duration-300 relative group bg-white flex items-center justify-center"
                    :class="activeImageIndex === idx ? '!border-2 !border-primary ring-2 ring-primary/40 scale-[1.02] shadow-sm' : 'hover:border-primary/60 opacity-75 hover:opacity-100'"
                    @click="activeImageIndex = idx"
                    :aria-label="`Chọn ảnh ${idx + 1} của ${book.title}`"
                    :aria-pressed="activeImageIndex === idx"
                  >
                    <img :src="getCoverUrl(img)" :alt="`${book.title} - ảnh ${idx + 1}`" class="w-full h-full object-contain mx-auto rounded-none" @error="handleGalleryImageError(img)" />
                  </button>
                </div>

                <!-- Floating Small Bottom Scroll Arrow Button (shown if > 5 images) -->
                <button
                  v-if="allImages.length > 5"
                  @click="scrollThumbnails(1)"
                  class="hidden md:flex absolute -bottom-2 left-1/2 -translate-x-1/2 z-30 min-h-11 min-w-11 bg-white/95 backdrop-blur-sm hover:bg-primary hover:text-white text-slate-700 rounded-full border border-slate-300 shadow-md items-center justify-center transition-all cursor-pointer border-none"
                  title="Cuộn xuống"
                  aria-label="Cuộn danh sách ảnh xuống"
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

            <section class="mt-6 rounded-2xl border border-outline-variant/30 bg-surface-container-low p-5" aria-labelledby="commercial-parties-title">
              <div class="flex flex-col gap-3">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-primary">Nguồn sách đã khai báo</p>
                  <h2 id="commercial-parties-title" class="mt-1 text-lg font-bold text-on-surface">Thông tin xuất bản và cung ứng</h2>
                </div>
                <div v-if="book.vendor" class="flex flex-wrap items-center gap-2">
                  <!-- 🏪 Vendor Logo / Avatar Badge -->
                  <div
                    class="relative w-9 h-9 rounded-xl overflow-hidden border border-outline-variant/30 bg-surface-container-high shrink-0 shadow-2xs flex items-center justify-center"
                    :title="`Logo gian hàng ${book.vendor.name}`"
                  >
                    <img
                      v-if="vendorLogoUrl && !vendorAvatarBroken"
                      :src="vendorLogoUrl"
                      :alt="book.vendor?.name || 'Logo Gian hàng'"
                      class="w-full h-full object-cover rounded-xl"
                      @error="vendorAvatarBroken = true"
                    />
                    <span v-else class="text-xs font-extrabold text-on-surface-variant" :aria-label="`Biểu tượng chữ cái của ${book.vendor?.name || 'gian hàng'}`">{{ getInitials(book.vendor?.name || book.vendor?.shop_name) }}</span>
                  </div>

                  <router-link v-if="book.vendor.slug" :to="{ name: 'vendor-storefront', params: { slug: book.vendor.slug } }" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-primary px-3.5 text-xs font-bold text-primary no-underline transition-colors hover:bg-primary hover:text-on-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">storefront</span>
                    Xem gian hàng
                  </router-link>
                  <button type="button" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-xs font-bold text-on-surface hover:bg-surface-container-high transition-colors cursor-pointer" :disabled="followLoading || (authStore.isAuthenticated && !followAvailable)" @click="toggleVendorFollow">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">{{ followingVendor ? 'notifications_active' : 'add_alert' }}</span>
                    {{ followingVendor ? 'Đang theo dõi' : 'Theo dõi' }}
                  </button>
                  <button v-if="authStore.isAuthenticated" type="button" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-emerald-600/80 bg-emerald-50 px-3.5 text-xs font-bold text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white dark:bg-emerald-950/40 dark:text-emerald-300 cursor-pointer" @click="contactVendor">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">chat</span>
                    Nhắn tin
                  </button>
                </div>
              </div>
              <div v-if="book.commercial_parties && Object.keys(book.commercial_parties).length" class="mt-5 grid gap-3 sm:grid-cols-3">
                <div v-for="(party, role) in book.commercial_parties" :key="role" class="h-full flex flex-col justify-between rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4 transition-shadow duration-200 hover:shadow-md">
                  <div>
                    <div class="flex items-start gap-1.5 mb-1">
                      <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ role === 'publisher' ? 'Nhà xuất bản' : role === 'supplier' ? 'Nhà cung cấp' : 'Đơn vị chịu trách nhiệm' }}</p>
                      <InfoTip v-if="party.is_demo" text="Dữ liệu mô phỏng phục vụ trình diễn, không phải xác minh quan hệ pháp lý." :label="`Giải thích dữ liệu mô phỏng của ${party.display_name}`" />
                    </div>
                    <router-link :to="{ name: 'organization-public', params: { slug: party.slug } }" class="mt-1.5 block font-bold text-on-surface no-underline hover:text-primary leading-snug focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-fixed-dim">{{ party.display_name }}</router-link>
                  </div>
                  <div class="mt-2 pt-2 border-t border-outline-variant/10">
                    <span v-if="!party.is_demo" class="inline-flex items-center gap-1 text-xs font-semibold text-primary"><span class="material-symbols-outlined text-sm" aria-hidden="true">verified</span>Đã xác minh</span>
                  </div>
                </div>
              </div>
              <p v-else class="mt-4 rounded-xl bg-surface-container p-4 text-sm text-on-surface-variant">Sách chưa được gắn Nhà xuất bản, Nhà cung cấp và đơn vị chịu trách nhiệm.</p>
            </section>
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
                       <span class="font-bold text-on-surface text-sm ml-1">{{ averageRating }}</span>
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
              <div class="bg-surface-container-low/60 rounded-3xl p-5 sm:p-6 border border-outline-variant/20 mb-6 shadow-2xs">
                 <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 sm:gap-5">
                    <!-- Price Info -->
                    <div class="shrink-0">
                       <p class="mb-1 text-[11px] font-extrabold uppercase tracking-wider text-primary">Giá bán niêm yết</p>
                       <div class="flex flex-wrap items-baseline gap-2.5">
                          <span class="text-2xl sm:text-3xl font-black text-red-600 dark:text-red-400 tracking-tight leading-none">
                             {{ formatCurrency(book.sale_price || book.price) }}
                          </span>
                          <span v-if="book.sale_price && book.price > book.sale_price" class="text-sm sm:text-base text-outline/60 line-through font-bold">
                             {{ formatCurrency(book.price) }}
                          </span>
                       </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 sm:gap-2.5 flex-wrap sm:flex-nowrap shrink-0">
                       <template v-if="book.type === 'ebook' && ownershipData.owned">
                          <button @click="goToReader" class="h-11 bg-on-surface text-surface px-5 rounded-2xl font-bold text-xs uppercase tracking-wider shadow-md hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2 whitespace-nowrap cursor-pointer border-none">
                            <span class="material-symbols-outlined text-lg">auto_stories</span>
                            Đọc Sách Ngay
                          </button>
                       </template>
                       <template v-else-if="book.type !== 'ebook' && (Number(book.stock) <= 0 || (book.status && book.status !== 'published'))">
                          <div class="h-11 px-4 rounded-2xl bg-surface-container-high text-outline font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 whitespace-nowrap cursor-not-allowed border border-outline-variant/30">
                            <span class="material-symbols-outlined text-lg">remove_shopping_cart</span>
                            Sách đã hết hàng (Tạm ngừng bán)
                          </div>
                          <button @click="toggleWishlist" class="h-11 w-11 shrink-0 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest flex items-center justify-center hover:bg-surface-container-high transition-all shadow-2xs cursor-pointer" :aria-label="wishlistStore.isFavorite(book?.id) ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích'">
                            <span class="material-symbols-outlined text-xl" :class="wishlistStore.isFavorite(book?.id) ? 'text-error fill-1' : 'text-outline'">favorite</span>
                          </button>
                       </template>
                       <template v-else>
                          <button @click="addToCart" class="h-11 px-3.5 sm:px-4 rounded-2xl border-2 border-primary text-primary font-bold text-xs uppercase tracking-wider hover:bg-primary hover:text-on-primary transition-all flex items-center justify-center gap-1.5 whitespace-nowrap cursor-pointer shadow-2xs">
                            <span class="material-symbols-outlined text-lg">shopping_bag</span>
                            Thêm vào giỏ
                          </button>
                          <button @click="buyNow" class="h-11 bg-primary text-on-primary px-5 sm:px-6 rounded-2xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-primary/20 hover:bg-primary/90 active:scale-95 transition-all whitespace-nowrap cursor-pointer border-none flex items-center justify-center">
                            Mua ngay
                          </button>
                          <button @click="toggleWishlist" class="h-11 w-11 shrink-0 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest flex items-center justify-center hover:bg-surface-container-high transition-all shadow-2xs cursor-pointer" :aria-label="wishlistStore.isFavorite(book?.id) ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích'">
                            <span class="material-symbols-outlined text-xl" :class="wishlistStore.isFavorite(book?.id) ? 'text-error fill-1' : 'text-outline'">favorite</span>
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

                <div v-if="sortedChapters.length > 0" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                  <div                    v-for="chapter in sortedChapters"                    :key="chapter.id"
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

            <SeriesOrbitCarousel
              v-if="seriesBooks.length"
              :books="seriesBooks"
              :current-book-id="book.id"
              :series-title="book.series?.title"
            />

          </div>

        </div>

        <!-- ─── FULL WIDTH REVIEW SECTION: CẢM NHẬN ĐỘC GIẢ (COMPACT 2-COLUMN LAYOUT) ─── -->
        <section class="mt-8 bg-surface-container-lowest rounded-[32px] p-6 lg:p-8 border border-outline-variant/10 shadow-sm relative animate-fade-in">
          <!-- Section Header -->
          <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-outline-variant/10">
            <div>
              <h3 class="text-xl font-extrabold text-on-surface tracking-tight mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary text-2xl">forum</span>
                Cảm nhận độc giả
                <span v-if="book.reviews?.length" class="text-xs font-bold bg-primary/10 text-primary px-2.5 py-0.5 rounded-full">
                  {{ book.reviews.length }}
                </span>
              </h3>
              <p class="text-xs text-on-surface-variant font-medium opacity-70">Đánh giá và trải nghiệm thực tế từ các độc giả tại KomiBook.</p>
            </div>
            <button @click="openNewReviewModal" class="min-h-11 rounded-xl border-none bg-primary px-6 text-xs font-bold uppercase tracking-wider text-on-primary shadow-md shadow-primary/20 hover:scale-105 active:scale-95 transition-all self-start sm:self-auto cursor-pointer flex items-center gap-2">
              <span class="material-symbols-outlined text-base">rate_review</span>
              Viết đánh giá
            </button>
          </header>

          <!-- 2-COLUMN GRID (col-span-4 Rating Summary & Filters + col-span-8 Review Cards) -->
          <div v-if="book.reviews?.length > 0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- ─── LEFT SIDEBAR (4 COLS): RATING SUMMARY CARD, BỘ LỌC & SẮP XẾP ─── -->
            <div class="lg:col-span-4 space-y-4 lg:sticky lg:top-24">
              <!-- Rating Summary & Filters Combined Card (Fills all empty space below 5-star bars!) -->
              <div class="p-5 rounded-2xl bg-surface-container-low/40 border border-outline-variant/15 space-y-4 shadow-xs">
                <!-- Score Header -->
                <div class="flex flex-col items-center text-center">
                  <div class="text-4xl font-black text-on-surface tracking-tighter leading-none mb-1.5">
                    {{ ratingStats.average }}
                    <span class="text-base text-outline font-normal">/ 5</span>
                  </div>
                  <div class="flex items-center justify-center gap-1 mb-1">
                    <span v-for="i in 5" :key="i" class="material-symbols-outlined text-lg" :style="{ 'font-variation-settings': i <= Math.round(ratingStats.average) ? `'FILL' 1` : `'FILL' 0`, color: i <= Math.round(ratingStats.average) ? '#ba0035' : '#c3c6ce' }">star</span>
                  </div>
                  <p class="text-xs font-semibold text-on-surface-variant">
                    Dựa trên <strong class="text-on-surface">{{ book.reviews.length }}</strong> đánh giá
                  </p>
                  <div v-if="ratingStats.recommendRate > 0" class="mt-2.5 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300 px-3 py-1 rounded-full border border-emerald-200">
                    <span class="material-symbols-outlined text-sm">thumb_up</span>
                    {{ ratingStats.recommendRate }}% độc giả hài lòng
                  </div>
                </div>

                <!-- 5-Star Breakdown Bars (Vertical & Snug) -->
                <div class="w-full space-y-2 pt-3 border-t border-outline-variant/10">
                  <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-2 text-xs">
                    <button
                      @click="activeReviewFilter = activeReviewFilter === star ? 'all' : star"
                      class="flex items-center gap-0.5 font-bold w-10 shrink-0 hover:text-primary transition-colors cursor-pointer border-none bg-transparent text-left"
                      :class="activeReviewFilter === star ? 'text-primary' : 'text-on-surface-variant'"
                    >
                      <span>{{ star }}</span>
                      <span class="material-symbols-outlined text-xs text-amber-500" style="font-variation-settings: 'FILL' 1">star</span>
                    </button>
                    <div
                      @click="activeReviewFilter = activeReviewFilter === star ? 'all' : star"
                      class="flex-1 h-2 bg-surface-container-high rounded-full overflow-hidden cursor-pointer group"
                    >
                      <div
                        class="h-full bg-primary rounded-full transition-all duration-500 group-hover:bg-primary/80"
                        :style="{ width: `${ratingStats.percentages[star]}%` }"
                      ></div>
                    </div>
                    <span class="w-10 text-right text-[11px] font-semibold text-outline shrink-0">
                      {{ ratingStats.counts[star] }}
                    </span>
                  </div>
                </div>

                <!-- 🔍 FILTER CHIPS & SORT DROPDOWN (MOVED DOWN HERE TO FILL EMPTY SPACE UNDER 5-STAR BARS!) -->
                <div class="pt-3.5 border-t border-outline-variant/10 space-y-3">
                  <!-- Filter Chips -->
                  <div class="flex flex-wrap gap-1.5">
                    <button
                      v-for="filter in reviewFilters"
                      :key="filter.value"
                      @click="activeReviewFilter = filter.value"
                      class="min-h-7 px-2.5 rounded-full text-[11px] font-bold transition-all cursor-pointer border flex items-center gap-1 whitespace-nowrap"
                      :class="activeReviewFilter === filter.value
                        ? 'bg-primary text-on-primary border-primary shadow-xs'
                        : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant/20 hover:border-primary/40'"
                    >
                      <span v-if="filter.icon" class="material-symbols-outlined text-xs">{{ filter.icon }}</span>
                      {{ filter.label }}
                      <span v-if="filter.count !== undefined" class="text-[9px] opacity-80 font-semibold px-1.5 py-0.1 bg-black/10 rounded-full">
                        {{ filter.count }}
                      </span>
                    </button>
                  </div>

                  <!-- Sort dropdown -->
                  <div class="flex items-center justify-between text-xs pt-1 border-t border-outline-variant/10">
                    <span class="text-outline font-medium">Sắp xếp:</span>
                    <select
                      v-model="reviewSortBy"
                      class="px-2.5 py-1 rounded-xl border border-outline-variant/30 bg-surface-container-lowest text-on-surface font-semibold text-xs focus:outline-none focus:border-primary cursor-pointer"
                    >
                      <option value="newest">Mới nhất</option>
                      <option value="highest">Đánh giá cao nhất</option>
                      <option value="lowest">Đánh giá thấp nhất</option>
                      <option value="helpful">Hữu ích nhất</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Popular Sentiment Tags Cloud (If available) -->
              <div v-if="popularTags.length > 0" class="p-4 rounded-2xl bg-surface-container-low/20 border border-outline-variant/10 space-y-2">
                <span class="text-xs font-bold text-outline uppercase tracking-wider flex items-center gap-1">
                  <span class="material-symbols-outlined text-sm text-secondary">sell</span>
                  Chủ đề nổi bật:
                </span>
                <div class="flex flex-wrap gap-1.5 pt-1">
                  <button
                    v-for="tag in popularTags"
                    :key="tag.name"
                    @click="toggleTagFilter(tag.name)"
                    class="text-xs font-medium px-2.5 py-1 rounded-lg transition-colors border border-outline-variant/20 cursor-pointer"
                    :class="selectedTagFilter === tag.name ? 'bg-secondary text-on-secondary border-secondary font-bold' : 'bg-surface-container-lowest text-on-surface-variant hover:border-secondary/50'"
                  >
                    #{{ tag.name }} <span class="text-[10px] opacity-70">({{ tag.count }})</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- ─── RIGHT CONTENT (8 COLS): REVIEWS LIST CARDS ─── -->
            <div class="lg:col-span-8 space-y-4">

              <!-- Review Cards List (Compact Snug Cards) -->
              <div v-if="filteredReviews.length > 0" class="space-y-3.5 max-h-[650px] overflow-y-auto pr-1.5 scrollbar-thin">
                <article
                  v-for="review in filteredReviews"
                  :key="review.id"
                  class="p-4 rounded-2xl bg-surface-container-low/30 border border-outline-variant/15 hover:border-primary/20 transition-all space-y-3 shadow-2xs"
                >
                  <div>
                    <!-- User Header -->
                    <div class="flex items-start justify-between gap-3 mb-2">
                      <div class="flex items-center gap-3">
                        <div class="relative w-9 h-9 rounded-full overflow-hidden shrink-0 shadow-2xs border border-outline-variant/30 bg-surface-container-high flex items-center justify-center">
                          <img
                            v-if="getReviewUserAvatar(review) && !isReviewAvatarBroken(review)"
                            :src="getReviewUserAvatar(review)"
                            :alt="review.user?.name || 'Avatar'"
                            class="w-full h-full object-cover rounded-full"
                            @error="markReviewAvatarBroken(review)"
                          />
                          <span v-else class="text-[11px] font-extrabold text-on-surface-variant" :aria-label="`Biểu tượng chữ cái của ${review.user?.name || 'độc giả'}`">{{ getInitials(review.user?.name) }}</span>
                        </div>
                        <div>
                          <div class="flex items-center gap-2">
                            <h4 class="text-xs font-bold text-on-surface truncate max-w-[180px] sm:max-w-[250px]">
                              {{ review.user?.name || 'Độc giả KomiBook' }}
                            </h4>
                            <!-- ✅ Verified Purchaser Badge -->
                            <span
                              v-if="review.is_verified || review.user_id === ownershipData.user_id || ownershipData.owned"
                              class="inline-flex items-center gap-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300 px-2 py-0.5 rounded-md border border-emerald-200"
                              title="Tài khoản đã mua sách này tại KomiBook"
                            >
                              <span class="material-symbols-outlined text-[13px]">verified</span>
                              Đã mua hàng
                            </span>
                          </div>
                          <span class="text-[10px] text-outline opacity-60">{{ formatDate(review.created_at) }}</span>
                        </div>
                      </div>

                      <div class="flex items-center gap-1.5 shrink-0" v-if="authStore.isAuthenticated">
                        <template v-if="isOwnReview(review)">
                          <button
                            @click="openEditReviewModal(review)"
                            type="button"
                            class="inline-flex items-center gap-1 text-[11px] font-bold text-primary bg-primary/10 hover:bg-primary/20 px-2.5 py-1 rounded-lg transition-colors border border-primary/20 cursor-pointer"
                            title="Chỉnh sửa đánh giá của bạn"
                          >
                            <span class="material-symbols-outlined text-xs">edit</span>
                            Sửa
                          </button>
                          <button
                            @click="deleteUserReview(review)"
                            type="button"
                            class="inline-flex items-center gap-1 text-[11px] font-bold text-error bg-error/10 hover:bg-error/20 px-2.5 py-1 rounded-lg transition-colors border border-error/20 cursor-pointer"
                            title="Xóa đánh giá của bạn"
                          >
                            <span class="material-symbols-outlined text-xs">delete</span>
                            Xóa
                          </button>
                        </template>

                        <button
                          v-else
                          @click="reportReview(review)"
                          type="button"
                          class="text-[10px] text-error opacity-60 hover:opacity-100 bg-transparent border-none cursor-pointer hover:underline shrink-0"
                        >
                          Báo cáo
                        </button>
                      </div>
                    </div>

                    <!-- Rating Stars & Quick Tags -->
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                      <div class="flex items-center gap-0.5">
                        <span v-for="i in 5" :key="i" class="material-symbols-outlined text-[14px]" :style="{ 'font-variation-settings': i <= review.rating ? `'FILL' 1` : `'FILL' 0`, color: i <= review.rating ? '#ba0035' : '#c3c6ce' }">star</span>
                      </div>
                      <div v-if="review.tags && review.tags.length" class="flex flex-wrap gap-1">
                        <span v-for="t in review.tags" :key="t" class="text-[10px] font-semibold bg-surface-container-high text-on-surface-variant px-2 py-0.5 rounded-full">
                          #{{ t }}
                        </span>
                      </div>
                    </div>

                    <!-- Comment Content -->
                    <p class="text-xs text-on-surface-variant leading-relaxed whitespace-pre-line">
                      {{ review.comment }}
                    </p>

                    <!-- 📸 Review Images (if present) -->
                    <div v-if="review.images && review.images.length" class="flex gap-2 mt-3 overflow-x-auto">
                      <img
                        v-for="(img, imgIdx) in review.images"
                        :key="imgIdx"
                        :src="img"
                        alt="Ảnh cảm nhận"
                        class="w-14 h-14 object-cover rounded-lg border border-outline-variant/30 cursor-pointer hover:opacity-90 transition-opacity"
                        @click="openReviewImage(img)"
                      />
                    </div>

                    <!-- 🏪 / 💬 Response Section (Vendor vs Customer/Reader) -->
                    <div v-if="review.vendor_reply" class="mt-3 p-3 rounded-xl text-xs space-y-1" :class="review.is_vendor_reply ? 'bg-primary/5 border border-primary/10' : 'bg-surface-container-low border border-outline-variant/20'">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5 font-bold text-[11px]" :class="review.is_vendor_reply ? 'text-primary' : 'text-on-surface'">
                          <span class="material-symbols-outlined text-sm">{{ review.is_vendor_reply ? 'storefront' : 'chat_bubble_outline' }}</span>
                          <span v-if="review.is_vendor_reply">
                            Phản hồi từ Gian hàng ({{ book.vendor?.name || 'KomiBook' }})
                          </span>
                          <span v-else>
                            Phản hồi từ {{ review.reply_user_name || 'Độc giả' }}
                          </span>
                        </div>
                        <div class="flex items-center gap-2" v-if="authStore.isAuthenticated">
                          <button @click="toggleReplyForm(review.id)" class="text-[10px] hover:underline bg-transparent border-none cursor-pointer font-semibold" :class="review.is_vendor_reply ? 'text-primary' : 'text-secondary'">
                            Sửa
                          </button>
                          <button @click="deleteVendorReply(review)" class="text-[10px] text-error/80 hover:text-error hover:underline bg-transparent border-none cursor-pointer font-semibold">
                            Xóa
                          </button>
                        </div>
                      </div>
                      <p class="text-[11px] text-on-surface-variant italic leading-relaxed">
                        "{{ review.vendor_reply }}"
                      </p>
                    </div>

                    <!-- ✏️ Inline Reply Form (Shown when clicking Reply button) -->
                    <div v-if="activeReplyReviewId === review.id" class="mt-3 p-3.5 rounded-xl bg-surface-container-lowest border border-secondary/40 space-y-2 animate-fade-in shadow-sm">
                      <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-secondary flex items-center gap-1">
                          <span class="material-symbols-outlined text-sm">reply</span>
                          Phản hồi cho {{ review.user?.name || 'độc giả' }}
                          <span class="text-[10px] font-semibold text-outline">({{ isVendorUser ? 'với tư cách Gian hàng' : 'với tư cách Độc giả' }})</span>:
                        </span>
                        <button @click="activeReplyReviewId = null" class="text-[10px] text-outline hover:text-error bg-transparent border-none cursor-pointer font-bold">✕ Đóng</button>
                      </div>
                      <textarea
                        v-model="replyText"
                        rows="2"
                        :placeholder="isVendorUser ? 'Viết câu trả lời / lời cảm ơn của Gian hàng...' : 'Viết phản hồi hoặc bình luận của bạn...'"
                        class="w-full p-2.5 rounded-lg border border-outline-variant/30 text-xs bg-surface-container-low text-on-surface focus:outline-none focus:border-secondary leading-relaxed"
                      ></textarea>
                      <div class="flex justify-end gap-2 pt-1">
                        <button @click="activeReplyReviewId = null" class="px-3 py-1 text-xs font-bold text-outline bg-transparent border-none cursor-pointer hover:bg-surface-container-high rounded-lg">Hủy</button>
                        <button @click="submitVendorReply(review)" :disabled="isSubmittingReply" class="px-4 py-1.5 rounded-lg bg-secondary text-on-secondary text-xs font-bold shadow-xs hover:bg-secondary/90 transition-all border-none cursor-pointer flex items-center gap-1">
                          <span class="material-symbols-outlined text-sm">send</span>
                          {{ isSubmittingReply ? 'Đang gửi...' : 'Gửi phản hồi' }}
                        </button>
                      </div>
                    </div>
                  </div>

                    <!-- Action Bar: Hữu ích, Sửa/Xóa đánh giá & Phản hồi -->
                    <div class="pt-2 border-t border-outline-variant/10 flex items-center justify-between text-xs">
                      <div class="flex items-center gap-2">
                        <button
                          @click="toggleHelpful(review)"
                          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-bold transition-all border border-transparent cursor-pointer"
                          :class="review.is_helpful_clicked
                            ? 'bg-primary/10 text-primary border-primary/20'
                            : 'bg-surface-container-lowest text-outline hover:bg-surface-container-high hover:text-on-surface'"
                        >
                          <span class="material-symbols-outlined text-sm" :class="review.is_helpful_clicked ? 'fill-1' : ''">thumb_up</span>
                          Hữu ích <span v-if="review.helpful_count > 0">({{ review.helpful_count }})</span>
                        </button>

                        <button
                          v-if="authStore.isAuthenticated"
                          @click="toggleReplyForm(review.id)"
                          class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-[11px] font-bold text-secondary hover:bg-secondary/10 transition-colors border-none bg-transparent cursor-pointer"
                        >
                          <span class="material-symbols-outlined text-sm">reply</span>
                          {{ review.vendor_reply ? 'Sửa phản hồi' : 'Phản hồi' }}
                        </button>
                      </div>

                      <span class="text-[10px] text-outline opacity-50">KomiBook Review</span>
                    </div>
                </article>
              </div>

              <!-- Empty Filter State -->
              <div v-else class="text-center py-10 bg-surface-container-low/20 rounded-2xl border border-dashed border-outline-variant/20">
                <span class="material-symbols-outlined text-4xl text-outline/30 mb-2">rate_review</span>
                <p class="text-sm font-semibold text-on-surface mb-1">Không tìm thấy đánh giá phù hợp bộ lọc</p>
                <p class="text-xs text-on-surface-variant font-medium opacity-60 mb-4">Thử chọn lại bộ lọc khác hoặc xem tất cả đánh giá.</p>
                <button
                  @click="activeReviewFilter = 'all'; selectedTagFilter = null"
                  class="text-xs font-bold text-primary hover:underline bg-transparent border-none cursor-pointer"
                >
                  Xóa bộ lọc
                </button>
              </div>
            </div>
          </div>

          <!-- Empty Overall Reviews State -->
          <div v-else class="text-center py-10 bg-surface-container-low/20 rounded-2xl border border-dashed border-outline-variant/20">
             <span class="material-symbols-outlined text-4xl text-outline/30 mb-2">rate_review</span>
             <p class="text-sm font-semibold text-on-surface mb-1">Chưa có cảm nhận nào</p>
             <p class="text-xs text-on-surface-variant font-medium opacity-60 mb-4">Hãy là người đầu tiên chia sẻ trải nghiệm về cuốn sách này!</p>
             <button @click="showReviewModal = true" class="px-5 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold shadow hover:bg-primary/90 transition-all border-none cursor-pointer">
               Viết đánh giá ngay
             </button>
          </div>
        </section>

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
    <Dialog
      v-model:visible="showReviewModal"
      modal
      :header="isEditingOwnReview ? 'Chỉnh sửa đánh giá của bạn' : 'Đánh giá tác phẩm'"
      class="!max-w-md !w-[90vw] !rounded-[32px] !bg-surface-container-lowest"
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
import { useChatStore } from '@/stores/chatStore'
import InfoTip from '@/components/InfoTip.vue'
import SeriesOrbitCarousel from '@/components/SeriesOrbitCarousel.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const chatStore = useChatStore()

const contactVendor = () => {
  if (!book.value?.vendor) return
  chatStore.openChatWithVendor(book.value.vendor.id, book.value.vendor.name || 'Gian hàng', {
    id: book.value.id,
    title: book.value.title,
  })
}

const book = ref(null)
const followingVendor = ref(false)
const followAvailable = ref(true)
const followLoading = ref(false)
const fetchState = ref('loading')
const requestError = ref('')
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
const isEditingOwnReview = ref(false)
const isSubmittingReview = ref(false)
const reviewForm = ref({ rating: 5, comment: '', tags: [] })

// Review Section Filters, Tags & Stats
const activeReviewFilter = ref('all')
const selectedTagFilter = ref(null)
const reviewSortBy = ref('newest')

const ratingStats = computed(() => {
  const reviews = book.value?.reviews || []
  const ratedReviews = reviews.map(review => Math.round(Number(review.rating))).filter(rating => rating >= 1 && rating <= 5)
  const total = ratedReviews.length
  if (!total) {
    return {
      average: '0.0',
      recommendRate: 0,
      counts: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 },
      percentages: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 }
    }
  }

  const counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 }
  let sum = 0
  let highRatingCount = 0

  ratedReviews.forEach(star => {
    counts[star] = (counts[star] || 0) + 1
    sum += star
    if (star >= 4) highRatingCount++
  })

  const average = (sum / total).toFixed(1)
  const recommendRate = Math.round((highRatingCount / total) * 100)
  const percentages = {
    5: Math.round((counts[5] / total) * 100),
    4: Math.round((counts[4] / total) * 100),
    3: Math.round((counts[3] / total) * 100),
    2: Math.round((counts[2] / total) * 100),
    1: Math.round((counts[1] / total) * 100)
  }

  return { average, recommendRate, counts, percentages }
})

const popularTags = computed(() => {
  const reviews = book.value?.reviews || []
  const tagMap = {}
  reviews.forEach(r => {
    if (Array.isArray(r.tags)) {
      r.tags.forEach(t => {
        tagMap[t] = (tagMap[t] || 0) + 1
      })
    }
  })
  return Object.keys(tagMap)
    .map(name => ({ name, count: tagMap[name] }))
    .sort((a, b) => b.count - a.count)
})

const reviewFilters = computed(() => {
  const reviews = book.value?.reviews || []
  const counts = {
    all: reviews.length,
    5: reviews.filter(r => Math.round(r.rating) === 5).length,
    4: reviews.filter(r => Math.round(r.rating) === 4).length,
    3: reviews.filter(r => Math.round(r.rating) === 3).length,
    low: reviews.filter(r => Math.round(r.rating) <= 2).length,
    verified: reviews.filter(r => r.is_verified || r.user_id === ownershipData.value?.user_id || ownershipData.value?.owned).length,
    has_tags: reviews.filter(r => Array.isArray(r.tags) && r.tags.length > 0).length
  }

  return [
    { label: 'Tất cả', value: 'all', count: counts.all, icon: 'grid_view' },
    { label: '5★', value: 5, count: counts[5] },
    { label: '4★', value: 4, count: counts[4] },
    { label: '3★', value: 3, count: counts[3] },
    { label: '2★ & 1★', value: 'low', count: counts.low },
    { label: 'Đã mua hàng', value: 'verified', count: counts.verified, icon: 'verified' },
    { label: 'Có chủ đề', value: 'has_tags', count: counts.has_tags, icon: 'sell' }
  ]
})

const toggleTagFilter = (tagName) => {
  if (selectedTagFilter.value === tagName) {
    selectedTagFilter.value = null
  } else {
    selectedTagFilter.value = tagName
  }
}

const filteredReviews = computed(() => {
  let list = [...(book.value?.reviews || [])]

  // Apply star / badge filter
  if (activeReviewFilter.value === 5) {
    list = list.filter(r => Math.round(r.rating) === 5)
  } else if (activeReviewFilter.value === 4) {
    list = list.filter(r => Math.round(r.rating) === 4)
  } else if (activeReviewFilter.value === 3) {
    list = list.filter(r => Math.round(r.rating) === 3)
  } else if (activeReviewFilter.value === 'low') {
    list = list.filter(r => Math.round(r.rating) <= 2)
  } else if (activeReviewFilter.value === 'verified') {
    list = list.filter(r => r.is_verified || r.user_id === ownershipData.value?.user_id || ownershipData.value?.owned)
  } else if (activeReviewFilter.value === 'has_tags') {
    list = list.filter(r => Array.isArray(r.tags) && r.tags.length > 0)
  }

  // Apply tag filter
  if (selectedTagFilter.value) {
    list = list.filter(r => Array.isArray(r.tags) && r.tags.includes(selectedTagFilter.value))
  }

  // Apply sorting
  list.sort((a, b) => {
    if (reviewSortBy.value === 'highest') return (b.rating || 0) - (a.rating || 0)
    if (reviewSortBy.value === 'lowest') return (a.rating || 0) - (b.rating || 0)
    if (reviewSortBy.value === 'helpful') return (b.helpful_count || 0) - (a.helpful_count || 0)
    return new Date(b.created_at || 0) - new Date(a.created_at || 0)
  })

  return list
})

const toggleHelpful = (review) => {
  if (!review.helpful_count) review.helpful_count = 0
  if (review.is_helpful_clicked) {
    review.helpful_count = Math.max(0, review.helpful_count - 1)
    review.is_helpful_clicked = false
    toast.add({ severity: 'info', summary: 'Thông báo', detail: 'Đã bỏ ghi nhận lượt hữu ích.', life: 2000 })
  } else {
    review.helpful_count += 1
    review.is_helpful_clicked = true
    toast.add({ severity: 'success', summary: 'Cảm ơn!', detail: 'Cảm ơn phản hồi của bạn về đánh giá này.', life: 2000 })
  }
}

// Vendor / Customer Reply Logic
const activeReplyReviewId = ref(null)
const replyText = ref('')
const isSubmittingReply = ref(false)

const isVendorUser = computed(() => {
  if (!authStore.user || !book.value?.vendor) return false
  const user = authStore.user
  const vendor = book.value.vendor
  return Boolean(
    user.vendor_id === vendor.id ||
    user.id === vendor.user_id ||
    user.role === 'vendor' ||
    (user.name && vendor.name && user.name.toLowerCase() === vendor.name.toLowerCase())
  )
})

// Vendor / Customer Reply Persistence Logic
const LOCAL_STORAGE_REPLIES_KEY = 'komibook_review_replies'

const loadPersistedReplies = () => {
  if (!book.value || !Array.isArray(book.value.reviews)) return
  try {
    const raw = localStorage.getItem(LOCAL_STORAGE_REPLIES_KEY)
    if (!raw) return
    const savedMap = JSON.parse(raw)
    book.value.reviews.forEach(review => {
      if (savedMap[review.id]) {
        review.vendor_reply = savedMap[review.id].vendor_reply
        review.reply_user_name = savedMap[review.id].reply_user_name
        review.is_vendor_reply = savedMap[review.id].is_vendor_reply
      }
    })
  } catch (err) {
    console.warn('Error loading replies from localStorage:', err)
  }
}

const saveReplyToStorage = (reviewId, replyPayload) => {
  try {
    const raw = localStorage.getItem(LOCAL_STORAGE_REPLIES_KEY)
    const savedMap = raw ? JSON.parse(raw) : {}
    if (replyPayload) {
      savedMap[reviewId] = replyPayload
    } else {
      delete savedMap[reviewId]
    }
    localStorage.setItem(LOCAL_STORAGE_REPLIES_KEY, JSON.stringify(savedMap))
  } catch (err) {
    console.warn('Error saving reply to localStorage:', err)
  }
}

const toggleReplyForm = (reviewId) => {
  if (activeReplyReviewId.value === reviewId) {
    activeReplyReviewId.value = null
    replyText.value = ''
  } else {
    activeReplyReviewId.value = reviewId
    const targetReview = book.value?.reviews?.find(r => r.id === reviewId)
    replyText.value = targetReview?.vendor_reply || ''
  }
}

const submitVendorReply = async (review) => {
  if (!replyText.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập nội dung phản hồi.', life: 3000 })
    return
  }
  isSubmittingReply.value = true
  const isVendor = isVendorUser.value
  const replierName = authStore.user?.name || (isVendor ? (book.value.vendor?.name || 'Gian hàng') : 'Độc giả')

  const payload = {
    vendor_reply: replyText.value,
    reply_user_name: replierName,
    is_vendor_reply: isVendor
  }

  try {
    await apiClient.post(`/api/reviews/${review.id}/reply`, {
      reply: replyText.value,
      is_vendor: isVendor,
      user_name: replierName
    }).catch(() => {})
  } catch (err) {
    console.warn('Backend reply endpoint response:', err)
  } finally {
    review.vendor_reply = replyText.value
    review.reply_user_name = replierName
    review.is_vendor_reply = isVendor

    saveReplyToStorage(review.id, payload)

    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: isVendor ? 'Đã gửi phản hồi từ Gian hàng!' : 'Đã gửi phản hồi của bạn!',
      life: 3000
    })
    activeReplyReviewId.value = null
    replyText.value = ''
    isSubmittingReply.value = false
  }
}

const deleteVendorReply = (review) => {
  if (!window.confirm('Bạn có chắc chắn muốn xóa phản hồi này?')) return
  review.vendor_reply = null
  review.reply_user_name = null
  review.is_vendor_reply = false
  saveReplyToStorage(review.id, null)
  toast.add({ severity: 'info', summary: 'Đã xóa', detail: 'Đã xóa phản hồi khỏi cảm nhận.', life: 2000 })
}

const previewDialogVisible = ref(false)
const activePreviewChapter = ref(null)

const activeImageIndex = ref(0)
const thumbnailScrollContainer = ref(null)
const failedGalleryImagePaths = ref([])
const brokenRelatedCoverIds = ref([])
const vendorAvatarBroken = ref(false)
const brokenReviewAvatarKeys = ref([])

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

const getInitials = (name) => {
  const words = String(name || '').trim().split(/\s+/).filter(Boolean)
  return words.slice(0, 2).map(word => word.charAt(0).toUpperCase()).join('') || 'KB'
}

const reviewAvatarKey = (review) => String(review?.id ?? review?.user_id ?? review?.user?.id ?? '')

const isReviewAvatarBroken = (review) => brokenReviewAvatarKeys.value.includes(reviewAvatarKey(review))

const markReviewAvatarBroken = (review) => {
  const key = reviewAvatarKey(review)
  if (key && !brokenReviewAvatarKeys.value.includes(key)) brokenReviewAvatarKeys.value.push(key)
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
  { label: 'Đánh giá', value: `${averageRating.value} (${book.value?.reviews?.length || 0})`, icon: 'star_rate' },
  { label: 'Yêu thích', value: book.value?.wishlists_count || 0, icon: 'favorite' },
  { label: 'Khám phá', value: book.value?.views || 0, icon: 'visibility' }
])

const sortedChapters = computed(() => {
  return [...(book.value?.chapters || [])].sort((a, b) => (a.order || 0) - (b.order || 0))
})

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
  } else if (book.value.cover_format) {
    tags.push(book.value.cover_format)
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
      value: book.value.commercial_parties?.supplier?.display_name || 'Chưa cập nhật',
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
      { label: 'Hình thức bìa', value: book.value.cover_format || 'Chưa cập nhật' },
      { label: 'Kích thước', value: book.value.dimensions || 'Chưa cập nhật' },
      { label: 'Trọng lượng', value: book.value.weight ? (book.value.weight.toString().includes('g') ? book.value.weight : `${book.value.weight} gam`) : 'Chưa cập nhật' }
    )
  }

  meta.push(
    { label: 'Số trang', value: book.value.pages ? `${book.value.pages} trang` : 'Đang cập nhật' },
    { label: 'Năm xuất bản', value: book.value.release_date || 'Chưa cập nhật' },
    { label: 'Ngôn ngữ', value: book.value.language || 'Chưa cập nhật' },
    { label: 'Độ tuổi', value: book.value.target_age || 'Chưa cập nhật' }
  )

  if (book.value.type !== 'ebook' && book.value.isbn) {
    meta.push({ label: 'Mã ISBN / SKU', value: book.value.isbn })
  }

  return meta
})

const fetchBookDetail = async () => {
  book.value = null
  fetchState.value = 'loading'
  requestError.value = ''
  followingVendor.value = false
  followAvailable.value = true
  followLoading.value = false
  ownershipData.value = { owned: false, order_id: null, book_id: null }
  seriesBooks.value = []
  relatedCategoryBooks.value = []
  relatedAuthorBooks.value = []
  vendorAvatarBroken.value = false
  brokenReviewAvatarKeys.value = []
  try {
    const response = await apiClient.get(`/api/books/${route.params.slug}`)
    const responseData = response.data.data || response.data
    book.value = responseData
    fetchState.value = 'ready'
    loadPersistedReplies()
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
    if (error.response?.status === 404) {
      fetchState.value = 'notFound'
    } else {
      requestError.value = error.response?.data?.message || 'Không thể kết nối tới máy chủ. Vui lòng thử lại.'
      fetchState.value = 'error'
    }
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
  if (!book.value || !book.value.reviews || book.value.reviews.length === 0) return 0
  const ratings = book.value.reviews.map(review => Number(review.rating)).filter(rating => rating >= 1 && rating <= 5)
  if (!ratings.length) return 0
  return Math.round((ratings.reduce((sum, rating) => sum + rating, 0) / ratings.length) * 10) / 10
})

const submitReview = async () => {
  if (!reviewForm.value.comment) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng viết lời nhận xét của bạn.', life: 3000 })
    return
  }
  isSubmittingReview.value = true
  try {
    const response = await apiClient.post(`/api/books/${book.value.id}/reviews`, reviewForm.value)
    toast.add({ severity: 'success', summary: 'Thành công', detail: response.data.message || 'Cảm ơn bạn đã lưu đánh giá!', life: 3000 })
    if (!book.value.reviews) book.value.reviews = []
    const existingIndex = book.value.reviews.findIndex(review => review.user_id === response.data.data.user_id || review.id === response.data.data.id)
    if (existingIndex >= 0) book.value.reviews.splice(existingIndex, 1, response.data.data)
    else book.value.reviews.unshift(response.data.data)
    reviewForm.value = { rating: 5, comment: '', tags: [] }
    showReviewModal.value = false
    isEditingOwnReview.value = false
  } catch (error) {
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi gửi đánh giá'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    isSubmittingReview.value = false
  }
}

const getVendorLogoUrl = (vendor) => {
  if (!vendor) return ''
  const logo = vendor.logo || vendor.logo_url || vendor.avatar || vendor.avatar_url
  return logo ? getCoverUrl(logo) : ''
}

const vendorLogoUrl = computed(() => getVendorLogoUrl(book.value?.vendor))

const getReviewUserAvatar = (review) => {
  let avatarPath = review.user?.avatar || review.user?.avatar_url || review.avatar || review.user_avatar
  if (!avatarPath && isOwnReview(review)) {
    avatarPath = authStore.user?.avatar || authStore.user?.avatar_url
  }
  if (avatarPath) {
    return getCoverUrl(avatarPath)
  }
  return ''
}

const isOwnReview = (review) => {
  if (!authStore.isAuthenticated || !authStore.user) return false
  const userId = authStore.user.id
  const userName = authStore.user.name
  const userEmail = authStore.user.email

  if (review.user_id != null && userId != null && String(review.user_id) === String(userId)) return true
  if (review.user?.id != null && userId != null && String(review.user.id) === String(userId)) return true
  if (userName && review.user?.name && review.user.name.trim().toLowerCase() === userName.trim().toLowerCase()) return true
  if (userEmail && review.user?.email && review.user.email.trim().toLowerCase() === userEmail.trim().toLowerCase()) return true
  return false
}

const openNewReviewModal = () => {
  isEditingOwnReview.value = false
  reviewForm.value = { rating: 5, comment: '', tags: [] }
  showReviewModal.value = true
}

const openEditReviewModal = (review) => {
  isEditingOwnReview.value = true
  reviewForm.value = {
    rating: review.rating || 5,
    comment: review.comment || '',
    tags: Array.isArray(review.tags) ? [...review.tags] : []
  }
  showReviewModal.value = true
}

const deleteUserReview = async (review) => {
  if (!window.confirm('Bạn có chắc chắn muốn xóa đánh giá của mình?')) return
  try {
    await apiClient.delete(`/api/books/${book.value.id}/reviews/${review.id}`).catch(async () => {
      await apiClient.delete(`/api/reviews/${review.id}`)
    })
  } catch (e) {
    console.warn('Xóa trên backend thất bại hoặc chưa có route delete, cập nhật trên client:', e)
  } finally {
    if (book.value?.reviews) {
      const idx = book.value.reviews.findIndex(r => r.id === review.id)
      if (idx >= 0) book.value.reviews.splice(idx, 1)
    }
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Đã xóa đánh giá của bạn thành công.', life: 3000 })
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
    vendor: book.value.vendor, vendor_id: book.value.vendor?.id,
    category: book.value.category, categories: book.value.categories,
    ...(Object.prototype.hasOwnProperty.call(book.value, 'category_id') ? { category_id: book.value.category_id } : {})
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

onMounted(async () => {
  await fetchBookDetail()
  wishlistStore.fetchWishlistIds()

  if (route.hash === '#reviews' || route.query.action === 'review') {
    setTimeout(() => {
      const reviewSection = document.getElementById('reviews-section') || document.querySelector('[aria-labelledby="customer-reviews-title"]')
      if (reviewSection) {
        reviewSection.scrollIntoView({ behavior: 'smooth' })
      }
    }, 300)
  }
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
