<template>
  <div class="min-h-screen bg-background py-xl px-gutter">
    <div class="max-w-[1200px] mx-auto">

      <!-- Stepper / Breadcrumb -->
      <div class="flex items-center gap-4 mb-xl overflow-x-auto pb-2 no-scrollbar">
        <div class="flex items-center gap-2 shrink-0">
          <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center text-sm font-bold">1</div>
          <span class="text-sm font-bold text-on-surface">Giỏ hàng</span>
        </div>
        <div class="w-12 h-px bg-outline-variant"></div>
        <div class="flex items-center gap-2 shrink-0">
          <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold', step >= 2 ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant']">2</div>
          <span :class="['text-sm font-bold', step >= 2 ? 'text-on-surface' : 'text-on-surface-variant']">Thanh toán</span>
        </div>
        <div class="w-12 h-px bg-outline-variant"></div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="w-8 h-8 rounded-full bg-surface-container-high text-on-surface-variant flex items-center justify-center text-sm font-bold">3</div>
          <span class="text-sm font-bold text-on-surface-variant">Hoàn tất</span>
        </div>
      </div>

      <h1 class="font-inter text-3xl font-bold text-on-surface tracking-tight mb-xl flex items-center gap-3">
        <span class="material-symbols-outlined text-primary text-3xl">shopping_cart</span>
        {{ step === 1 ? 'Giỏ hàng của bạn' : 'Thông tin thanh toán' }}
      </h1>

      <!-- EMPTY STATE -->
      <div v-if="cartStore.items.length === 0" class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 soft-shadow p-20 text-center">
        <div class="w-24 h-24 rounded-full bg-primary-container/30 flex items-center justify-center mx-auto mb-6">
          <span class="material-symbols-outlined text-5xl text-primary">shopping_basket</span>
        </div>
        <h2 class="text-2xl font-bold text-on-surface mb-3">Giỏ hàng của bạn đang trống</h2>
        <p class="text-on-surface-variant mb-xl max-w-[28rem] mx-auto leading-relaxed">Hãy khám phá hàng ngàn cuốn sách hấp dẫn và kiến thức vô tận đang chờ bạn tại KomiBook.</p>
        <button
          @click="$router.push('/')"
          class="inline-flex items-center gap-2 bg-primary text-on-primary px-xl py-md rounded-xl text-base font-bold hover:bg-primary/90 transition-colors shadow-md active:scale-95 border-none cursor-pointer"
        >
          <span class="material-symbols-outlined">explore</span> Tiếp tục khám phá
        </button>
      </div>

      <!-- CART CONTENT -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-xl items-start">

        <!-- Cột trái: Nội dung giỏ hàng hoặc Form thanh toán -->
        <div class="lg:col-span-2 space-y-lg">

          <!-- STEP 1: CART ITEMS -->
          <template v-if="step === 1">
            <!-- Freeship Notification Banner -->
            <div v-if="cartStore.selectedItems.length > 0" class="rounded-2xl p-md border transition-colors flex items-center justify-between gap-md" :class="hasAutoFreeShipping ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900'">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-2xl" :class="hasAutoFreeShipping ? 'text-emerald-600' : 'text-amber-600'">local_shipping</span>
                <div>
                  <div class="font-bold text-sm">
                    {{ hasAutoFreeShipping ? '🎉 Đã đạt điều kiện Miễn phí vận chuyển Komi Express!' : '💡 Ưu đãi vận chuyển Komi Express toàn sàn' }}
                  </div>
                  <div class="text-xs">
                    {{ !shippingPreviewAvailable ? 'Phí vận chuyển sẽ được tính khi thanh toán.' : (hasAutoFreeShipping ? 'Đơn hàng của bạn đã được TỰ ĐỘNG MIỄN PHÍ VẬN CHUYỂN toàn bộ các nhà bán.' : `Mua thêm ${formatCurrency(amountToFreeShipping)} để được Miễn phí vận chuyển toàn bộ (Phí hiện tại: ${policyFeeLabel}/nhà bán).`) }}
                  </div>
                </div>
              </div>
              <span class="text-xs font-bold px-3 py-1 rounded-full shrink-0" :class="hasAutoFreeShipping ? 'bg-emerald-200 text-emerald-800' : 'bg-amber-200 text-amber-800'">
                {{ hasAutoFreeShipping ? 'MIỄN PHÍ SHIP 100%' : (shippingPreviewAvailable ? `${policyFeeLabel}/NHÀ BÁN` : 'TÍNH KHI THANH TOÁN') }}
              </span>
            </div>

            <!-- Master Selection & Bulk Action Bar -->
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 soft-shadow p-lg flex flex-wrap items-center justify-between gap-md">
              <label class="flex min-h-11 items-center gap-3 cursor-pointer select-none font-bold text-on-surface text-base">
                <input
                  type="checkbox"
                  :checked="cartStore.isAllSelected"
                  @change="e => cartStore.toggleSelectAll(e.target.checked)"
                  class="w-5 h-5 accent-primary rounded cursor-pointer"
                />
                <span>Chọn tất cả ({{ cartStore.items.length }} sản phẩm)</span>
              </label>

              <div class="flex items-center gap-md">
                <button
                  v-if="cartStore.selectedItems.length > 0"
                  @click="confirmRemoveSelected"
                  class="inline-flex items-center gap-1.5 text-xs font-bold text-error hover:bg-error-container/20 px-3 py-2 rounded-xl transition-colors border border-error/20 bg-transparent cursor-pointer"
                >
                  <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
                  Xóa sản phẩm đã chọn ({{ cartStore.selectedItems.length }})
                </button>
              </div>
            </div>

            <!-- List by Vendor Group -->
            <div v-for="group in cartStore.groupedItems" :key="group.vendorId" class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 soft-shadow overflow-hidden">
              <!-- Header Shop -->
              <div class="px-lg py-md bg-surface-container-low/50 flex items-center justify-between border-b border-outline-variant/20">
                <label class="flex min-h-11 items-center gap-3 cursor-pointer select-none">
                  <input
                    type="checkbox"
                    :checked="cartStore.isVendorAllSelected(group.vendorId)"
                    @change="e => cartStore.toggleSelectVendorGroup(group.vendorId, e.target.checked)"
                    class="w-4 h-4 accent-primary rounded cursor-pointer"
                  />
                  <span class="material-symbols-outlined text-primary">store</span>
                  <span class="font-bold text-on-surface">{{ group.vendorName }}</span>
                </label>
                <span class="text-xs text-outline font-medium px-md py-1 bg-surface-container-high rounded-full">
                  {{ group.items.length }} sản phẩm
                </span>
              </div>

              <!-- Danh sách sách -->
              <div class="divide-y divide-outline-variant/10">
                <div v-for="item in group.items" :key="item.book.id" class="p-lg flex flex-col sm:flex-row items-start sm:items-center gap-lg transition-colors hover:bg-surface-container-low/20">

                  <!-- Checkbox Item -->
                  <label class="flex min-h-11 min-w-11 items-center justify-center shrink-0 cursor-pointer sm:pt-0" :for="`cart-item-${item.book.id}`">
                    <input
                      :id="`cart-item-${item.book.id}`"
                      type="checkbox"
                      :checked="item.selected !== false"
                      @change="cartStore.toggleSelectItem(item.book.id)"
                      class="w-5 h-5 accent-primary rounded cursor-pointer"
                      :aria-label="`Chọn ${item.book.display_title || item.book.title}`"
                    />
                  </label>

                  <!-- Hình ảnh -->
                  <div class="relative aspect-[2/3] w-24 shrink-0 overflow-hidden bg-surface-container shadow-sm sm:w-28 rounded-lg">
                    <router-link :to="`/book/${item.book.slug}`" class="absolute inset-0 block focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim" :aria-label="`Xem chi tiết ${item.book.title}`">
                    <img v-if="item.book.cover_image" :src="item.book.cover_image" :alt="`Bìa sách ${item.book.title}`" class="h-full w-full rounded-none object-contain p-2" />
                    <div v-else class="absolute inset-0 flex items-center justify-center bg-surface-container-high">
                      <span class="material-symbols-outlined text-outline text-3xl">image</span>
                    </div>
                    </router-link>
                  </div>

                  <!-- Info & Actions -->
                  <div class="flex-1 flex flex-col justify-between w-full">
                    <div class="flex justify-between items-start gap-4">
                      <div class="flex-1">
                        <h3 class="text-lg font-bold text-on-surface line-clamp-2 hover:text-primary cursor-pointer transition-colors leading-snug mb-1" @click="$router.push(`/book/${item.book.slug}`)">{{ item.book.display_title || item.book.title }}</h3>
                        <div class="flex items-center gap-2 mb-2">
                          <span class="text-sm text-on-surface-variant font-medium">{{ item.book.author || 'Đang cập nhật' }}</span>
                          <span class="w-1 h-1 rounded-full bg-outline-variant"></span>
                          <span class="text-xs text-outline uppercase tracking-wider font-bold">{{ item.book.type === 'ebook' ? 'E-book' : 'Sách giấy' }}</span>
                        </div>
                        <p v-if="item.book.type === 'ebook'" class="text-xs font-bold text-primary">
                          {{ item.book.latest_ebook_version?.version
                            ? `Phiên bản mới nhất: ${item.book.latest_ebook_version.version}`
                            : 'Phiên bản mới nhất sẽ được chốt khi thanh toán' }}
                        </p>
                      </div>
                      <button
                        class="flex h-11 w-11 items-center justify-center rounded-full border-none bg-transparent text-outline transition-colors hover:bg-error-container/20 hover:text-error cursor-pointer"
                        @click="confirmRemove(item.book)"
                        :aria-label="`Xoá ${item.book.title} khỏi giỏ`"
                      >
                        <span class="material-symbols-outlined text-[20px]">delete</span>
                      </button>
                    </div>

                    <div class="flex flex-wrap justify-between items-end gap-md mt-4">
                      <div class="flex flex-col">
                        <span class="text-xl font-bold text-primary">
                          {{ formatCurrency(item.book.sale_price || item.book.price) }}
                        </span>
                        <span v-if="item.book.sale_price && item.book.price > item.book.sale_price" class="text-sm text-outline line-through">
                          {{ formatCurrency(item.book.price) }}
                        </span>
                      </div>

                      <!-- Counter -->
                      <div v-if="item.book.type !== 'ebook'" class="flex items-center gap-1 p-1 bg-surface-container-low rounded-xl border border-outline-variant/20">
                        <button
                          class="flex h-11 w-11 items-center justify-center rounded-lg border-none bg-transparent text-on-surface-variant transition-colors hover:bg-surface-container-highest disabled:opacity-30 cursor-pointer"
                          @click="updateQuantity(item.book.id, item.quantity - 1)"
                          :disabled="item.quantity <= 1"
                          :aria-label="`Giảm số lượng ${item.book.title}`"
                        >
                          <span class="material-symbols-outlined text-[18px]">remove</span>
                        </button>
                        <input
                          type="number"
                          :value="item.quantity"
                          @change="(e) => updateQuantity(item.book.id, parseInt(e.target.value) || 1)"
                          class="w-10 text-center bg-transparent border-none text-sm font-bold text-on-surface focus:outline-none p-0 hide-arrows"
                          min="1"
                          :aria-label="`Số lượng ${item.book.title}`"
                        />
                        <button
                          class="flex h-11 w-11 items-center justify-center rounded-lg border-none bg-transparent text-on-surface-variant transition-colors hover:bg-surface-container-highest cursor-pointer"
                          @click="updateQuantity(item.book.id, item.quantity + 1)"
                          :aria-label="`Tăng số lượng ${item.book.title}`"
                        >
                          <span class="material-symbols-outlined text-[18px]">add</span>
                        </button>
                      </div>
                      <div v-else class="flex min-h-11 items-center rounded-lg bg-primary-container px-3 text-xs font-bold text-on-primary-container">
                        1 quyền truy cập số
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mobile Footer Action -->
            <div class="lg:hidden fixed bottom-0 left-0 right-0 p-lg bg-surface-container-lowest border-t border-outline-variant/30 z-30 flex items-center justify-between shadow-2xl">
              <div>
                <div class="text-xs text-outline font-medium">Tổng tiền ({{ cartStore.selectedTotalItems }} món)</div>
                <div class="text-xl font-black text-primary">{{ finalTotal === null ? 'Tính khi thanh toán' : formatCurrency(finalTotal) }}</div>
              </div>
              <button
                @click="goToCheckout"
                :disabled="cartStore.selectedItems.length === 0"
                class="bg-primary text-on-primary px-xl py-md rounded-xl font-bold shadow-md hover:bg-primary/90 transition-colors active:scale-95 border-none cursor-pointer disabled:opacity-50"
              >
                Thanh toán
              </button>
            </div>
          </template>

          <!-- STEP 2: CHECKOUT FORM -->
          <template v-else>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 soft-shadow p-lg md:p-xl space-y-xl">

              <!-- Address Section -->
              <section v-if="hasPhysicalBooks">
                <div class="flex items-center justify-between mb-lg">
                  <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">location_on</span>
                    Địa chỉ nhận hàng
                  </h3>
                  <button @click="step = 1" class="text-sm font-bold text-secondary hover:underline bg-transparent border-none cursor-pointer">Sửa giỏ hàng</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                  <div class="space-y-2">
                    <label for="checkout-address-book" class="text-sm font-bold text-on-surface-variant ml-1">Sổ địa chỉ</label>
                    <Select inputId="checkout-address-book" v-model="selectedAddress" :options="addresses" optionLabel="address" placeholder="Chọn địa chỉ đã lưu..." class="w-full !rounded-xl !border-outline-variant/40" @change="onAddressSelect">
                      <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center">
                          <div class="font-medium">{{ slotProps.value.receiver_name }} - {{ slotProps.value.phone }}</div>
                        </div>
                        <span v-else>{{ slotProps.placeholder }}</span>
                      </template>
                      <template #option="slotProps">
                        <div class="flex flex-col py-1">
                          <span class="font-bold text-on-surface">{{ slotProps.option.receiver_name }} ({{ slotProps.option.phone }})</span>
                          <span class="text-xs text-on-surface-variant">{{ slotProps.option.address }}</span>
                        </div>
                      </template>
                    </Select>
                  </div>

                  <div class="space-y-2">
                    <label for="checkout-receiver" class="text-sm font-bold text-on-surface-variant ml-1">Tên người nhận</label>
                    <div class="relative">
                      <InputText id="checkout-receiver" v-model="shippingData.receiver_name" placeholder="Nhập họ và tên..." class="w-full !pl-10 !rounded-xl !border-outline-variant/40" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">person</span>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <label for="checkout-phone" class="text-sm font-bold text-on-surface-variant ml-1">Số điện thoại</label>
                    <div class="relative">
                      <InputText id="checkout-phone" v-model="shippingData.phone" placeholder="Nhập số điện thoại..." class="w-full !pl-10 !rounded-xl !border-outline-variant/40" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">phone</span>
                    </div>
                  </div>

                  <div class="space-y-2 md:col-span-2">
                    <label for="checkout-address" class="text-sm font-bold text-on-surface-variant ml-1">Địa chỉ chi tiết</label>
                    <div class="relative">
                      <InputText id="checkout-address" v-model="shippingData.shipping_address" placeholder="Số nhà, tên đường, phường/xã, quận/huyện..." class="w-full !pl-10 !rounded-xl !border-outline-variant/40" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">home</span>
                    </div>
                  </div>
                </div>
              </section>
              <section v-else class="rounded-xl border border-primary/20 bg-primary-container p-5 text-on-primary-container">
                <h3 class="flex items-center gap-2 font-bold"><span class="material-symbols-outlined" aria-hidden="true">cloud_download</span>Giao nội dung số</h3>
                <p class="mt-2 text-sm leading-6">Đơn chỉ có ebook nên không cần địa chỉ nhận hàng. Ebook sẽ xuất hiện trong Tủ sách sau khi thanh toán hoàn tất.</p>
              </section>

              <!-- Multi-Vendor Order Breakdown Preview -->
              <section class="pt-xl border-t border-outline-variant/10">
                <div class="flex items-center justify-between mb-md">
                  <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">inventory</span>
                    Kiện hàng sẽ khởi tạo ({{ cartStore.selectedGroupedItems.length }} đơn từ {{ cartStore.selectedGroupedItems.length }} nhà bán)
                  </h3>
                </div>
                <p class="text-xs text-on-surface-variant mb-lg">Hệ thống sẽ tự động tạo đơn hàng riêng cho từng nhà bán để xử lý và bàn giao giao hàng độc lập.</p>

                <div class="space-y-md">
                  <div
                    v-for="(group, idx) in cartStore.selectedGroupedItems"
                    :key="group.vendorId"
                    class="p-md rounded-2xl border border-outline-variant/30 bg-surface-container-low/30 flex flex-col md:flex-row justify-between items-start md:items-center gap-md"
                  >
                    <div>
                      <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[11px] font-bold">Đơn #{{ idx + 1 }}</span>
                        <span class="font-bold text-on-surface text-sm flex items-center gap-1">
                          <span class="material-symbols-outlined text-base text-primary">store</span>
                          {{ group.vendorName }}
                        </span>
                      </div>
                      <p class="text-xs text-on-surface-variant line-clamp-1">
                        {{ group.items.map(i => i.book.display_title || i.book.title).join(', ') }}
                      </p>
                    </div>

                    <div class="flex items-center gap-md self-end md:self-auto shrink-0">
                      <div class="text-right">
                        <span class="text-xs text-outline block">Tạm tính kiện</span>
                        <span class="text-sm font-bold text-primary">{{ formatCurrency(groupSubtotal(group)) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Payment Method -->
              <section class="pt-xl border-t border-outline-variant/10">
                <h3 class="text-lg font-bold text-on-surface mb-lg flex items-center gap-2">
                  <span class="material-symbols-outlined text-primary">payments</span>
                  Phương thức thanh toán
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                  <button
                    type="button"
                    @click="paymentMethod = 'COD'"
                    :aria-pressed="paymentMethod === 'COD'"
                    :class="['p-lg rounded-2xl border-2 cursor-pointer transition-[border-color,background-color,box-shadow] flex items-center gap-md text-left', paymentMethod === 'COD' ? 'border-primary bg-primary-container/5 shadow-sm' : 'border-outline-variant/20 hover:border-outline-variant/60']"
                  >
                    <div :class="['w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0', paymentMethod === 'COD' ? 'border-primary' : 'border-outline']">
                      <div v-if="paymentMethod === 'COD'" class="w-3 h-3 rounded-full bg-primary"></div>
                    </div>
                    <div class="flex-1">
                      <div class="font-bold text-on-surface">Thanh toán khi nhận hàng (COD)</div>
                      <div class="text-xs text-on-surface-variant">Thanh toán bằng tiền mặt khi giao hàng</div>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-primary/40">local_shipping</span>
                  </button>

                  <button
                    v-for="provider in availablePaymentProviders"
                    :key="provider.id"
                    type="button"
                    @click="paymentMethod = provider.id.toUpperCase()"
                    :aria-pressed="paymentMethod === provider.id.toUpperCase()"
                    :class="['p-lg rounded-2xl border-2 cursor-pointer transition-[border-color,background-color,box-shadow] flex items-center gap-md text-left', paymentMethod === provider.id.toUpperCase() ? 'border-primary bg-primary-container/5 shadow-sm' : 'border-outline-variant/20 hover:border-outline-variant/60']"
                  >
                    <div :class="['w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0', paymentMethod === provider.id.toUpperCase() ? 'border-primary' : 'border-outline']">
                      <div v-if="paymentMethod === provider.id.toUpperCase()" class="w-3 h-3 rounded-full bg-primary"></div>
                    </div>
                    <div class="flex-1">
                      <div class="font-bold text-on-surface">{{ provider.name }}</div>
                      <div class="text-xs text-on-surface-variant">{{ provider.notice }}</div>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-primary/40">{{ provider.supports_qr ? 'qr_code_2' : 'account_balance_wallet' }}</span>
                  </button>
                </div>
                <p v-if="availablePaymentProviders.some(provider => provider.mode === 'demo')" class="mt-md rounded-xl border border-amber-300 bg-amber-50 p-md text-sm leading-6 text-amber-950">
                  Phương thức có nhãn demo chỉ mô phỏng nội bộ, không quét/chuyển tiền thật và không phát sinh phí.
                </p>
              </section>

              <!-- Coupon -->
              <section class="pt-xl border-t border-outline-variant/10">
                <h3 class="text-lg font-bold text-on-surface mb-lg flex items-center gap-2">
                  <span class="material-symbols-outlined text-primary">sell</span>
                  Mã giảm giá
                </h3>
                <label for="coupon-code" class="mb-2 block text-sm font-bold text-on-surface-variant">Mã ưu đãi</label>
                <div class="max-w-[28rem] flex gap-2">
                  <div class="relative flex-1">
                    <InputText id="coupon-code" v-model="couponCode" placeholder="Nhập mã ưu đãi..." class="w-full !pl-10 !rounded-xl !border-outline-variant/40" aria-describedby="coupon-status" />
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">confirmation_number</span>
                  </div>
                  <Button label="Áp dụng" class="!px-6 !rounded-xl !bg-primary-container !text-on-primary-container !border-none font-bold" :loading="isApplyingCoupon" @click="applyCoupon" />
                </div>
                <p id="coupon-status" class="mt-2 text-sm text-on-surface-variant" role="status" aria-live="polite">{{ couponStatus }}</p>
                <div v-if="appliedCoupon" class="inline-flex items-center gap-2 mt-3 px-md py-1.5 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-lg border border-emerald-100 animate-fade-in">
                  <span class="material-symbols-outlined text-[16px]">check_circle</span>
                  Đã áp dụng mã: {{ appliedCoupon.code }} (-{{ formatCurrency(appliedCoupon.discount_amount) }})
                </div>
              </section>
            </div>
          </template>
        </div>

        <!-- Cột phải: Tổng kết đơn hàng -->
        <div class="lg:col-span-1 sticky top-24">
          <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 soft-shadow p-lg md:p-xl">
            <h2 class="text-xl font-bold text-on-surface tracking-tight mb-xl">Tóm tắt đơn hàng</h2>

            <div class="space-y-4 mb-xl">
              <div class="flex justify-between text-sm text-on-surface-variant">
                <span>Tạm tính sản phẩm ({{ cartStore.selectedTotalItems }} món)</span>
                <span class="font-bold text-on-surface">{{ formatCurrency(cartStore.selectedTotalPrice) }}</span>
              </div>

              <!-- Chi tiết phí ship từng Shop -->
              <div v-if="vendorShippingBreakdown.length > 0" class="py-2 border-y border-outline-variant/10 space-y-1.5">
                <div class="text-xs font-bold text-on-surface-variant flex items-center gap-1">
                  <span class="material-symbols-outlined text-sm text-primary">local_shipping</span>
                  Phí vận chuyển ({{ vendorShippingBreakdown.length }} nhà bán):
                </div>
                <div v-for="v in vendorShippingBreakdown" :key="v.vendorName" class="flex justify-between text-xs pl-4 text-on-surface-variant">
                  <span>{{ v.vendorName }}</span>
                  <span :class="v.isFreeship ? 'text-emerald-600 font-bold' : 'text-on-surface font-medium'">
                    {{ !v.previewAvailable ? 'Tính khi thanh toán' : (v.isFreeship ? 'Miễn phí' : formatCurrency(v.fee)) }}
                  </span>
                </div>
              </div>

              <div class="flex justify-between text-sm text-on-surface-variant" v-if="appliedCoupon">
                <span>Giảm giá</span>
                <span class="font-bold text-emerald-600">-{{ formatCurrency(appliedCoupon.discount_amount) }}</span>
              </div>

              <div class="flex justify-between text-sm text-on-surface-variant">
                <span>Tổng phí vận chuyển</span>
                <span class="font-bold" :class="shippingPreviewAvailable && totalShippingFee === 0 ? 'text-emerald-600' : 'text-on-surface'">
                  {{ !shippingPreviewAvailable ? 'Tính khi thanh toán' : (totalShippingFee === 0 ? 'Miễn phí' : formatCurrency(totalShippingFee)) }}
                </span>
              </div>
            </div>

            <div class="pt-lg border-t border-outline-variant/20 mb-xl">
              <div class="flex justify-between items-center">
                <span class="text-base font-bold text-on-surface">Tổng cộng</span>
                <div class="text-right">
                  <div class="text-3xl font-black text-primary leading-none mb-1">{{ finalTotal === null ? 'Tính khi thanh toán' : formatCurrency(finalTotal) }}</div>
                  <div class="text-[10px] text-outline font-medium italic">(Đã bao gồm VAT nếu có)</div>
                </div>
              </div>
            </div>

            <template v-if="step === 1">
              <button
                @click="goToCheckout"
                :disabled="cartStore.selectedItems.length === 0"
                class="w-full py-4 bg-primary text-on-primary rounded-xl text-lg font-bold hover:bg-primary/90 transition-colors shadow-md active:scale-[0.98] flex items-center justify-center gap-2 border-none cursor-pointer disabled:opacity-50"
              >
                Tiến hành thanh toán
                <span class="material-symbols-outlined">arrow_forward</span>
              </button>
            </template>
            <template v-else>
              <div v-if="hasEbooks" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-left">
                <label class="flex min-h-11 cursor-pointer items-start gap-3">
                  <input v-model="ebookTermsAccepted" type="checkbox" class="mt-1 h-5 w-5 shrink-0" />
                  <span class="text-sm leading-6 text-amber-950">
                    Tôi mua <strong>phiên bản ebook mới nhất tại thời điểm đặt hàng</strong> và hiểu nội dung số
                    <strong>không được trả lại sau khi mua</strong>. Khi ebook được cập nhật, tôi vẫn giữ bản đã mua
                    và được đọc các phiên bản mới hơn từ trình đọc ebook.
                    <RouterLink to="/policies/ebooks" class="inline-flex min-h-11 items-center font-bold text-primary underline">
                      Xem {{ ebookPolicyLabel }}
                    </RouterLink>.
                  </span>
                </label>
              </div>
              <button
                @click="processCheckout"
                :disabled="isSubmitting || (hasEbooks && !ebookTermsAccepted)"
                class="w-full py-4 bg-primary text-on-primary rounded-xl text-lg font-bold hover:bg-primary/90 transition-colors shadow-md active:scale-[0.98] flex items-center justify-center gap-2 disabled:opacity-50 border-none cursor-pointer"
              >
                <template v-if="isSubmitting">
                  <span class="pi pi-spin pi-spinner mr-2"></span>
                  Đang xử lý...
                </template>
                <template v-else>
                  Xác nhận đặt hàng
                  <span class="material-symbols-outlined ml-2">task_alt</span>
                </template>
              </button>
              <button
                @click="step = 1"
                class="w-full mt-4 py-3 bg-transparent text-outline rounded-xl text-sm font-bold hover:bg-surface-container-high transition-colors border-none cursor-pointer"
              >
                Quay lại giỏ hàng
              </button>
            </template>

            <div class="flex items-center gap-2 justify-center mt-xl text-outline">
              <span class="material-symbols-outlined text-[18px]">verified_user</span>
              <span class="text-[11px] font-medium uppercase tracking-tighter">Bảo mật & An toàn tuyệt đối</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <div v-if="pendingPayment" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-md" role="presentation">
    <section ref="paymentDialog" class="w-full max-w-md rounded-2xl bg-surface-container-lowest p-xl shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="demo-payment-title" aria-describedby="demo-payment-notice" tabindex="-1" @keydown="trapPaymentFocus">
      <div class="mb-lg flex items-start justify-between gap-md">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Thanh toán mô phỏng</p>
          <h2 id="demo-payment-title" class="mt-xs text-2xl font-bold text-on-surface">{{ pendingPayment.provider_name }}</h2>
        </div>
        <button ref="paymentCloseButton" type="button" class="min-h-11 min-w-11 rounded-full hover:bg-surface-container-high border-none bg-transparent cursor-pointer" aria-label="Đóng" @click="closeDemoPayment">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div v-if="pendingPayment.qr_payload" class="mx-auto mb-lg grid aspect-square w-52 place-items-center border-8 border-on-surface bg-white p-md text-center font-mono text-xs font-bold text-on-surface">
        QR DEMO<br />KHÔNG QUÉT<br />{{ formatCurrency(pendingPayment.amount) }}
      </div>
      <p id="demo-payment-notice" class="rounded-xl border border-amber-300 bg-amber-50 p-md text-sm leading-6 text-amber-950">{{ pendingPayment.notice }}</p>
      <button type="button" class="mt-lg min-h-12 w-full rounded-xl bg-primary px-lg font-bold text-on-primary disabled:opacity-50 border-none cursor-pointer" :disabled="isCompletingPayment" @click="completeSimulatedPayment">
        {{ isCompletingPayment ? 'Đang xác nhận...' : 'Xác nhận thanh toán demo' }}
      </button>
    </section>
  </div>
</template>

<script>
export const calculateCheckoutPreviewTotal = ({ subtotal, shippingFee, couponDiscount, hasPhysicalItems, shippingPolicyAvailable }) => {
  if (hasPhysicalItems && !shippingPolicyAvailable) return null

  return Math.max(0, subtotal + shippingFee - couponDiscount)
}

export const normalizeCartCategoryIds = (book) => {
  const categoryIds = [
    book?.category_id,
    book?.category?.id,
    ...(Array.isArray(book?.categories) ? book.categories.map(category => category?.id) : [])
  ]
    .filter(categoryId => categoryId !== undefined && categoryId !== null)
    .map(String)

  return [...new Set(categoryIds)].sort()
}

export const createSelectedCartQuoteFingerprint = (items) => JSON.stringify(
  items
    .map(item => ({
      bookId: item.book?.id,
      quantity: item.quantity,
      price: item.book?.sale_price || item.book?.price || null,
      vendorId: item.book?.vendor_id || item.book?.vendor?.id || null,
      type: item.book?.type || null,
      categoryIds: normalizeCartCategoryIds(item.book)
    }))
    .sort((left, right) => String(left.bookId).localeCompare(String(right.bookId)))
)

export const shouldAcceptCouponPreview = (requestedFingerprint, currentFingerprint) => (
  requestedFingerprint === currentFingerprint
)
</script>

<script setup>
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { ref, computed, nextTick, onMounted, watch } from 'vue'
import apiClient from '@/services/axios'
import { readApiData } from '@/services/apiContract'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useConfirm } from "primevue/useconfirm"
import { useToast } from "primevue/usetoast"

const cartStore = useCartStore()
const authStore = useAuthStore()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()

const step = ref(1)
const isSubmitting = ref(false)
const shippingData = ref({
  receiver_name: '',
  phone: '',
  shipping_address: ''
})

const paymentMethod = ref('COD')
const paymentProviders = ref([])
const pendingPayment = ref(null)
const pendingOrderId = ref(null)
const isCompletingPayment = ref(false)
const paymentDialog = ref(null)
const paymentCloseButton = ref(null)
const returnFocusElement = ref(null)
const availablePaymentProviders = computed(() => paymentProviders.value.filter(provider => provider.available))
const addresses = ref([])
const selectedAddress = ref(null)

const couponCode = ref('')
const isApplyingCoupon = ref(false)
const appliedCoupon = ref(null)
const couponStatus = ref('')
const ebookTermsAccepted = ref(false)
const ebookPolicy = ref(null)
const shippingPolicy = ref(null)

const targetItemsForCheckout = computed(() => {
  return cartStore.selectedItems
})
const selectedCartQuoteFingerprint = computed(() => createSelectedCartQuoteFingerprint(cartStore.selectedItems))
const hasEbooks = computed(() => targetItemsForCheckout.value.some(item => item.book.type === 'ebook'))
const hasPhysicalBooks = computed(() => targetItemsForCheckout.value.some(item => item.book.type !== 'ebook'))
const ebookPolicyLabel = computed(() => ebookPolicy.value?.version
  ? `chính sách ebook v${ebookPolicy.value.version}`
  : 'chính sách ebook hiện hành')

onMounted(() => {
  refreshCartBooks()
  fetchPublicPolicies()
  fetchShippingPolicy()
  fetchPaymentProviders()
  if (authStore.isAuthenticated) {
    fetchAddresses()
  }
})

const invalidateStaleCoupon = (fingerprint = selectedCartQuoteFingerprint.value) => {
  if (appliedCoupon.value?.quoteFingerprint && appliedCoupon.value.quoteFingerprint !== fingerprint) {
    appliedCoupon.value = null
    couponStatus.value = 'Giỏ hàng đã thay đổi. Hãy áp dụng lại mã ưu đãi.'
    return true
  }
  return false
}

watch(selectedCartQuoteFingerprint, (fingerprint) => {
  invalidateStaleCoupon(fingerprint)
}, { flush: 'sync' })

const fetchPaymentProviders = async () => {
  try {
    const response = await apiClient.get('/api/payment-providers')
    paymentProviders.value = response.data?.data || []
  } catch {
    paymentProviders.value = []
  }
}

const fetchPublicPolicies = async () => {
  try {
    const response = await apiClient.get('/api/policies/returns')
    ebookPolicy.value = response.data?.data?.ebook_non_returnable || null
  } catch {
    ebookPolicy.value = null
  }
}

const fetchShippingPolicy = async () => {
  try {
    const response = await apiClient.get('/api/commerce/shipping-policy')
    const policy = readApiData(response.data)
    shippingPolicy.value = Number.isInteger(policy?.free_shipping_threshold) && Number.isInteger(policy?.base_fee_per_physical_vendor)
      ? policy
      : null
  } catch {
    shippingPolicy.value = null
  }
}

const refreshCartBooks = async () => {
  await Promise.allSettled(cartStore.items.map(async (item) => {
    if (!item.book?.slug) return
    const response = await apiClient.get(`/api/books/${item.book.slug}`)
    cartStore.refreshBook(item.book.id, readApiData(response.data))
  }))
}

const fetchAddresses = async () => {
  if (!authStore.isAuthenticated) return
  try {
    const res = await apiClient.get('/api/profile/addresses')
    addresses.value = res.data.data
    if (addresses.value && addresses.value.length > 0) {
      const def = addresses.value.find(a => a.is_default) || addresses.value[0]
      selectedAddress.value = def
      onAddressSelect()
    }
  } catch (error) {
    console.error('Error fetching addresses:', error)
  }
}

const onAddressSelect = () => {
  if (selectedAddress.value) {
    shippingData.value.receiver_name = selectedAddress.value.receiver_name
    shippingData.value.phone = selectedAddress.value.phone
    shippingData.value.shipping_address = selectedAddress.value.address
  }
}

const groupSubtotal = (group) => {
  return group.items.reduce((sum, item) => sum + ((item.book.sale_price || item.book.price) * item.quantity), 0)
}

const shippingPreviewAvailable = computed(() => Boolean(shippingPolicy.value))
const hasAutoFreeShipping = computed(() => shippingPreviewAvailable.value && cartStore.selectedTotalPrice >= shippingPolicy.value.free_shipping_threshold)
const amountToFreeShipping = computed(() => shippingPreviewAvailable.value ? Math.max(0, shippingPolicy.value.free_shipping_threshold - cartStore.selectedTotalPrice) : 0)
const policyFeeLabel = computed(() => shippingPreviewAvailable.value ? formatCurrency(shippingPolicy.value.base_fee_per_physical_vendor) : '')

const vendorShippingBreakdown = computed(() => {
  const isAutoFreeship = hasAutoFreeShipping.value
  return cartStore.selectedGroupedItems.map(group => {
    const hasPhysical = group.items.some(item => item.book.type !== 'ebook')
    const fee = shippingPreviewAvailable.value ? ((!hasPhysical || isAutoFreeship) ? 0 : shippingPolicy.value.base_fee_per_physical_vendor) : null
    return {
      vendorName: group.vendorName,
      fee,
      isFreeship: fee === 0,
      previewAvailable: shippingPreviewAvailable.value,
      hasPhysical
    }
  })
})

const totalShippingFee = computed(() => {
  return vendorShippingBreakdown.value.reduce((sum, v) => sum + (v.fee || 0), 0)
})

const finalTotal = computed(() => {
  return calculateCheckoutPreviewTotal({
    subtotal: cartStore.selectedTotalPrice,
    shippingFee: totalShippingFee.value,
    couponDiscount: appliedCoupon.value?.discount_amount || 0,
    hasPhysicalItems: hasPhysicalBooks.value,
    shippingPolicyAvailable: shippingPreviewAvailable.value
  })
})

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const updateQuantity = (bookId, newQuantity) => {
  cartStore.updateQuantity(bookId, newQuantity)
}

const confirmRemove = (book) => {
  confirm.require({
    message: `Bạn có chắc chắn muốn xóa cuốn "${book.title}" khỏi giỏ hàng?`,
    header: 'Xác nhận xóa',
    icon: 'pi pi-exclamation-triangle text-amber-500',
    acceptClass: 'p-button-danger',
    rejectLabel: 'Hủy',
    rejectClass: 'p-button-text p-button-secondary',
    acceptLabel: 'Xóa',
    accept: () => {
      cartStore.removeFromCart(book.id)
    }
  })
}

const confirmRemoveSelected = () => {
  confirm.require({
    message: `Bạn có chắc chắn muốn xóa ${cartStore.selectedItems.length} sản phẩm đã chọn khỏi giỏ hàng?`,
    header: 'Xác nhận xóa hàng loạt',
    icon: 'pi pi-exclamation-triangle text-amber-500',
    acceptClass: 'p-button-danger',
    rejectLabel: 'Hủy',
    rejectClass: 'p-button-text p-button-secondary',
    acceptLabel: 'Xóa tất cả đã chọn',
    accept: () => {
      cartStore.removeSelected()
      toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Đã xóa sản phẩm khỏi giỏ hàng', life: 3000 })
    }
  })
}

const applyCoupon = async () => {
  if (!couponCode.value.trim()) {
    couponStatus.value = 'Vui lòng nhập mã ưu đãi trước khi áp dụng.'
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập mã giảm giá', life: 3000 })
    return
  }

  isApplyingCoupon.value = true
  couponStatus.value = 'Đang áp dụng mã ưu đãi.'
  const requestedQuoteFingerprint = selectedCartQuoteFingerprint.value
  try {
    const response = await apiClient.post('/api/coupons/apply', {
      code: couponCode.value,
      total_amount: cartStore.selectedTotalPrice,
      items: cartStore.selectedItems.map(item => ({
        id: item.book.id,
        price: item.book.sale_price || item.book.price,
        quantity: item.quantity,
        category_id: item.book.category_id
      }))
    })
    if (!shouldAcceptCouponPreview(requestedQuoteFingerprint, selectedCartQuoteFingerprint.value)) {
      appliedCoupon.value = null
      couponStatus.value = 'Giỏ hàng đã thay đổi. Hãy áp dụng lại mã ưu đãi.'
      return
    }

    appliedCoupon.value = {
      ...readApiData(response.data),
      quoteFingerprint: requestedQuoteFingerprint
    }
    const responsePolicy = appliedCoupon.value?.shipping_policy
    if (Number.isInteger(responsePolicy?.free_shipping_threshold) && Number.isInteger(responsePolicy?.base_fee_per_physical_vendor)) {
      shippingPolicy.value = responsePolicy
    }
    couponStatus.value = response.data.message || 'Đã áp dụng mã ưu đãi.'
    toast.add({ severity: 'success', summary: 'Thành công', detail: response.data.message || 'Đã áp dụng mã giảm giá!', life: 3000 })
  } catch (error) {
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi áp dụng mã'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 3000 })
    appliedCoupon.value = null
    couponStatus.value = msg
  } finally {
    isApplyingCoupon.value = false
  }
}

const goToCheckout = () => {
  if (!authStore.isAuthenticated) {
    router.push({ path: '/login', query: { redirect: '/cart' } })
    return
  }

  if (cartStore.selectedItems.length === 0) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn ít nhất 1 sản phẩm để thanh toán!', life: 3000 })
    return
  }
  step.value = 2
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const focusPaymentDialog = async () => {
  await nextTick()
  paymentCloseButton.value?.focus()
}

const restorePaymentFocus = async () => {
  const focusTarget = returnFocusElement.value
  returnFocusElement.value = null
  await nextTick()
  focusTarget?.focus?.()
}

const openDemoPayment = (payment, orderId) => {
  returnFocusElement.value = document.activeElement
  pendingOrderId.value = orderId
  pendingPayment.value = payment
  focusPaymentDialog()
}

const closeDemoPayment = () => {
  pendingPayment.value = null
  pendingOrderId.value = null
  restorePaymentFocus()
}

const trapPaymentFocus = (event) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    closeDemoPayment()
    return
  }

  if (event.key !== 'Tab' || !paymentDialog.value) return

  const focusable = Array.from(paymentDialog.value.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )).filter(element => element.getClientRects().length > 0)

  if (focusable.length === 0) {
    event.preventDefault()
    paymentDialog.value.focus()
    return
  }

  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

