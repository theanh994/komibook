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
          class="inline-flex items-center gap-2 bg-primary text-on-primary px-xl py-md rounded-xl text-base font-bold hover:bg-primary/90 transition-all shadow-md active:scale-95"
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
            <div v-for="group in cartStore.groupedItems" :key="group.vendorId" class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 soft-shadow overflow-hidden">
              <!-- Header Shop -->
              <div class="px-lg py-md bg-surface-container-low/50 flex items-center justify-between border-b border-outline-variant/20">
                <div class="flex items-center gap-3">
                  <span class="material-symbols-outlined text-primary">store</span>
                  <span class="font-bold text-on-surface">{{ group.vendorName }}</span>
                </div>
                <span class="text-xs text-outline font-medium px-md py-1 bg-surface-container-high rounded-full">
                  {{ group.items.length }} sản phẩm
                </span>
              </div>
              
              <!-- Danh sách sách -->
              <div class="divide-y divide-outline-variant/10">
                <div v-for="item in group.items" :key="item.book.id" class="p-lg flex flex-col sm:flex-row gap-lg transition-colors hover:bg-surface-container-low/20">
                  
                  <!-- Hình ảnh -->
                  <div class="w-24 sm:w-28 shrink-0 rounded-xl overflow-hidden shadow-sm aspect-[3/4] relative cursor-pointer group" @click="$router.push(`/book/${item.book.slug}`)">
                    <img v-if="item.book.cover_image" :src="item.book.cover_image" :alt="item.book.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                    <div v-else class="absolute inset-0 flex items-center justify-center bg-surface-container-high">
                      <span class="material-symbols-outlined text-outline text-3xl">image</span>
                    </div>
                  </div>

                  <!-- Info & Actions -->
                  <div class="flex-1 flex flex-col justify-between">
                    <div class="flex justify-between items-start gap-4">
                      <div class="flex-1">
                        <h3 class="text-lg font-bold text-on-surface line-clamp-2 hover:text-primary cursor-pointer transition-colors leading-snug mb-1" @click="$router.push(`/book/${item.book.slug}`)">{{ item.book.title }}</h3>
                        <div class="flex items-center gap-2 mb-2">
                          <span class="text-sm text-on-surface-variant font-medium">{{ item.book.author || 'Đang cập nhật' }}</span>
                          <span class="w-1 h-1 rounded-full bg-outline-variant"></span>
                          <span class="text-xs text-outline uppercase tracking-wider font-bold">{{ item.book.type === 'ebook' ? 'E-book' : 'Sách giấy' }}</span>
                        </div>
                      </div>
                      <button 
                        class="w-10 h-10 rounded-full flex items-center justify-center text-outline hover:text-error hover:bg-error-container/20 transition-all border-none bg-transparent cursor-pointer" 
                        @click="confirmRemove(item.book)"
                        title="Xoá khỏi giỏ"
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
                      <div class="flex items-center gap-1 p-1 bg-surface-container-low rounded-xl border border-outline-variant/20">
                        <button 
                          class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-container-highest text-on-surface-variant transition-all disabled:opacity-30 border-none bg-transparent cursor-pointer" 
                          @click="updateQuantity(item.book.id, item.quantity - 1)" 
                          :disabled="item.quantity <= 1"
                        >
                          <span class="material-symbols-outlined text-[18px]">remove</span>
                        </button>
                        <input 
                          type="number" 
                          :value="item.quantity" 
                          @change="(e) => updateQuantity(item.book.id, parseInt(e.target.value) || 1)" 
                          class="w-10 text-center bg-transparent border-none text-sm font-bold text-on-surface focus:outline-none p-0 hide-arrows" 
                          min="1" 
                        />
                        <button 
                          class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-container-highest text-on-surface-variant transition-all border-none bg-transparent cursor-pointer" 
                          @click="updateQuantity(item.book.id, item.quantity + 1)"
                        >
                          <span class="material-symbols-outlined text-[18px]">add</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Mobile Footer Action -->
            <div class="lg:hidden fixed bottom-0 left-0 right-0 p-lg bg-surface-container-lowest border-t border-outline-variant/30 z-30 flex items-center justify-between shadow-2xl">
              <div>
                <div class="text-xs text-outline font-medium">Tổng tiền</div>
                <div class="text-xl font-black text-primary">{{ formatCurrency(cartStore.totalPrice) }}</div>
              </div>
              <button 
                @click="goToCheckout"
                class="bg-primary text-on-primary px-xl py-md rounded-xl font-bold shadow-md hover:bg-primary/90 transition-all active:scale-95"
              >
                Thanh toán
              </button>
            </div>
          </template>

          <!-- STEP 2: CHECKOUT FORM -->
          <template v-else>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 soft-shadow p-lg md:p-xl space-y-xl">
              
              <!-- Address Section -->
              <section>
                <div class="flex items-center justify-between mb-lg">
                  <h3 class="text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">location_on</span>
                    Địa chỉ nhận hàng
                  </h3>
                  <button @click="step = 1" class="text-sm font-bold text-secondary hover:underline bg-transparent border-none cursor-pointer">Sửa giỏ hàng</button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                  <div class="space-y-2">
                    <label class="text-sm font-bold text-on-surface-variant ml-1">Sổ địa chỉ</label>
                    <Select v-model="selectedAddress" :options="addresses" optionLabel="address" placeholder="Chọn địa chỉ đã lưu..." class="w-full !rounded-xl !border-outline-variant/40" @change="onAddressSelect">
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
                    <label class="text-sm font-bold text-on-surface-variant ml-1">Tên người nhận</label>
                    <div class="relative">
                      <InputText v-model="shippingData.receiver_name" placeholder="Nhập họ và tên..." class="w-full !pl-10 !rounded-xl !border-outline-variant/40" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">person</span>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <label class="text-sm font-bold text-on-surface-variant ml-1">Số điện thoại</label>
                    <div class="relative">
                      <InputText v-model="shippingData.phone" placeholder="Nhập số điện thoại..." class="w-full !pl-10 !rounded-xl !border-outline-variant/40" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">phone</span>
                    </div>
                  </div>

                  <div class="space-y-2 md:col-span-2">
                    <label class="text-sm font-bold text-on-surface-variant ml-1">Địa chỉ chi tiết</label>
                    <div class="relative">
                      <InputText v-model="shippingData.shipping_address" placeholder="Số nhà, tên đường, phường/xã, quận/huyện..." class="w-full !pl-10 !rounded-xl !border-outline-variant/40" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">home</span>
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
                  <div 
                    @click="paymentMethod = 'COD'"
                    :class="['p-lg rounded-2xl border-2 cursor-pointer transition-all flex items-center gap-md', paymentMethod === 'COD' ? 'border-primary bg-primary-container/5 shadow-sm' : 'border-outline-variant/20 hover:border-outline-variant/60']"
                  >
                    <div :class="['w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0', paymentMethod === 'COD' ? 'border-primary' : 'border-outline']">
                      <div v-if="paymentMethod === 'COD'" class="w-3 h-3 rounded-full bg-primary"></div>
                    </div>
                    <div class="flex-1">
                      <div class="font-bold text-on-surface">Thanh toán khi nhận hàng (COD)</div>
                      <div class="text-xs text-on-surface-variant">Thanh toán bằng tiền mặt khi giao hàng</div>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-primary/40">local_shipping</span>
                  </div>

                  <div 
                    @click="paymentMethod = 'VNPAY'"
                    :class="['p-lg rounded-2xl border-2 cursor-pointer transition-all flex items-center gap-md', paymentMethod === 'VNPAY' ? 'border-primary bg-primary-container/5 shadow-sm' : 'border-outline-variant/20 hover:border-outline-variant/60']"
                  >
                    <div :class="['w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0', paymentMethod === 'VNPAY' ? 'border-primary' : 'border-outline']">
                      <div v-if="paymentMethod === 'VNPAY'" class="w-3 h-3 rounded-full bg-primary"></div>
                    </div>
                    <div class="flex-1">
                      <div class="font-bold text-on-surface">Ví điện tử / Thẻ ATM (VNPAY)</div>
                      <div class="text-xs text-on-surface-variant">Thanh toán qua cổng VNPAY an toàn</div>
                    </div>
                    <span class="material-symbols-outlined text-3xl text-primary/40">account_balance_wallet</span>
                  </div>
                </div>
              </section>

              <!-- Coupon -->
              <section class="pt-xl border-t border-outline-variant/10">
                <h3 class="text-lg font-bold text-on-surface mb-lg flex items-center gap-2">
                  <span class="material-symbols-outlined text-primary">sell</span>
                  Mã giảm giá
                </h3>
                <div class="max-w-[28rem] flex gap-2">
                  <div class="relative flex-1">
                    <InputText v-model="couponCode" placeholder="Nhập mã ưu đãi..." class="w-full !pl-10 !rounded-xl !border-outline-variant/40" />
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">confirmation_number</span>
                  </div>
                  <Button label="Áp dụng" class="!px-6 !rounded-xl !bg-primary-container !text-on-primary-container !border-none font-bold" :loading="isApplyingCoupon" @click="applyCoupon" />
                </div>
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
                <span>Tổng tiền sản phẩm</span>
                <span class="font-bold text-on-surface">{{ formatCurrency(cartStore.totalPrice) }}</span>
              </div>
              <div class="flex justify-between text-sm text-on-surface-variant" v-if="appliedCoupon">
                <span>Giảm giá</span>
                <span class="font-bold text-emerald-600">-{{ formatCurrency(appliedCoupon.discount_amount) }}</span>
              </div>
              <div class="flex justify-between text-sm text-on-surface-variant">
                <span>Phí vận chuyển</span>
                <span class="font-bold text-on-surface text-emerald-600">Miễn phí</span>
              </div>
            </div>

            <div class="pt-lg border-t border-outline-variant/20 mb-xl">
              <div class="flex justify-between items-center">
                <span class="text-base font-bold text-on-surface">Tổng cộng</span>
                <div class="text-right">
                  <div class="text-3xl font-black text-primary leading-none mb-1">{{ formatCurrency(finalTotal) }}</div>
                  <div class="text-[10px] text-outline font-medium italic">(Đã bao gồm VAT nếu có)</div>
                </div>
              </div>
            </div>

            <template v-if="step === 1">
              <button 
                @click="goToCheckout"
                class="w-full py-4 bg-primary text-on-primary rounded-xl text-lg font-bold hover:bg-primary/90 transition-all shadow-md active:scale-[0.98] flex items-center justify-center gap-2"
              >
                Tiến hành thanh toán
                <span class="material-symbols-outlined">arrow_forward</span>
              </button>
            </template>
            <template v-else>
              <button 
                @click="processCheckout"
                :disabled="isSubmitting"
                class="w-full py-4 bg-primary text-on-primary rounded-xl text-lg font-bold hover:bg-primary/90 transition-all shadow-md active:scale-[0.98] flex items-center justify-center gap-2 disabled:opacity-50"
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
                class="w-full mt-4 py-3 bg-transparent text-outline rounded-xl text-sm font-bold hover:bg-surface-container-high transition-all border-none cursor-pointer"
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
</template>

