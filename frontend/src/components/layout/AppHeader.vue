<template>
  <!-- Header Container (Smart Autohide Header) -->
  <header
    class="sticky top-0 z-50 bg-white shadow-xs transition-transform duration-300 ease-in-out"
    :class="showHeader ? 'translate-y-0' : '-translate-y-full'"
  >
    <!-- 1. TOP BAR (Greeting only) -->
    <div class="bg-slate-100/80 text-slate-600 text-xs font-semibold py-1.5 px-4 md:px-gutter border-b border-slate-200/60">
      <div class="mx-auto flex w-full max-w-[1280px] items-center justify-end">
        <div class="flex items-center text-xs shrink-0 font-bold text-slate-700">
          <template v-if="!authStore.isAuthenticated">
            <span>Xin chào, Độc giả</span>
            <span class="mx-2 opacity-40">|</span>
            <router-link to="/register" class="inline-flex min-h-11 items-center text-slate-700 hover:text-commerce no-underline">Đăng ký</router-link>
            <span class="mx-2 opacity-40">|</span>
            <router-link to="/login" class="inline-flex min-h-11 items-center text-slate-700 hover:text-commerce no-underline">Đăng nhập</router-link>
          </template>
          <template v-else>
            <span>Xin chào, {{ authStore.user?.name }}</span>
          </template>
        </div>
      </div>
    </div>

    <!-- 2. MIDDLE BRANDING & SEARCH & CART BAR -->
    <div class="mx-auto flex w-full max-w-[1280px] items-center justify-between px-4 py-3 md:px-gutter md:py-4 gap-4 bg-white">
      <!-- Brand Logo (Clean Standalone Image - NO Border, Enriched Size) -->
      <router-link to="/" aria-label="KomiBook" class="flex items-center gap-4 no-underline shrink-0 group">
        <div class="h-16 w-16 md:h-22 md:w-22 flex items-center justify-center transition-transform group-hover:scale-105 shrink-0">
          <img v-if="logoExists" src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-contain" />
          <div v-else class="flex h-full w-full items-center justify-center rounded-2xl bg-commerce">
            <span class="material-symbols-outlined text-on-commerce text-4xl">auto_stories</span>
          </div>
        </div>
        <div class="hidden sm:flex flex-col justify-center">
          <span class="font-inter text-3xl md:text-4xl font-black text-primary tracking-tight uppercase leading-none">KOMIBOOK</span>
          <span class="mt-1.5 font-inter text-[10px] font-extrabold uppercase leading-tight tracking-[0.24em] text-primary md:text-[13px]">YOUR PERSONAL LIBRARY</span>
        </div>
      </router-link>

      <!-- Search Bar (Pill Shape with Green Button) -->
      <div class="relative flex-1 max-w-xl mx-2 lg:mx-6 hidden lg:flex items-center">
        <div class="flex w-full items-center overflow-hidden rounded-full border-2 border-commerce bg-white shadow-xs transition-shadow focus-within:ring-2 focus-within:ring-commerce/30">
          <input
            v-model="searchQuery"
            aria-label="Tìm kiếm sách"
            autocomplete="off"
            class="flex-1 px-5 py-2.5 text-sm font-medium text-slate-800 placeholder:text-slate-400 bg-transparent border-none focus:outline-none"
            placeholder="Tìm kiếm sách, tác giả, nhà xuất bản..."
            type="text"
            @keyup.enter="doSearch"
          />
          <button
            type="button"
            class="flex min-h-11 min-w-11 items-center justify-center bg-primary px-6 py-2.5 text-on-primary transition-colors hover:bg-primary-container cursor-pointer border-none"
            @click="doSearch"
            aria-label="Tìm kiếm"
          >
            <span class="material-symbols-outlined text-xl">search</span>
          </button>
        </div>
      </div>

      <!-- Actions Right: Cart + Mobile Menu Button + User Menu Dropdown -->
      <div class="flex items-center gap-3 lg:gap-5 shrink-0">
        <!-- Cart Widget -->
        <button
          type="button"
          class="group flex min-h-11 min-w-11 items-center gap-3 cursor-pointer border-none bg-transparent p-1"
          @click="$router.push('/cart')"
          aria-label="Giỏ hàng"
        >
          <div class="relative flex items-center justify-center">
            <span class="material-symbols-outlined text-3xl md:text-4xl text-commerce transition-transform group-hover:scale-110">shopping_cart</span>
            <span
              v-if="cartStore.totalItems > 0"
              class="absolute -top-1 -right-2 bg-emerald-600 text-white text-[11px] font-black min-w-[20px] h-[20px] px-1 flex items-center justify-center rounded-full border-2 border-white shadow-xs"
            >
              {{ cartStore.totalItems }}
            </span>
          </div>
          <div class="hidden lg:flex flex-col text-left">
            <span class="text-sm font-bold leading-tight text-slate-900 transition-colors group-hover:text-commerce">Giỏ hàng</span>
            <span class="text-xs text-slate-500 font-medium">{{ cartStore.totalItems }} sản phẩm</span>
          </div>
        </button>

        <!-- Notification Bell (if authenticated) -->
        <button
          v-if="authStore.isAuthenticated"
          class="relative hidden h-11 w-11 cursor-pointer items-center justify-center rounded-full text-slate-700 hover:bg-slate-100 sm:flex border-none bg-transparent"
          @click="$router.push('/notifications')"
          aria-label="Thông báo"
        >
          <span class="material-symbols-outlined text-2xl text-slate-700">notifications</span>
          <span
            v-if="unreadNotificationsCount > 0"
            class="absolute -top-1 -right-1 bg-rose-600 text-white text-[10px] font-bold w-[18px] h-[18px] flex items-center justify-center rounded-full border-2 border-white"
          >
            {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
          </span>
        </button>

        <!-- User Menu Dropdown trigger (Avatar with NO border) -->
        <template v-if="authStore.isAuthenticated">
          <div ref="userMenuRef" class="relative">
            <button
              type="button"
              ref="userMenuTriggerRef"
              class="hidden h-11 w-11 cursor-pointer items-center justify-center rounded-full border-none bg-transparent transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim sm:flex"
              aria-label="Mở menu tài khoản"
              aria-controls="user-account-disclosure"
              :aria-expanded="userMenuOpen"
              @click.stop="toggleUserMenu"
            >
              <div v-if="authStore.user?.avatar" class="relative h-9 w-9 overflow-hidden rounded-full shadow-xs">
                <img :src="getAvatarUrl(authStore.user.avatar)" alt="Avatar" class="w-full h-full object-cover" />
              </div>
              <div v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-container text-on-primary-container shadow-xs">
                <span class="material-symbols-outlined text-[20px]">person</span>
              </div>
            </button>

            <!-- Custom User Dropdown Panel -->
            <Transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 -translate-y-2"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 -translate-y-2"
            >
              <div
                v-if="userMenuOpen"
                class="absolute right-0 top-full mt-2 w-72 z-[100] overflow-hidden rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-2 shadow-2xl origin-top-right"
                id="user-account-disclosure"
                @click.stop
              >
                <!-- User Profile Header Card (Avatar with NO border) -->
                <div class="mb-2 flex items-center gap-3 rounded-xl bg-surface-container-low p-3 border border-outline-variant/30">
                  <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full shadow-xs">
                    <img v-if="authStore.user?.avatar" :src="getAvatarUrl(authStore.user.avatar)" alt="Avatar" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full w-full items-center justify-center bg-primary-container text-on-primary-container">
                      <span class="material-symbols-outlined text-xl">person</span>
                    </div>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-1">
                      <h4 class="truncate text-sm font-bold text-on-surface">{{ authStore.user?.name }}</h4>
                      <span class="shrink-0 rounded-full bg-primary-container px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-on-primary-container">
                        {{ userRoleLabel }}
                      </span>
                    </div>
                    <p class="truncate text-xs text-outline mt-0.5">{{ authStore.user?.email || 'Thành viên KomiBook' }}</p>
                  </div>
                </div>

                <!-- Navigation List -->
                <div class="space-y-0.5 text-sm">
                  <router-link ref="userMenuFirstActionRef" to="/profile" class="flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-user text-base text-primary/80"></i>Thông tin cá nhân</span>
                  </router-link>

                  <router-link to="/my-library" class="flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-book text-base text-primary/80"></i>Tủ sách cá nhân</span>
                  </router-link>

                  <router-link to="/orders" class="flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-shopping-bag text-base text-primary/80"></i>Lịch sử mua hàng</span>
                  </router-link>

                  <router-link to="/wishlist" class="flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-heart text-base text-primary/80"></i>Danh sách yêu thích</span>
                  </router-link>

                  <router-link to="/notifications" class="flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-bell text-base text-primary/80"></i>Thông báo</span>
                    <span v-if="unreadNotificationsCount > 0" class="rounded-full bg-error px-2 py-0.5 text-[10px] font-bold text-on-error">{{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}</span>
                  </router-link>

                  <div class="my-1 border-t border-outline-variant/30"></div>

                  <router-link v-if="canAccessPartnerPortal" to="/organization-portal" class="flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-building text-base text-primary/80"></i>Tổ chức & phân phối</span>
                  </router-link>

                  <button type="button" class="flex min-h-11 w-full items-center justify-between rounded-xl px-3 py-2 text-left text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface cursor-pointer border-none bg-transparent" @click="openChatList">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-comments text-base text-primary/80"></i>Hộp thư hỗ trợ</span>
                  </button>

                  <router-link to="/help-center" class="flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-question-circle text-base text-primary/80"></i>Trung tâm trợ giúp</span>
                  </router-link>
                </div>

                <!-- Featured Role Switcher Box (Switch Space) -->
                <div v-if="managementTarget" class="mt-2 pt-1.5 border-t border-outline-variant/30">
                  <button
                    type="button"
                    class="group flex min-h-11 w-full items-center justify-between rounded-xl border border-commerce/30 bg-primary-container/70 p-2.5 text-left transition-[border-color,box-shadow,transform] hover:scale-[1.01] hover:border-commerce hover:shadow-md cursor-pointer"
                    @click="goToManagement"
                  >
                    <div class="flex items-center gap-2.5">
                      <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-commerce text-on-commerce shadow-xs">
                        <i :class="managementTarget.icon" class="text-sm"></i>
                      </div>
                      <div>
                        <span class="block text-xs font-bold leading-tight text-on-surface">{{ managementTarget.label }}</span>
                        <span class="mt-0.5 block text-[11px] font-medium text-outline">Chuyển không gian</span>
                      </div>
                    </div>
                    <span class="material-symbols-outlined text-base text-commerce transition-transform group-hover:translate-x-0.5">arrow_forward</span>
                  </button>
                </div>

                <!-- Log Out Button -->
                <div class="mt-1 pt-1 border-t border-outline-variant/30">
                  <button
                    type="button"
                    class="flex min-h-11 w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-error transition-colors hover:bg-error-container/60 hover:text-on-error-container cursor-pointer border-none bg-transparent"
                    @click="handleLogout"
                  >
                    <i class="pi pi-sign-out text-base"></i>
                    <span>Đăng xuất</span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </template>

        <!-- Mobile Menu Toggle Button -->
        <button
          type="button"
          ref="mobileMenuTriggerRef"
          class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl text-slate-700 hover:bg-slate-100 lg:hidden border-none bg-transparent"
          aria-label="Mở menu di động"
          :aria-expanded="mobileMenuOpen"
          aria-controls="mobile-main-menu"
          @click="toggleMobileMenu"
        >
          <span class="material-symbols-outlined text-2xl">
            {{ mobileMenuOpen ? 'close' : 'menu' }}
          </span>
        </button>
      </div>
    </div>

    <!-- 3. BOTTOM NAVIGATION BAR (Desktop - Submenu Enabled) -->
    <nav class="hidden lg:flex w-full bg-white border-t border-b border-slate-200/80 shadow-2xs relative z-40" aria-label="Điều hướng chính">
      <div class="mx-auto flex w-full max-w-[1280px] items-center justify-center gap-2 px-4 py-0">
        <router-link
          to="/"
          :class="[
            'flex min-h-11 items-center px-5 py-3 text-sm font-extrabold uppercase tracking-wider transition-colors border-b-2 no-underline whitespace-nowrap',
            $route.name === 'home'
              ? 'text-commerce border-commerce'
              : 'text-slate-700 border-transparent hover:text-commerce'
          ]"
        >
          TRANG CHỦ
        </router-link>

        <!-- Category Dropdown Submenu (Continuous Hover Bridge) -->
        <div ref="categoryMenuRef" class="relative group" @mouseleave="categoryMenuOpen = false">
          <button
            type="button"
            ref="categoryMenuTriggerRef"
            :class="[
              'flex min-h-11 items-center gap-1 px-5 py-3 text-sm font-extrabold uppercase tracking-wider transition-colors border-b-2 bg-transparent cursor-pointer whitespace-nowrap',
              $route.name === 'catalog' && $route.query.provenance !== 'used_resale'
                ? 'text-commerce border-commerce'
                : 'text-slate-700 border-transparent hover:text-commerce'
            ]"
            :aria-expanded="categoryMenuOpen"
            aria-controls="desktop-category-disclosure"
            @click="toggleCategoryMenu"
            @mouseenter="categoryMenuOpen = true"
          >
            DANH MỤC
            <span class="material-symbols-outlined text-[18px]">
              {{ categoryMenuOpen ? 'expand_less' : 'expand_more' }}
            </span>
          </button>

          <!-- Category Submenu Panel (Zero Hover Gap Wrapper) -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="transform opacity-0 scale-95 -translate-y-1"
            enter-to-class="transform opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="transform opacity-100 scale-100 translate-y-0"
            leave-to-class="transform opacity-0 scale-95 -translate-y-1"
          >
            <div
              v-if="categoryMenuOpen"
              id="desktop-category-disclosure"
              class="absolute left-0 top-full z-[100] pt-1.5 w-72"
              @mouseenter="categoryMenuOpen = true"
              @mouseleave="categoryMenuOpen = false"
            >
              <div class="rounded-2xl border border-slate-200/90 bg-white p-2 shadow-2xl">
                <p class="px-3 pb-2 pt-1 text-xs font-black uppercase text-slate-400">Thể loại sách</p>
                <router-link
                  to="/catalog"
                  ref="categoryMenuFirstActionRef"
                  class="flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-[#00b14f] hover:bg-emerald-50/70 no-underline transition-colors"
                  @click="closeNavigationMenus"
                >
                  Tất cả danh mục
                </router-link>
                <p v-if="loadingCategories" class="px-3 py-2 text-sm text-slate-400">Đang tải thể loại…</p>
                <div v-else-if="categoryLoadFailed" class="px-3 py-2 text-sm text-rose-500">
                  <p>Chưa thể tải danh sách thể loại.</p>
                  <button type="button" class="mt-2 min-h-11 rounded-lg px-3 font-semibold text-primary hover:bg-surface-container-low" @click="fetchTopCategories">Thử lại</button>
                </div>
                <router-link
                  v-for="category in topCategories"
                  :key="category.id"
                  :to="{ name: 'catalog', query: { category_id: category.id } }"
                  class="flex min-h-11 items-center justify-between rounded-xl px-3 text-sm font-semibold text-slate-700 hover:bg-emerald-50/50 hover:text-[#00b14f] no-underline transition-colors"
                  @click="closeNavigationMenus"
                >
                  <span>{{ category.name }}</span>
                  <span class="text-xs text-slate-400 font-bold bg-slate-100 px-2 py-0.5 rounded-full">{{ category.published_books_count }}</span>
                </router-link>
              </div>
            </div>
          </Transition>
        </div>

        <router-link
          :to="{ name: 'catalog', query: { provenance: 'used_resale' } }"
          :class="[
            'flex min-h-11 items-center px-5 py-3 text-sm font-extrabold uppercase tracking-wider transition-colors border-b-2 no-underline whitespace-nowrap',
            $route.name === 'catalog' && $route.query.provenance === 'used_resale'
              ? 'text-[#00b14f] border-[#00b14f]'
              : 'text-slate-700 border-transparent hover:text-[#00b14f]'
          ]"
        >
          SÁCH CỦ
        </router-link>

        <router-link
          to="/blog"
          :class="[
            'flex min-h-11 items-center px-5 py-3 text-sm font-extrabold uppercase tracking-wider transition-colors border-b-2 no-underline whitespace-nowrap',
            $route.name === 'blog'
              ? 'text-[#00b14f] border-[#00b14f]'
              : 'text-slate-700 border-transparent hover:text-[#00b14f]'
          ]"
        >
          TIN TỨC
        </router-link>

        <router-link
          v-if="canApplyAsVendor"
          :to="vendorRegistrationTarget"
          :class="[
            'flex min-h-11 items-center px-5 py-3 text-sm font-extrabold uppercase tracking-wider transition-colors border-b-2 no-underline whitespace-nowrap',
            $route.name === 'vendor-register'
              ? 'text-[#00b14f] border-[#00b14f]'
              : 'text-slate-700 border-transparent hover:text-[#00b14f]'
          ]"
        >
          ĐĂNG KÝ NHÀ BÁN
        </router-link>

        <router-link
          v-if="authStore.isAuthenticated"
          to="/my-library"
          :class="[
            'flex min-h-11 items-center px-5 py-3 text-sm font-extrabold uppercase tracking-wider transition-colors border-b-2 no-underline whitespace-nowrap',
            $route.name === 'my-library'
              ? 'text-[#00b14f] border-[#00b14f]'
              : 'text-slate-700 border-transparent hover:text-[#00b14f]'
          ]"
        >
          TỦ SÁCH
        </router-link>
      </div>
    </nav>

    <!-- Mobile Menu Dropdown -->
    <Transition name="slide-down">
      <div
        v-if="mobileMenuOpen"
        id="mobile-main-menu"
        class="border-t border-slate-200 bg-white px-4 pb-6 lg:hidden"
      >
        <!-- Mobile Search -->
        <div class="relative mt-md mb-md">
          <input
            ref="mobileSearchRef"
            v-model="searchQuery"
            aria-label="Tìm kiếm sách"
            autocomplete="off"
            class="min-h-11 w-full pl-10 pr-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm font-inter text-on-surface placeholder:text-outline"
            placeholder="Tìm kiếm sách..."
            type="text"
            @keyup.enter="doSearch"
          />
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
        </div>

        <!-- Mobile Nav Links -->
        <nav class="flex flex-col gap-xs" aria-label="Điều hướng chính trên điện thoại">
          <router-link to="/" class="flex min-h-11 items-center gap-md rounded-lg px-md py-sm text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low" @click="mobileMenuOpen = false">
            <span class="material-symbols-outlined text-[20px]">home</span> Trang chủ
          </router-link>
          <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between rounded-lg px-md py-sm text-left text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low"
            :aria-expanded="mobileCategoriesOpen"
            aria-controls="mobile-category-menu"
            @click="mobileCategoriesOpen = !mobileCategoriesOpen"
          >
            <span class="flex items-center gap-md">
              <span class="material-symbols-outlined text-[20px]" aria-hidden="true">category</span>
              Danh mục sách
            </span>
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
              {{ mobileCategoriesOpen ? 'expand_less' : 'expand_more' }}
            </span>
          </button>
          <div
            v-if="mobileCategoriesOpen"
            id="mobile-category-menu"
            class="ml-4 border-l border-outline-variant pl-3"
          >
            <router-link
              to="/catalog"
              class="flex min-h-11 items-center rounded-lg px-md py-sm text-sm font-semibold text-primary hover:bg-surface-container-low"
              @click="closeNavigationMenus"
            >
              Tất cả danh mục
            </router-link>
            <p v-if="loadingCategories" class="px-md py-sm text-sm text-text-muted">Đang tải thể loại…</p>
            <div v-else-if="categoryLoadFailed" class="px-md py-sm text-sm text-error">
              Chưa thể tải danh sách thể loại.
              <button type="button" class="mt-2 min-h-11 rounded-lg px-md font-semibold text-primary hover:bg-surface-container-low" @click="fetchTopCategories">Thử lại</button>
            </div>
            <router-link
              v-for="category in topCategories"
              :key="category.id"
              :to="{ name: 'catalog', query: { category_id: category.id } }"
              class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-md py-sm text-sm text-on-surface-variant hover:bg-surface-container-low hover:text-primary"
              @click="closeNavigationMenus"
            >
              <span>{{ category.name }}</span>
              <span class="text-sm text-outline">{{ category.published_books_count }}</span>
            </router-link>
          </div>
          <router-link
            :to="{ name: 'catalog', query: { provenance: 'used_resale' } }"
            class="flex min-h-11 items-center gap-md rounded-lg px-md py-sm text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low"
            @click="closeNavigationMenus"
          >
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">auto_stories</span>
            Sách cũ
          </router-link>
          <router-link to="/blog" class="flex min-h-11 items-center gap-md rounded-lg px-md py-sm text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low" @click="mobileMenuOpen = false">
            <span class="material-symbols-outlined text-[20px]">newspaper</span> Tin tức
          </router-link>
          <router-link
            v-if="canApplyAsVendor"
            :to="vendorRegistrationTarget"
            class="flex min-h-11 items-center gap-md rounded-lg px-md py-sm text-sm font-bold text-primary transition-colors hover:bg-surface-container-low"
            @click="closeNavigationMenus"
          >
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">storefront</span>
            Đăng ký Nhà bán
          </router-link>
          <template v-if="authStore.isAuthenticated">
            <router-link to="/my-library" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">local_library</span> Tủ sách
            </router-link>
            <router-link to="/orders" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">shopping_bag</span> Đơn hàng
            </router-link>
            <router-link to="/notifications" class="flex min-h-11 items-center justify-between px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="flex items-center gap-md">
                <span class="material-symbols-outlined text-[20px]">notifications</span> Thông báo
              </span>
              <span v-if="unreadNotificationsCount > 0" class="bg-error text-on-error text-[10px] font-bold px-2 py-0.5 rounded-full">
                {{ unreadNotificationsCount }}
              </span>
            </router-link>
            <router-link to="/profile" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">person</span> Tài khoản
            </router-link>
            <button @click="handleLogout" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-error hover:bg-error-container/30 transition-colors text-sm font-medium w-full text-left">
              <span class="material-symbols-outlined text-[20px]">logout</span> Đăng xuất
            </button>
          </template>
          <template v-else>
            <router-link to="/login" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-primary hover:bg-surface-container-low transition-colors text-sm font-bold" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">login</span> Đăng nhập
            </router-link>
            <router-link to="/register" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg bg-primary text-on-primary transition-colors text-sm font-bold" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">person_add</span> Đăng ký
            </router-link>
          </template>
        </nav>
      </div>
    </Transition>
  </header>