const processCheckout = async () => {
  if (!authStore.isAuthenticated) {
    router.push({ path: '/login', query: { redirect: '/cart' } })
    return
  }

  invalidateStaleCoupon()

  if (hasEbooks.value && !ebookTermsAccepted.value) {
    toast.add({ severity: 'warn', summary: 'Cần xác nhận điều khoản ebook', detail: 'Vui lòng đồng ý điều khoản nội dung số trước khi đặt hàng.', life: 4000 })
    return
  }
  if (hasPhysicalBooks.value && (!shippingData.value.phone || !shippingData.value.shipping_address || !shippingData.value.receiver_name)) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập đầy đủ thông tin giao hàng!', life: 3000 })
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      ...shippingData.value,
      payment_method: paymentMethod.value,
      coupon_code: appliedCoupon.value?.code,
      ebook_terms_accepted: ebookTermsAccepted.value
    }
    const res = await cartStore.checkout(payload)

    const selectedCapability = paymentProviders.value.find(
      provider => provider.id === paymentMethod.value.toLowerCase()
    )

    if (selectedCapability?.mode === 'demo') {
      const firstOrder = res[0]
      if (!firstOrder) throw new Error('Không tìm thấy đơn hàng vừa tạo.')
      const provider = paymentMethod.value.toLowerCase()
      const paymentResponse = await apiClient.post(`/api/payments/${provider}/attempts`, { order_id: firstOrder.id })
      openDemoPayment(paymentResponse.data, firstOrder.id)
      return
    }

    if (paymentMethod.value === 'VNPAY') {
       const firstOrder = res[0]
       if (firstOrder) {
           const vnpayRes = await apiClient.post('/api/vnpay/create', { order_id: firstOrder.id })
           window.location.href = vnpayRes.data.url
           return
       }
    }

    // COD Case
    cartStore.clearSelectedItems()
    router.push({ name: 'checkout-success', query: { order_id: res[0]?.id } })
  } catch (error) {
    console.error(error)
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi thanh toán'
    if (error.response?.status === 401) {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Bạn cần đăng nhập để thanh toán', life: 5000 })
      router.push({ path: '/login', query: { redirect: '/cart' } })
    } else {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 5000 })
    }
  } finally {
    isSubmitting.value = false
  }
}

const completeSimulatedPayment = async () => {
  if (!pendingPayment.value) return
  isCompletingPayment.value = true
  try {
    await apiClient.post(`/api/payments/${pendingPayment.value.provider}/attempts/${pendingPayment.value.transaction_id}/complete`)
    const orderId = pendingOrderId.value
    pendingPayment.value = null
    cartStore.clearSelectedItems()
    router.push({ name: 'checkout-success', query: { order_id: orderId } })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể xác nhận', detail: error.response?.data?.message || error.message, life: 5000 })
  } finally {
    isCompletingPayment.value = false
  }
}
</script>

<style scoped>
.hide-arrows::-webkit-outer-spin-button,
.hide-arrows::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.hide-arrows {
  -moz-appearance: textfield;
  appearance: textfield;
}

/* Scrollbar styling */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.3s ease-out forwards;
}
</style>