<script setup>
import { useCartStore } from '@/stores/cart'
import { useRouter, useRoute } from 'vue-router'
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useConfirm } from "primevue/useconfirm"
import { useToast } from "primevue/usetoast"

const cartStore = useCartStore()
const router = useRouter()
const route = useRoute()
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
const addresses = ref([])
const selectedAddress = ref(null)

const couponCode = ref('')
const isApplyingCoupon = ref(false)
const appliedCoupon = ref(null)

onMounted(() => {
  fetchAddresses()
  
  // Handle VNPAY Return
  if (route.query.vnp_ResponseCode === '00') {
    const orderId = route.query.vnp_TxnRef?.split('_')[0] // Assuming TxnRef is orderId_timestamp
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Thanh toán VNPAY hoàn tất!', life: 3000 })
    cartStore.clearCart()
    router.push({ name: 'checkout-success', query: { order_id: orderId } })
  } else if (route.query.vnp_ResponseCode) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Thanh toán không thành công hoặc bị hủy.', life: 5000 })
  }
})

const fetchAddresses = async () => {
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

const finalTotal = computed(() => {
  let total = cartStore.totalPrice
  if (appliedCoupon.value && appliedCoupon.value.discount_amount) {
    total -= appliedCoupon.value.discount_amount
  }
  return total > 0 ? total : 0
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

const applyCoupon = async () => {
  if (!couponCode.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập mã giảm giá', life: 3000 })
    return
  }

  isApplyingCoupon.value = true
  try {
    const response = await apiClient.post('/api/coupons/apply', {
      code: couponCode.value,
      total_amount: cartStore.totalPrice,
      items: cartStore.items.map(item => ({
        id: item.book.id,
        price: item.book.sale_price || item.book.price,
        quantity: item.quantity,
        category_id: item.book.category_id
      }))
    })
    appliedCoupon.value = response.data.data
    toast.add({ severity: 'success', summary: 'Thành công', detail: response.data.message || 'Đã áp dụng mã giảm giá!', life: 3000 })
  } catch (error) {
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi áp dụng mã'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 3000 })
    appliedCoupon.value = null
  } finally {
    isApplyingCoupon.value = false
  }
}

const goToCheckout = () => {
  step.value = 2
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const processCheckout = async () => {
  if (!shippingData.value.phone || !shippingData.value.shipping_address || !shippingData.value.receiver_name) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập đầy đủ thông tin giao hàng!', life: 3000 })
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      ...shippingData.value,
      payment_method: paymentMethod.value,
      coupon_code: appliedCoupon.value?.code
    }
    const res = await cartStore.checkout(payload)
    
    if (paymentMethod.value === 'VNPAY') {
       const firstOrder = res.data[0];
       if (firstOrder) {
           const vnpayRes = await apiClient.post('/api/vnpay/create', { order_id: firstOrder.id })
           cartStore.clearCart() 
           window.location.href = vnpayRes.data.url
           return
       }
    }

    // COD Case
    cartStore.clearCart()
    router.push({ name: 'checkout-success', query: { order_id: res.data[0]?.id } })
  } catch (error) {
    console.error(error)
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi thanh toán'
    if (error.response?.status === 401) {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Bạn cần đăng nhập để thanh toán', life: 5000 })
      router.push('/login')
    } else {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 5000 })
    }
  } finally {
    isSubmitting.value = false
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
