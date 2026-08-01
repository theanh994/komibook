<template>
  <div class="min-h-screen bg-background">

    <!-- ═══ Flash Sale Banner ═══ -->
    <Transition name="slide-down">
      <div v-if="activeFlashSale" class="flash-sale-banner">
        <div class="max-w-[1280px] mx-auto px-gutter py-3 flex flex-col md:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="flash-icon-box">
              <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">bolt</span>
            </div>
            <span class="text-white text-sm font-medium">
              ⚡ Flash Sale: <span class="font-extrabold text-white text-base mr-1">{{ activeFlashSale.title }}</span> đang diễn ra! Giảm giá trực tiếp cực sâu trên từng đầu sách.
            </span>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-white/80 text-[10px] md:text-xs font-bold uppercase tracking-widest hidden sm:inline">Kết thúc sau</span>
            <div class="flex items-center gap-1.5">
              <div class="timer-box"><span class="timer-num">{{ countdown.hours }}</span><span class="timer-unit">Giờ</span></div>
              <span class="text-white font-bold text-lg -mt-3">:</span>
              <div class="timer-box"><span class="timer-num">{{ countdown.minutes }}</span><span class="timer-unit">Phút</span></div>
              <span class="text-white font-bold text-lg -mt-3">:</span>
              <div class="timer-box"><span class="timer-num">{{ countdown.seconds }}</span><span class="timer-unit">Giây</span></div>
            </div>
            <router-link to="/flash-sale" class="bg-white text-primary text-[11px] font-black uppercase tracking-widest px-4 py-2 rounded-xl shadow-lg hover:scale-105 active:scale-95 transition-all flex items-center gap-0.5 ml-2">
              Săn ngay <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            </router-link>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ═══ HERO CAROUSEL ═══ -->
    <section class="mx-auto w-full max-w-[1440px] px-4 py-8 md:px-gutter md:py-12" aria-labelledby="home-hero-title">
      <div
        class="relative isolate flex min-h-[420px] items-end overflow-hidden rounded-2xl bg-primary shadow-elevated md:min-h-[500px]"
        aria-roledescription="carousel"
        aria-label="Sự kiện nổi bật"
      >
        <img
          v-if="activeHero.cover_image"
          :src="getCoverUrl(activeHero.cover_image)"
          :alt="activeHero.title"
          class="absolute inset-0 h-full w-full object-cover"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-primary/20"></div>

        <div class="relative z-10 max-w-3xl p-6 text-white md:p-12 lg:p-16">
          <p class="mb-3 text-sm font-bold uppercase tracking-[0.18em] text-indigo-200">{{ activeHero.eyebrow }}</p>
          <h1 id="home-hero-title" class="text-3xl font-bold leading-tight tracking-tight md:text-5xl">{{ activeHero.title }}</h1>
          <p class="mt-4 max-w-2xl text-base leading-7 text-slate-200 md:text-lg">{{ activeHero.excerpt }}</p>
          <router-link
            :to="activeHero.to"
            class="mt-7 inline-flex min-h-11 items-center gap-2 rounded-lg bg-secondary px-5 py-3 text-sm font-bold text-on-secondary no-underline transition-colors hover:bg-secondary-container"
          >
            {{ activeHero.cta }}
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
          </router-link>
          <p v-if="heroError" class="mt-4 text-sm text-slate-300" role="status">
            Tin nổi bật chưa tải được; nội dung giới thiệu KomiBook đang được hiển thị.
          </p>
        </div>

        <template v-if="heroSlides.length > 1">
          <button type="button" class="absolute left-3 top-1/2 z-20 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-slate-950/60 text-white transition-colors hover:bg-slate-950" aria-label="Nội dung nổi bật trước" @click="previousHero">
            <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
          </button>
          <button type="button" class="absolute right-3 top-1/2 z-20 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-slate-950/60 text-white transition-colors hover:bg-slate-950" aria-label="Nội dung nổi bật tiếp theo" @click="nextHero">
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
          </button>
          <div class="absolute bottom-4 right-4 z-20 flex gap-2" aria-label="Chọn nội dung nổi bật">
            <button
              v-for="(slide, index) in heroSlides"
              :key="slide.key"
              type="button"
              class="h-11 w-11 rounded-full border-0 bg-transparent p-3"
              :aria-label="`Hiển thị nội dung ${index + 1}: ${slide.title}`"
              :aria-current="currentHeroIndex === index ? 'true' : undefined"
              @click="currentHeroIndex = index"
            >
              <span class="block h-2.5 w-2.5 rounded-full border border-white" :class="currentHeroIndex === index ? 'bg-white' : 'bg-white/30'"></span>
            </button>
          </div>
        </template>
      </div>
    </section>

    <section v-if="upcomingFlashSale" class="mx-auto w-full max-w-[1280px] px-4 py-8 md:px-gutter" aria-labelledby="upcoming-flash-title">
      <div class="overflow-hidden rounded-2xl border border-secondary/30 bg-gradient-to-r from-primary to-slate-800 p-5 text-white shadow-lg md:p-7">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
          <div>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-rose-200">Flash Sale sắp diễn ra</p>
            <h2 id="upcoming-flash-title" class="mt-2 text-2xl font-bold md:text-3xl">{{ upcomingFlashSale.title }}</h2>
            <p class="mt-2 text-sm text-slate-200">Bắt đầu lúc {{ formatFlashTime(upcomingFlashSale.start_time) }}. Tối đa 4 gian hàng được xếp theo lượt truy cập.</p>
            <div v-if="upcomingFlashSale.vendor_spotlights?.length" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <router-link v-for="vendor in upcomingFlashSale.vendor_spotlights" :key="vendor.id" :to="{ name: 'vendor-storefront', params: { slug: vendor.slug } }" class="flex min-h-16 items-center gap-3 rounded-xl bg-white/10 p-3 text-white no-underline transition-colors hover:bg-white/20">
                <img v-if="vendor.logo" :src="vendor.logo" :alt="`Logo ${vendor.shop_name}`" class="h-10 w-10 rounded-lg bg-white object-contain" />
                <span v-else class="material-symbols-outlined grid h-10 w-10 place-items-center rounded-lg bg-white/10" aria-hidden="true">storefront</span>
                <span class="min-w-0 truncate text-sm font-bold">{{ vendor.shop_name }}</span>
              </router-link>
            </div>
          </div>
          <div class="rounded-xl bg-white/10 p-4 text-center" aria-live="polite">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-200">Còn lại</p>
            <div class="mt-2 flex items-center justify-center gap-2 font-mono text-2xl font-black">
              <span>{{ upcomingCountdown.days }} ngày</span><span aria-hidden="true">:</span><span>{{ upcomingCountdown.hours }}</span><span aria-hidden="true">:</span><span>{{ upcomingCountdown.minutes }}</span><span aria-hidden="true">:</span><span>{{ upcomingCountdown.seconds }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ RECOMMENDATION ═══ -->
    <section class="mx-auto w-full max-w-[1280px] px-4 py-8 md:px-gutter md:py-xl" aria-labelledby="recommendation-title">
      <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary">Đề xuất đọc tiếp</p>
          <h2 id="recommendation-title" class="mt-2 text-2xl font-bold tracking-tight text-primary md:text-3xl">Gợi ý dành riêng cho bạn</h2>
          <p class="mt-2 text-sm text-on-surface-variant">{{ recommendationExplanation }}</p>
        </div>
        <router-link to="/catalog" class="inline-flex min-h-11 items-center gap-1 text-sm font-bold text-primary no-underline">
          Xem thêm <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
        </router-link>
      </div>

      <div v-if="loadingRecommendations" class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5" role="status" aria-label="Đang tải gợi ý sách">
        <div v-for="i in 5" :key="i" class="overflow-hidden rounded-xl border border-outline-variant/30 bg-surface-container-lowest">
          <Skeleton height="240px" borderRadius="0" />
          <div class="space-y-3 p-4"><Skeleton height="16px" /><Skeleton height="14px" width="65%" /></div>
        </div>
      </div>
      <div v-else-if="recommendationError" class="ui-state-panel" role="alert">
        <span class="material-symbols-outlined text-3xl text-error" aria-hidden="true">error</span>
        <p class="mt-2 font-bold">Chưa thể tải gợi ý sách</p>
        <button type="button" class="ui-button ui-button-secondary mt-4" @click="fetchRecommendations">Thử lại</button>
      </div>
      <div v-else-if="recommendations.length === 0" class="ui-state-panel">
        <span class="material-symbols-outlined text-3xl text-outline" aria-hidden="true">auto_stories</span>
        <p class="mt-2 font-bold">Chưa có sách phù hợp để gợi ý</p>
        <router-link to="/catalog" class="ui-button ui-button-secondary mt-4 no-underline">Khám phá danh mục</router-link>
      </div>
      <div v-else class="recommendation-grid">
        <div v-for="book in recommendations" :key="book.id" class="recommendation-item min-w-0">
          <BookCard
          :book="book"
          show-wishlist
          :is-favorite="wishlistStore.isFavorite(book.id)"
          @quick-view="openQuickView"
          @add-to-cart="addToCart"
          @buy-now="buyNow"
          @toggle-wishlist="toggleWishlist"
          />
        </div>
      </div>
    </section>

    <!-- ═══ COMMERCE & CONTENT FEED ═══ -->
    <section class="mx-auto w-full max-w-[1280px] px-4 py-8 md:px-gutter md:py-xl" aria-labelledby="commerce-feed-title">
      <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary">Khám phá theo nhu cầu</p>
        <h2 id="commerce-feed-title" class="mt-2 text-2xl font-bold tracking-tight text-primary md:text-3xl">Sách nổi bật trên KomiBook</h2>
      </div>

      <div class="space-y-12">
        <section v-for="group in commerceGroups" :key="group.key" :aria-labelledby="`home-${group.key}`">
          <div class="mb-5 flex items-end justify-between gap-4">
            <div>
              <h3 :id="`home-${group.key}`" class="text-xl font-bold text-on-surface md:text-2xl">{{ group.title }}</h3>
              <p class="mt-1 text-sm text-on-surface-variant">{{ group.subtitle }}</p>
            </div>
            <router-link :to="group.to" class="inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-bold text-primary no-underline">
              Xem thêm <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
            </router-link>
          </div>

          <div v-if="commerceState[group.key].loading" class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5" role="status" :aria-label="`Đang tải ${group.title}`">
            <div v-for="i in 5" :key="i" class="overflow-hidden rounded-xl border border-outline-variant/30 bg-surface-container-lowest">
              <Skeleton height="220px" borderRadius="0" />
              <div class="space-y-3 p-4"><Skeleton height="16px" /><Skeleton height="14px" width="60%" /></div>
            </div>
          </div>
          <div v-else-if="commerceState[group.key].error" class="ui-state-panel" role="alert">
            <p class="font-bold">Chưa thể tải {{ group.title.toLowerCase() }}</p>
            <button type="button" class="ui-button ui-button-secondary mt-4" @click="fetchCommerceGroup(group)">Thử lại</button>
          </div>
          <div v-else-if="commerceState[group.key].items.length === 0" class="ui-state-panel">
            <p class="font-bold">Chưa có {{ group.title.toLowerCase() }}</p>
            <p class="mt-2 text-sm text-on-surface-variant">Nội dung sẽ xuất hiện khi có sách đủ điều kiện.</p>
          </div>
          <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5">
            <BookCard
              v-for="book in commerceState[group.key].items"
              :key="book.id"
              :book="book"
              show-wishlist
              :is-favorite="wishlistStore.isFavorite(book.id)"
              @quick-view="openQuickView"
              @add-to-cart="addToCart"
              @buy-now="buyNow"
              @toggle-wishlist="toggleWishlist"
            />
          </div>
        </section>
      </div>
    </section>

    <!-- ═══ NEWS AT THE END OF THE FEED ═══ -->
    <section class="mx-auto w-full max-w-[1280px] px-4 py-8 md:px-gutter md:py-xl" aria-labelledby="vendor-feed-title">
      <div class="mb-6 flex items-end justify-between gap-4"><div><p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary">Từ cộng đồng xuất bản</p><h2 id="vendor-feed-title" class="mt-2 text-2xl font-bold tracking-tight text-primary md:text-3xl">Tin tức mới nhất</h2></div><router-link to="/blog" class="inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-bold text-primary no-underline">Xem tất cả <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span></router-link></div>
      <div v-if="loadingVendorFeed" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3" role="status" aria-label="Đang tải bản tin"><div v-for="i in 3" :key="i" class="overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-lowest"><Skeleton height="180px" borderRadius="0" /><div class="space-y-3 p-5"><Skeleton height="16px" width="40%" /><Skeleton height="24px" /><Skeleton height="14px" /></div></div></div>
      <div v-else-if="vendorFeedError" class="ui-state-panel" role="alert"><p class="font-bold">Chưa thể tải tin tức</p><button type="button" class="ui-button ui-button-secondary mt-4" @click="fetchEditorialFeed">Thử lại</button></div>
      <div v-else-if="vendorArticles.length === 0" class="ui-state-panel"><p class="font-bold">Chưa có bản tin đã xuất bản</p></div>
      <div v-else class="grid gap-5 md:grid-cols-2 lg:grid-cols-3"><article v-for="article in vendorArticles" :key="article.id" class="overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-lowest shadow-sm"><div class="flex aspect-[16/9] items-center justify-center bg-primary-container"><img v-if="article.cover_image" :src="getCoverUrl(article.cover_image)" :alt="article.title" class="h-full w-full object-cover" loading="lazy" /><span v-else class="material-symbols-outlined text-5xl text-on-primary-container" aria-hidden="true">auto_stories</span></div><div class="p-5"><div class="flex flex-wrap items-center gap-2 text-sm text-on-surface-variant"><span class="font-bold text-primary">{{ articlePublisher(article) }}</span><span aria-hidden="true">•</span><span>{{ article.category?.name || 'Bản tin' }}</span></div><h3 class="mt-3 text-xl font-bold leading-snug text-on-surface">{{ article.title }}</h3><p class="mt-3 line-clamp-3 text-sm leading-6 text-on-surface-variant">{{ article.excerpt }}</p><router-link :to="`/blog/${article.slug}`" class="mt-5 inline-flex min-h-11 items-center gap-1 font-bold text-primary no-underline">Đọc bài viết <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span></router-link></div></article></div>
    </section>

    <!-- ═══ TOP SELLING SECTION ═══ -->
    <section v-if="false" class="w-full px-gutter max-w-[1280px] mx-auto py-xl">
      <div class="flex justify-between items-end mb-lg">
        <router-link to="/catalog" class="font-inter text-3xl font-semibold text-primary tracking-tight hover:text-[#00b14f] transition-all duration-200 no-underline uppercase">
          SÁCH BÁN CHẠY
        </router-link>
        <router-link to="/catalog" class="text-sm font-medium text-secondary hover:underline flex items-center gap-1 no-underline">
          Xem tất cả <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </router-link>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="loadingTopSelling" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-gutter">
        <div v-for="i in 5" :key="i" class="bg-surface-container-lowest rounded-lg overflow-hidden border border-outline-variant/30">
          <Skeleton height="220px" borderRadius="0" />
          <div class="p-md flex flex-col gap-2"><Skeleton height="14px" width="80%" /><Skeleton height="12px" width="60%" /><Skeleton height="16px" width="40%" /></div>
        </div>
      </div>

      <!-- Book Grid -->
      <div v-else-if="topSellingBooks.length > 0" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-gutter">
        <div
          v-for="book in topSellingBooks"
          :key="book.id"
          class="group bg-surface-container-lowest rounded-lg soft-shadow overflow-hidden cursor-pointer border border-outline-variant/30 flex flex-col h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-card-hover"
          @click="goToDetail(book.slug)"
        >
          <!-- Cover -->
          <div class="relative pt-[140%] bg-surface-variant/30">
            <img v-if="book.cover_image && !brokenCoverIds.includes(book.id)" :src="getCoverUrl(book.cover_image)" :alt="book.display_title || book.title" class="absolute inset-0 w-full h-full object-cover p-2 rounded-t-lg transition-transform duration-500 group-hover:scale-105" loading="lazy" @error="markCoverBroken(book.id)" />
            <div v-else class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-outline text-4xl">image</span></div>
            <span v-if="book.type === 'ebook'" class="absolute bottom-2 left-2 bg-primary/90 text-on-primary text-[10px] font-bold px-2 py-1 rounded-md shadow-sm z-10">
              Ebook · Phiên bản {{ book.latest_ebook_version?.version || 1 }}
            </span>
            
            <!-- Wishlist Button -->
            <button 
              @click.stop="toggleWishlist(book.id)"
              class="absolute top-2.5 right-2.5 flex items-center justify-center transition-all hover:scale-120 active:scale-90 z-10 bg-transparent border-none cursor-pointer p-0"
              :class="wishlistStore.isFavorite(book.id) ? 'text-error' : 'text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.6)] hover:text-error'"
            >
              <span class="material-symbols-outlined text-[22px]" :class="wishlistStore.isFavorite(book.id) ? 'fill-1' : ''">favorite</span>
            </button>
            <!-- Sold Badge -->
            <div v-if="book.total_sold" class="absolute bottom-2 left-2 right-2 bg-inverse-surface/70 backdrop-blur-sm rounded-md py-1 px-2 text-center z-10">
              <span class="text-inverse-on-surface text-[11px] font-medium">Đã bán: {{ book.total_sold }}</span>
            </div>

            <!-- Cover Hover Buttons (Quick View, Add to Cart, Buy Now) -->
            <div class="absolute bottom-3 right-3 flex flex-col gap-2 z-20 opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
              <!-- Xem nhanh -->
              <button
                type="button"
                @click.stop="openQuickView(book)"
                title="Xem nhanh"
                class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
              >
                <span class="material-symbols-outlined text-[20px]">visibility</span>
              </button>
              <!-- Thêm vào giỏ hàng -->
              <button
                type="button"
                @click.stop="addToCart(book)"
                title="Thêm vào giỏ hàng"
                class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
              >
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
              </button>
              <!-- Mua ngay -->
              <button
                type="button"
                @click.stop="buyNow(book)"
                title="Mua ngay"
                class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
              >
                <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
              </button>
            </div>
          </div>
          <!-- Info -->
          <div class="p-md flex flex-col justify-between flex-grow">
            <h3 class="text-center text-sm font-medium text-on-surface line-clamp-2 leading-snug mb-1 group-hover:text-primary transition-colors">{{ book.display_title || book.title }}</h3>
            <p v-if="book.type === 'ebook'" class="text-center text-[11px] font-bold text-primary mb-1">Phiên bản {{ book.latest_ebook_version?.version || 1 }}</p>
            <div class="text-center text-sm font-bold text-[#00b14f] flex items-center justify-center gap-1.5 mt-auto">
              <span>{{ formatCurrency(book.sale_price || book.price) }}</span>
              <span v-if="book.sale_price && book.price > book.sale_price" class="text-xs text-outline line-through font-normal">{{ formatCurrency(book.price) }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ SÁCH MỚI NHẤT ═══ -->
    <section v-if="false" class="w-full px-gutter max-w-[1280px] mx-auto py-xl">
      <div class="flex justify-between items-end mb-lg">
        <router-link to="/catalog" class="font-inter text-3xl font-semibold text-primary tracking-tight hover:text-[#00b14f] transition-all duration-200 no-underline uppercase">
          SÁCH MỚI NHẤT
        </router-link>
        <router-link to="/catalog" class="text-sm font-medium text-secondary hover:underline flex items-center gap-1 no-underline">
          Xem tất cả <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </router-link>
      </div>

      <!-- Loading State -->
      <div v-if="loadingBooks" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-gutter">
        <div v-for="i in 10" :key="i" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
          <Skeleton height="220px" borderRadius="0" />
          <div class="p-md flex flex-col gap-2"><Skeleton height="14px" width="85%" /><Skeleton height="12px" width="60%" /><Skeleton height="16px" width="40%" /></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="books.length === 0" class="flex flex-col items-center justify-center py-xxl text-center bg-surface-container-lowest rounded-xl border border-outline-variant/20">
        <div class="w-20 h-20 rounded-full bg-surface-container flex items-center justify-center mb-lg">
          <span class="material-symbols-outlined text-outline text-4xl">search_off</span>
        </div>
        <p class="text-lg font-medium text-on-surface mb-1">Chưa có sách nào</p>
        <p class="text-sm text-outline">Hãy quay lại sau nhé.</p>
      </div>

      <!-- Book Grid -->
      <div v-else>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-gutter">
          <div
            v-for="book in books"
            :key="book.id"
            class="group bg-surface-container-lowest rounded-lg soft-shadow overflow-hidden cursor-pointer border border-outline-variant/30 flex flex-col h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-card-hover"
            @click="goToDetail(book.slug)"
          >
            <!-- Cover -->
            <div class="relative pt-[140%] bg-surface-variant/30">
              <img v-if="book.cover_image && !brokenCoverIds.includes(book.id)" :src="getCoverUrl(book.cover_image)" :alt="book.display_title || book.title" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" @error="markCoverBroken(book.id)" />
              <div v-else class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-outline text-4xl">image</span></div>
              <span v-if="book.type === 'ebook'" class="absolute bottom-2 left-2 bg-primary/90 text-on-primary text-[10px] font-bold px-2 py-1 rounded-md shadow-sm z-10">
                Ebook · Phiên bản {{ book.latest_ebook_version?.version || 1 }}
              </span>
              
              <!-- Wishlist Button -->
              <button 
                @click.stop="toggleWishlist(book.id)"
                class="absolute top-2.5 right-2.5 flex items-center justify-center transition-all hover:scale-120 active:scale-90 z-10 bg-transparent border-none cursor-pointer p-0"
                :class="wishlistStore.isFavorite(book.id) ? 'text-error' : 'text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.6)] hover:text-error'"
              >
                <span class="material-symbols-outlined text-[22px]" :class="wishlistStore.isFavorite(book.id) ? 'fill-1' : ''">favorite</span>
              </button>
              <!-- Sale Badge -->
              <span
                v-if="book.sale_price && book.price > book.sale_price"
                class="absolute top-2 left-2 bg-secondary text-on-secondary text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm z-10"
              >-{{ Math.round((1 - book.sale_price / book.price) * 100) }}%</span>

              <!-- Cover Hover Buttons (Quick View, Add to Cart, Buy Now) -->
              <div class="absolute bottom-3 right-3 flex flex-col gap-2 z-20 opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                <!-- Xem nhanh -->
                <button
                  type="button"
                  @click.stop="openQuickView(book)"
                  title="Xem nhanh"
                  class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
                >
                  <span class="material-symbols-outlined text-[20px]">visibility</span>
                </button>
                <!-- Thêm vào giỏ hàng -->
                <button
                  type="button"
                  @click.stop="addToCart(book)"
                  title="Thêm vào giỏ hàng"
                  class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
                >
                  <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                </button>
                <!-- Mua ngay -->
                <button
                  type="button"
                  @click.stop="buyNow(book)"
                  title="Mua ngay"
                  class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
                >
                  <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
                </button>
              </div>
            </div>
            <!-- Info -->
            <div class="p-md flex flex-col justify-between flex-grow">
              <h3 class="text-center text-sm font-medium text-on-surface line-clamp-2 leading-snug mb-1 group-hover:text-primary transition-colors">{{ book.display_title || book.title }}</h3>
              <p v-if="book.type === 'ebook'" class="text-center text-[11px] font-bold text-primary mb-1">Phiên bản {{ book.latest_ebook_version?.version || 1 }}</p>
              <div class="text-center text-sm font-bold text-[#00b14f] flex items-center justify-center gap-1.5 mt-auto">
                <span>{{ formatCurrency(book.sale_price || book.price) }}</span>
                <span v-if="book.sale_price && book.price > book.sale_price" class="text-xs text-outline line-through font-normal">{{ formatCurrency(book.price) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ QUICK VIEW DIALOG ═══ -->
    <Dialog 
      v-model:visible="quickViewVisible" 
      :modal="true" 
      :show-header="false"
      class="!max-w-4xl !w-[95vw] !rounded-xl !border-2 !border-[#00b14f] !bg-white !shadow-2xl overflow-hidden relative"
      contentClass="!p-0"
    >
      <div v-if="quickViewBook" class="flex flex-col md:flex-row min-h-[500px]">
        <!-- Close Button -->
        <button 
          @click="quickViewVisible = false"
          class="absolute top-3 right-3 w-8 h-8 rounded-full border border-outline-variant/60 bg-white flex items-center justify-center cursor-pointer hover:border-[#00b14f] hover:scale-105 active:scale-95 transition-all z-50 text-xl font-bold text-gray-500"
          type="button"
        >
          <span class="material-symbols-outlined text-[20px] text-gray-700">close</span>
        </button>

        <!-- Left Column: Image and slider indicator -->
        <div class="w-full md:w-1/2 p-6 bg-surface-variant/10 flex flex-col items-center justify-center border-r border-outline-variant/30 relative">
          <!-- Sale Badge on image top-right -->
          <div class="relative w-full max-w-[280px] pt-[140%] shadow-lg rounded-lg overflow-hidden bg-white">
            <img 
              v-if="quickViewBook.cover_image && !brokenCoverIds.includes(quickViewBook.id)"
              :src="getCoverUrl(quickViewBook.cover_image)" 
              :alt="quickViewBook.display_title || quickViewBook.title"
              class="absolute inset-0 w-full h-full object-cover p-2" 
              @error="markCoverBroken(quickViewBook.id)"
            />
            <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 text-center text-outline">
              <span class="material-symbols-outlined text-outline text-5xl" aria-hidden="true">image_not_supported</span>
              <span class="text-xs font-semibold">Ảnh đang cập nhật</span>
            </div>
            
            <!-- Sale Percent Badge -->
            <span
              v-if="quickViewBook.sale_price && quickViewBook.price > quickViewBook.sale_price"
              class="absolute top-3 right-3 bg-secondary text-on-secondary text-xs font-black px-2.5 py-1 rounded-md shadow-md z-10"
            >
              -{{ Math.round((1 - quickViewBook.sale_price / quickViewBook.price) * 100) }}%
            </span>
          </div>
        </div>

        <!-- Right Column: Info & Details -->
        <div class="w-full md:w-1/2 p-6 flex flex-col justify-between">
          <div>
            <!-- Book Title -->
            <h2 class="text-xl md:text-2xl font-bold text-on-surface mb-2 leading-tight pr-6">{{ quickViewBook.title }}</h2>
            
            <!-- SKU -->
            <div class="text-xs text-outline mb-4">SKU: 978632{{ String(quickViewBook.id).padStart(7, '0') }}</div>

            <!-- Price -->
            <div class="flex items-center gap-3 mb-5">
              <span class="text-2xl font-extrabold text-[#00b14f]">{{ formatCurrency(quickViewBook.sale_price || quickViewBook.price) }}</span>
              <span v-if="quickViewBook.sale_price && quickViewBook.price > quickViewBook.sale_price" class="text-sm text-outline line-through">{{ formatCurrency(quickViewBook.price) }}</span>
            </div>

            <!-- Metadata table grid -->
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 border-t border-b border-outline-variant/40 py-4 mb-5 text-xs text-on-surface-variant">
              <div><strong>Tác giả:</strong> <span class="text-on-surface ml-1">{{ quickViewBook.author || 'Đang cập nhật' }}</span></div>
              <div><strong>Dịch giả:</strong> <span class="text-on-surface ml-1">Đang cập nhật</span></div>
              <div><strong>Nhà xuất bản:</strong> <span class="text-on-surface ml-1">{{ quickViewBook.vendor?.name || 'Đang cập nhật' }}</span></div>
              <div><strong>Năm xuất bản:</strong> <span class="text-on-surface ml-1">{{ quickViewBook.publish_year || '2026' }}</span></div>
              <div><strong>Hình thức:</strong> <span class="text-on-surface ml-1">Bìa mềm</span></div>
              <div><strong>Kích thước:</strong> <span class="text-on-surface ml-1">13 x 18 cm</span></div>
            </div>

            <!-- Description -->
            <div class="mb-5">
              <div class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1.5">Nội dung:</div>
              <p class="text-xs text-on-surface-variant leading-relaxed line-clamp-3">
                {{ cleanDescriptionText(quickViewBook.description) || 'Chưa có mô tả chi tiết cho cuốn sách này.' }}
              </p>
            </div>



            <!-- Quantity Selector & Actions -->
            <div class="flex items-center gap-3 mt-5">
              <div class="flex items-center border border-outline-variant/60 rounded-xl h-10 overflow-hidden bg-surface-container-lowest">
                <button 
                  type="button"
                  @click="decrementQty"
                  class="w-8 h-full flex items-center justify-center hover:bg-surface-variant transition-colors text-sm font-bold border-none"
                >-</button>
                <input 
                  type="number" 
                  v-model.number="quickViewQty" 
                  min="1" 
                  class="w-10 h-full text-center text-xs font-bold bg-transparent border-none focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                />
                <button 
                  type="button"
                  @click="incrementQty"
                  class="w-8 h-full flex items-center justify-center hover:bg-surface-variant transition-colors text-sm font-bold border-none"
                >+</button>
              </div>

              <!-- Xem Thêm -->
              <button 
                type="button"
                @click="goToDetail(quickViewBook.slug); quickViewVisible = false"
                class="flex-grow h-10 bg-[#00b14f] text-white rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-[#009e46] transition-all cursor-pointer border-none shadow-sm flex items-center justify-center"
              >
                Xem thêm
              </button>

              <!-- Thêm vào giỏ -->
              <button 
                type="button"
                @click="quickViewAddToCart"
                class="flex-grow h-10 bg-[#00b14f] text-white rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-[#009e46] transition-all cursor-pointer border-none shadow-sm flex items-center justify-center"
              >
                Thêm vào giỏ
              </button>
            </div>
          </div>

          <!-- Bottom Categories / Tags metadata -->
          <div class="mt-6 pt-4 border-t border-outline-variant/20 text-[10px] text-outline uppercase tracking-wider flex flex-col gap-1">
            <div><strong>Danh mục:</strong> {{ quickViewBook.category?.name || 'Sách mới' }}</div>
            <div><strong>Tags:</strong> {{ getBookTags(quickViewBook).join(', ') }}</div>
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, ref, onBeforeUnmount, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'
import { readApiList } from '@/services/apiContract'
import { useCartStore } from '@/stores/cart'
import { useToast } from 'primevue/usetoast'
import Skeleton from 'primevue/skeleton'
import Dialog from 'primevue/dialog'
import { useWishlistStore } from '@/stores/wishlist'
import BookCard from '@/components/BookCard.vue'
import bookshelfHero from '@/assets/komibook-bookshelf-hero.webp'

const router = useRouter()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const toast = useToast()
const brokenCoverIds = ref([])

const heroArticles = ref([])
const heroError = ref(false)
const currentHeroIndex = ref(0)
const fallbackHero = {
  key: 'komibook-intro',
  eyebrow: 'KomiBook · Không gian đọc dành cho bạn',
  title: 'Khám phá thế giới sách',
  excerpt: 'Tìm ebook, sách giấy và những tác phẩm được tuyển chọn trong một trải nghiệm đọc rõ ràng, yên tĩnh.',
  cover_image: bookshelfHero,
  to: '/catalog',
  cta: 'Bắt đầu khám phá',
}
const heroSlides = computed(() => {
  if (heroArticles.value.length === 0) return [fallbackHero]

  return heroArticles.value.map((article) => ({
    key: `article-${article.id}`,
    eyebrow: article.category?.name || 'Bản tin KomiBook',
    title: article.title,
    excerpt: article.excerpt || 'Khám phá nội dung mới từ cộng đồng xuất bản KomiBook.',
    cover_image: article.cover_image,
    to: `/blog/${article.slug}`,
    cta: 'Đọc bài viết',
  }))
})
const activeHero = computed(() => heroSlides.value[currentHeroIndex.value] || heroSlides.value[0])

const recommendations = ref([])
const loadingRecommendations = ref(true)
const recommendationError = ref(false)
const recommendationExplanation = ref('Đang chuẩn bị gợi ý phù hợp…')
const vendorArticles = ref([])
const loadingVendorFeed = ref(true)
const vendorFeedError = ref(false)
const commerceGroups = [
  {
    key: 'bestselling-ebook',
    title: 'Ebook bán chạy',
    subtitle: 'Nội dung số được độc giả lựa chọn nhiều.',
    params: { type: 'ebook', sort: 'popular', per_page: 5 },
    to: { name: 'catalog', query: { type: 'ebook', sort: 'popular' } },
  },
  {
    key: 'bestselling-physical',
    title: 'Sách vật lý bán chạy',
    subtitle: 'Những đầu sách giấy nổi bật trên toàn sàn.',
    params: { type: 'physical', sort: 'popular', per_page: 5 },
    to: { name: 'catalog', query: { type: 'physical', sort: 'popular' } },
  },
  {
    key: 'newest-ebook',
    title: 'Ebook mới nhất',
    subtitle: 'Phiên bản số vừa được phát hành.',
    params: { type: 'ebook', per_page: 5 },
    to: { name: 'catalog', query: { type: 'ebook' } },
  },
  {
    key: 'newest-physical',
    title: 'Sách vật lý mới nhất',
    subtitle: 'Các ấn phẩm giấy mới lên kệ.',
    params: { type: 'physical', per_page: 5 },
    to: { name: 'catalog', query: { type: 'physical' } },
  },
  {
    key: 'used-books',
    title: 'Sách cũ giá tốt',
    subtitle: 'Sách vật lý đã qua sử dụng, được mô tả tình trạng rõ ràng.',
    params: { type: 'physical', provenance: 'used_resale', sort: 'price_asc', per_page: 5 },
    to: { name: 'catalog', query: { provenance: 'used_resale' } },
  },
]
const commerceState = ref(Object.fromEntries(
  commerceGroups.map((group) => [group.key, { items: [], loading: true, error: false }]),
))

// ─── State ──────────────────────────────────────────────────────────
const activeFlashSale = ref(null)
const upcomingFlashSale = ref(null)
const countdown = ref({ hours: '00', minutes: '00', seconds: '00' })
const upcomingCountdown = ref({ days: '00', hours: '00', minutes: '00', seconds: '00' })
let countdownInterval = null
let upcomingCountdownInterval = null

const books = ref([])
const loadingBooks = ref(false)

const topSellingBooks = ref([])
const loadingTopSelling = ref(false)

// Quick View State
const quickViewVisible = ref(false)
const quickViewBook = ref(null)
const quickViewVersion = ref('standard')
const quickViewQty = ref(1)

// ─── Fetch API ──────────────────────────────────────────────────────
const fetchEditorialFeed = async () => {
  heroError.value = false
  loadingVendorFeed.value = true
  vendorFeedError.value = false
  try {
    const response = await apiClient.get('/api/articles', {
      params: { per_page: 8 },
    })
    const articles = response.data?.data?.data || []
    heroArticles.value = articles.filter((article) => article.home_featured).slice(0, 5)
    vendorArticles.value = articles.slice(0, 6)
    currentHeroIndex.value = 0
  } catch {
    heroError.value = true
    vendorFeedError.value = true
    heroArticles.value = []
    vendorArticles.value = []
  } finally {
    loadingVendorFeed.value = false
  }
}

const fetchRecommendations = async () => {
  loadingRecommendations.value = true
  recommendationError.value = false
  try {
    const response = await apiClient.get('/api/books/recommendations')
    recommendations.value = readApiList(response.data).slice(0, 5)
    recommendationExplanation.value = response.data?.recommendation?.explanation || 'Sách nổi bật trên KomiBook'
  } catch {
    recommendationError.value = true
    recommendations.value = []
    recommendationExplanation.value = 'Gợi ý được tải độc lập với các nội dung khác.'
  } finally {
    loadingRecommendations.value = false
  }
}

const previousHero = () => {
  currentHeroIndex.value = (currentHeroIndex.value - 1 + heroSlides.value.length) % heroSlides.value.length
}

const nextHero = () => {
  currentHeroIndex.value = (currentHeroIndex.value + 1) % heroSlides.value.length
}

const articlePublisher = (article) => {
  return article.creator?.vendor?.shop_name
    || article.creator?.name
    || 'KomiBook'
}

const fetchCommerceGroup = async (group) => {
  const state = commerceState.value[group.key]
  state.loading = true
  state.error = false
  try {
    const response = await apiClient.get('/api/books', { params: group.params })
    state.items = readApiList(response.data).slice(0, 5)
  } catch {
    state.items = []
    state.error = true
  } finally {
    state.loading = false
  }
}

const fetchCommerceFeed = async () => {
  commerceGroups.forEach((group) => {
    commerceState.value[group.key].loading = true
    commerceState.value[group.key].error = false
  })

  try {
    const response = await apiClient.get('/api/books', {
      params: { sort: 'popular', per_page: 60 },
    })
    const allBooks = readApiList(response.data)
    const byNewest = (items) => [...items].sort((left, right) => (
      new Date(right.published_at || right.created_at || 0) - new Date(left.published_at || left.created_at || 0)
    ))
    const ebooks = allBooks.filter((book) => book.type === 'ebook')
    const physicalBooks = allBooks.filter((book) => book.type === 'physical')

    commerceState.value['bestselling-ebook'].items = ebooks.slice(0, 5)
    commerceState.value['bestselling-physical'].items = physicalBooks.slice(0, 5)
    commerceState.value['newest-ebook'].items = byNewest(ebooks).slice(0, 5)
    commerceState.value['newest-physical'].items = byNewest(physicalBooks).slice(0, 5)
    commerceState.value['used-books'].items = physicalBooks.filter((book) => (
      book.provenance === 'used_resale' || book.product_origin === 'used_resale'
    )).slice(0, 5)
  } catch {
    commerceGroups.forEach((group) => {
      commerceState.value[group.key].items = []
      commerceState.value[group.key].error = true
    })
  } finally {
    commerceGroups.forEach((group) => {
      commerceState.value[group.key].loading = false
    })
  }
}

const fetchFlashSales = async () => {
  try {
    const res = await apiClient.get('/api/flash-sales')
    const flashSales = readApiList(res.data)
    const now = new Date()
    const active = flashSales.find(fs => {
      const start = new Date(fs.start_time)
      const end = new Date(fs.end_time)
      return fs.status === 'active' && start <= now && end > now
    })
    if (active) {
      activeFlashSale.value = active
      startCountdown(new Date(active.end_time))
    }
    const upcoming = flashSales.find((fs) => fs.status === 'enrollment_open' && new Date(fs.start_time) > now)
    upcomingFlashSale.value = upcoming || null
    if (upcoming) startUpcomingCountdown(new Date(upcoming.start_time))
  } catch (e) {
    console.error('Failed to fetch flash sales', e)
  }
}

const startUpcomingCountdown = (startTime) => {
  if (upcomingCountdownInterval) clearInterval(upcomingCountdownInterval)
  const update = () => {
    const diff = startTime - new Date()
    if (diff <= 0) {
      clearInterval(upcomingCountdownInterval)
      upcomingFlashSale.value = null
      fetchFlashSales()
      return
    }
    upcomingCountdown.value = {
      days: String(Math.floor(diff / 86400000)).padStart(2, '0'),
      hours: String(Math.floor((diff % 86400000) / 3600000)).padStart(2, '0'),
      minutes: String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0'),
      seconds: String(Math.floor((diff % 60000) / 1000)).padStart(2, '0'),
    }
  }
  update()
  upcomingCountdownInterval = setInterval(update, 1000)
}

const formatFlashTime = (value) => new Intl.DateTimeFormat('vi-VN', {
  hour: '2-digit',
  minute: '2-digit',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
}).format(new Date(value))

const startCountdown = (endTime) => {
  if (countdownInterval) clearInterval(countdownInterval)
  const update = () => {
    const now = new Date()
    const diff = endTime - now
    if (diff <= 0) {
      clearInterval(countdownInterval)
      activeFlashSale.value = null
      return
    }
    const hours = Math.floor(diff / (1000 * 60 * 60))
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
    const seconds = Math.floor((diff % (1000 * 60)) / 1000)
    countdown.value = {
      hours: String(hours).padStart(2, '0'),
      minutes: String(minutes).padStart(2, '0'),
      seconds: String(seconds).padStart(2, '0')
    }
  }
  update()
  countdownInterval = setInterval(update, 1000)
}

// ─── User Actions ───────────────────────────────────────────────────
const cleanDescriptionText = (html) => {
  if (!html) return ''
  let text = html.replace(/<[^>]*>/g, '') // Xóa toàn bộ thẻ HTML
  return text.replace(/&nbsp;/g, ' ').replace(/\u00a0/g, ' ') // Thay thế khoảng trắng không ngắt
}

const getBookTags = (book) => {
  if (!book) return []
  const tags = []
  
  if (book.author && book.author !== 'Đang cập nhật' && book.author !== 'Nhiều Tác Giả') {
    tags.push(book.author)
  }
  if (book.category?.name) {
    tags.push(book.category.name)
  } else if (book.categories && book.categories.length > 0) {
    tags.push(book.categories[0].name)
  }
  if (book.type === 'ebook') {
    tags.push('E-book')
    tags.push(`Phiên bản ${book.latest_ebook_version?.version || 1}`)
  } else {
    tags.push(book.cover_format || 'Sách giấy')
  }
  if (book.vendor?.name) {
    tags.push(book.vendor.name)
  }
  if (book.sale_price && book.price > book.sale_price) {
    tags.push('Khuyến mãi')
  }
  const releaseYear = parseInt(book.release_date)
  const currentYear = new Date().getFullYear()
  if (releaseYear && releaseYear >= currentYear - 1) {
    tags.push('Sách mới')
  }
  return [...new Set(tags)]
}

const goToDetail = (slug) => {
  router.push({ name: 'book-detail', params: { slug } })
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const addToCart = (book) => {
  cartStore.addToCart(book, 1)
  toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm vào giỏ hàng!', life: 3000 })
}

const buyNow = (book) => {
  cartStore.addToCart(book, 1)
  router.push('/cart')
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  if (path.startsWith('/')) return path
  return `/storage/${path}`
}

const markCoverBroken = (bookId) => {
  if (!brokenCoverIds.value.includes(bookId)) brokenCoverIds.value.push(bookId)
}

const toggleWishlist = async (bookId) => {
  try {
    const res = await wishlistStore.toggleWishlist(bookId)
    if (res.state === 'added') {
      toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Đã thêm vào danh sách yêu thích', life: 2000 })
    } else if (res.state === 'removed') {
      toast.add({ severity: 'info', summary: 'Đã bỏ', detail: 'Đã xóa khỏi danh sách yêu thích', life: 2000 })
    } else if (res.state === 'unauthorized') {
      toast.add({ severity: 'warn', summary: 'Thông báo', detail: 'Vui lòng đăng nhập để lưu yêu thích', life: 3000 })
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  }
}

// Quick View Actions
const openQuickView = (book) => {
  quickViewBook.value = book
  quickViewQty.value = 1
  quickViewVersion.value = 'standard'
  quickViewVisible.value = true
}

const decrementQty = () => {
  if (quickViewQty.value > 1) {
    quickViewQty.value--
  }
}

const incrementQty = () => {
  quickViewQty.value++
}

const quickViewAddToCart = () => {
  if (!quickViewBook.value) return
  cartStore.addToCart(quickViewBook.value, quickViewQty.value)
  toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã thêm ${quickViewQty.value} cuốn vào giỏ hàng!`, life: 3000 })
  quickViewVisible.value = false
}

// ─── Init ───────────────────────────────────────────────────────────
onMounted(() => {
  fetchEditorialFeed()
  fetchRecommendations()
  fetchCommerceFeed()
  fetchFlashSales()
  wishlistStore.fetchWishlistIds()
})

onBeforeUnmount(() => {
  if (countdownInterval) clearInterval(countdownInterval)
  if (upcomingCountdownInterval) clearInterval(upcomingCountdownInterval)
})
</script>

<style scoped>
/* ═══ FLASH SALE BANNER ═══ */
.flash-sale-banner {
  background: linear-gradient(90deg, #ba0035 0%, #e21e49 100%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: relative;
  z-index: 100;
}
.flash-icon-box {
  width: 32px; height: 32px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: white;
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.8; }
  100% { transform: scale(1); opacity: 1; }
}
.code-badge {
  background: white; color: #ba0035;
  padding: 2px 8px; border-radius: 4px;
  font-weight: 800; font-family: 'JetBrains Mono', monospace;
  margin: 0 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.timer-box {
  background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 8px; padding: 4px 8px; min-width: 44px;
  display: flex; flex-direction: column; align-items: center;
  backdrop-filter: blur(4px);
}
.timer-num { color: white; font-weight: 800; font-size: 15px; font-family: 'JetBrains Mono', monospace; line-height: 1; }
.timer-unit { color: rgba(255, 255, 255, 0.7); font-size: 8px; text-transform: uppercase; font-weight: 700; margin-top: 2px; }

/* ═══ Animations ═══ */
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); opacity: 0; }

.recommendation-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.recommendation-item:nth-child(n + 3) { display: none; }

@media (min-width: 640px) {
  .recommendation-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .recommendation-item:nth-child(3) { display: block; }
}

@media (min-width: 768px) {
  .recommendation-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .recommendation-item:nth-child(4) { display: block; }
}

@media (min-width: 1024px) {
  .recommendation-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
  .recommendation-item:nth-child(5) { display: block; }
}
</style>