</template>

<script setup>
import { nextTick, ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chatStore'
import { useCartStore } from '@/stores/cart'
import apiClient from '@/services/axios'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const chatStore = useChatStore()
const cartStore = useCartStore()
const logoExists = ref(false)
const mobileMenuOpen = ref(false)
const categoryMenuOpen = ref(false)
const mobileCategoriesOpen = ref(false)
const topCategories = ref([])
const loadingCategories = ref(false)
const categoryLoadFailed = ref(false)
const userMenuOpen = ref(false)
const userMenuRef = ref(null)
const categoryMenuRef = ref(null)
const userMenuTriggerRef = ref(null)
const userMenuFirstActionRef = ref(null)
const categoryMenuTriggerRef = ref(null)
const categoryMenuFirstActionRef = ref(null)
const mobileMenuTriggerRef = ref(null)
const mobileSearchRef = ref(null)
const searchQuery = ref('')
const unreadNotificationsCount = ref(0)
const showHeader = ref(true)
const lastScrollY = ref(0)
let notificationTimer

const userRoleLabel = computed(() => {
  if (authStore.isAdmin) return 'Quản trị'
  if (authStore.isVendor || authStore.user?.capabilities?.active_vendor) return 'Nhà bán'
  if (authStore.isWarehouseManager) return 'Quản kho'
  return 'Thành viên'
})

const managementTarget = computed(() => {
  if (!authStore.isAuthenticated) return null
  if (authStore.isAdmin) {
    return { label: 'Trang quản trị hệ thống', icon: 'pi pi-cog', route: '/admin/dashboard' }
  }
  if (authStore.isVendor || authStore.user?.capabilities?.active_vendor) {
    return { label: 'Trang quản lý gian hàng', icon: 'pi pi-store', route: '/vendor/dashboard' }
  }
  if (authStore.isWarehouseManager) {
    return { label: 'Không gian Quản kho', icon: 'pi pi-box', route: '/warehouse-manager/dashboard' }
  }
  return null
})

const canApplyAsVendor = computed(() => (
  !authStore.isAdmin
  && !authStore.user?.capabilities?.active_vendor
))

const canAccessPartnerPortal = computed(() => (
  authStore.isAdmin
  || authStore.isVendor
  || authStore.user?.capabilities?.active_vendor
  || authStore.user?.capabilities?.organization_manager
))

const vendorRegistrationTarget = computed(() => (
  authStore.isAuthenticated
    ? { name: 'vendor-register' }
    : { name: 'login', query: { redirect: '/vendor/register' } }
))

const focusElement = (elementRef) => {
  const element = elementRef.value?.$el || elementRef.value
  element?.focus?.()
}

const toggleUserMenu = async () => {
  userMenuOpen.value = !userMenuOpen.value
  if (userMenuOpen.value) {
    await nextTick()
    focusElement(userMenuFirstActionRef)
  }
}

const toggleCategoryMenu = async () => {
  categoryMenuOpen.value = !categoryMenuOpen.value
  if (categoryMenuOpen.value) {
    await nextTick()
    focusElement(categoryMenuFirstActionRef)
  }
}

const toggleMobileMenu = async () => {
  mobileMenuOpen.value = !mobileMenuOpen.value
  if (mobileMenuOpen.value) {
    await nextTick()
    focusElement(mobileSearchRef)
  }
}

const goToManagement = () => {
  userMenuOpen.value = false
  if (managementTarget.value) {
    router.push(managementTarget.value.route)
  }
}

const openChatList = () => {
  userMenuOpen.value = false
  chatStore.openConversationList()
}

const closeNavigationMenus = () => {
  mobileMenuOpen.value = false
  categoryMenuOpen.value = false
  mobileCategoriesOpen.value = false
  userMenuOpen.value = false
}

const handleWindowScroll = () => {
  userMenuOpen.value = false
  categoryMenuOpen.value = false

  const currentScrollY = window.scrollY || window.pageYOffset || 0
  if (currentScrollY > 80) {
    if (currentScrollY > lastScrollY.value + 5) {
      // Scroll DOWN -> Hide Header
      showHeader.value = false
    } else if (currentScrollY < lastScrollY.value - 5) {
      // Scroll UP -> Reveal Header
      showHeader.value = true
    }
  } else {
    // Top of page -> Show Header
    showHeader.value = true
  }

  lastScrollY.value = currentScrollY
}

const handleClickOutside = (event) => {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    userMenuOpen.value = false
  }
  if (categoryMenuRef.value && !categoryMenuRef.value.contains(event.target)) {
    categoryMenuOpen.value = false
  }
}

const handleGlobalKeydown = (event) => {
  if (event.key !== 'Escape') return

  if (userMenuOpen.value) focusElement(userMenuTriggerRef)
  else if (categoryMenuOpen.value) focusElement(categoryMenuTriggerRef)
  else if (mobileMenuOpen.value) focusElement(mobileMenuTriggerRef)
  closeNavigationMenus()
}

watch(() => route.fullPath, closeNavigationMenus)

// Tự động đồng bộ/làm mới ô tìm kiếm trên Header theo param search của URL (xóa sạch từ khóa khi chuyển sang trang khác)
watch(
  () => route.query.search,
  (newSearch) => {
    searchQuery.value = typeof newSearch === 'string' ? newSearch : ''
  },
  { immediate: true }
)

const fetchTopCategories = async () => {
  loadingCategories.value = true
  categoryLoadFailed.value = false
  try {
    const response = await apiClient.get('/api/categories', {
      params: { popular: 1, limit: 10 },
    })
    topCategories.value = Array.isArray(response.data?.data) ? response.data.data : []
  } catch (error) {
    categoryLoadFailed.value = true
    console.error('Failed to fetch public categories:', error)
  } finally {
    loadingCategories.value = false
  }
}

const fetchUnreadCount = async () => {
  if (!authStore.isAuthenticated) return
  try {
    const res = await apiClient.get('/api/notifications')
    unreadNotificationsCount.value = res.data.unread_count || 0
  } catch (err) {
    console.error('Failed to fetch unread notification count:', err)
  }
}

const doSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ name: 'catalog', query: { search: searchQuery.value.trim() } })
    closeNavigationMenus()
  }
}

const handleLogout = async () => {
  closeNavigationMenus()
  await authStore.logout()
  router.push({ name: 'home' })
}

onMounted(() => {
  const img = new Image()
  img.onload = () => logoExists.value = true
  img.onerror = () => logoExists.value = false
  img.src = new URL('@/assets/logo.png', import.meta.url).href
  
  if (authStore.isAuthenticated) {
    fetchUnreadCount()
    notificationTimer = window.setInterval(fetchUnreadCount, 60000)
  }

  fetchTopCategories()
  window.addEventListener('click', handleClickOutside)
  window.addEventListener('keydown', handleGlobalKeydown)
  window.addEventListener('scroll', handleWindowScroll, { passive: true, capture: true })
})

onUnmounted(() => {
  window.removeEventListener('click', handleClickOutside)
  window.removeEventListener('keydown', handleGlobalKeydown)
  window.removeEventListener('scroll', handleWindowScroll, { capture: true })
  if (notificationTimer) window.clearInterval(notificationTimer)
})

const getAvatarUrl = (avatar) => {
  if (!avatar) return ''
  // Nếu là path đã có sẵn /storage/ (từ backend mới)
  if (avatar.startsWith('/storage/')) return avatar
  
  // Nếu là URL tuyệt đối từ domain cũ (ví dụ komibook.test), chuyển về tương đối
  if (avatar.includes('/storage/')) {
    return avatar.substring(avatar.indexOf('/storage/'))
  }

  // Fallback cho path cũ
  return `/storage/${avatar}`
}
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active {
  transition:
    opacity 0.22s var(--ui-ease-standard),
    max-height 0.22s var(--ui-ease-standard);
}
.slide-down-enter-from, .slide-down-leave-to {
  opacity: 0;
  max-height: 0;
  overflow: hidden;
}
.slide-down-enter-to, .slide-down-leave-from {
  max-height: 500px;
}
</style>
