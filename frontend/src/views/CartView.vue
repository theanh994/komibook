<template>
  <div class="min-h-screen bg-slate-50 py-8 px-4">
    <div class="max-w-7xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-6">Giỏ hàng của bạn</h1>

      <!-- EMPTY STATE -->
      <div v-if="cartStore.items.length === 0" class="bg-white rounded-xl border border-slate-200/60 shadow-sm p-12 text-center">
        <div class="w-24 h-24 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-6">
          <i class="pi pi-shopping-cart text-4xl text-slate-300"></i>
        </div>
        <h2 class="text-xl font-semibold text-slate-900 mb-2">Giỏ hàng của bạn đang trống</h2>
        <p class="text-slate-500 mb-8">Hãy khám phá hàng ngàn cuốn sách hay đang chờ bạn tại KomiBook.</p>
        <Button label="Tiếp tục mua sắm" icon="pi pi-shopping-bag" class="p-button-primary bg-gradient-to-b from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 shadow-sm px-6 py-2.5 rounded-lg" @click="$router.push('/')" />
      </div>

      <!-- CART CONTENT -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Cột trái (Danh sách sản phẩm theo từng Shop) -->
        <div class="lg:col-span-2 space-y-6">
          <div v-for="group in cartStore.groupedItems" :key="group.vendorId" class="bg-white rounded-xl border border-slate-200/60 shadow-sm overflow-hidden">
            <!-- Header Shop -->
            <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
              <i class="pi pi-shop text-indigo-500 text-lg"></i>
              <span class="font-semibold text-slate-900">{{ group.vendorName }}</span>
            </div>
            
            <!-- Danh sách sách trong Shop -->
            <ul class="divide-y divide-slate-100">
              <li v-for="item in group.items" :key="item.book.id" class="p-5 flex flex-col sm:flex-row gap-5">
                
                <!-- Hình ảnh Sách -->
                <div class="w-20 sm:w-24 shrink-0 rounded-lg overflow-hidden bg-slate-100 border border-slate-200/50 aspect-[3/4] relative cursor-pointer" @click="$router.push(`/book/${item.book.slug}`)">
                  <img v-if="item.book.cover_image" :src="item.book.cover_image" :alt="item.book.title" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" />
                  <div v-else class="absolute inset-0 flex items-center justify-center">
                    <i class="pi pi-image text-slate-300 text-xl"></i>
                  </div>
                </div>

                <!-- Thông tin sách & Thao tác -->
                <div class="flex-1 flex flex-col justify-between">
                  <!-- Tên & Xóa -->
                  <div class="flex justify-between items-start gap-4">
                    <div>
                      <h3 class="text-base font-semibold text-slate-900 line-clamp-2 hover:text-indigo-600 cursor-pointer transition-colors" @click="$router.push(`/book/${item.book.slug}`)">{{ item.book.title }}</h3>
                      <p class="text-sm text-slate-500 mt-1">{{ item.book.author || 'Đang cập nhật' }}</p>
                    </div>
                    <button class="text-slate-400 hover:text-rose-500 transition-colors p-2 -mr-2 bg-transparent border-none" @click="confirmRemove(item.book)" title="Xoá khỏi giỏ">
                      <i class="pi pi-trash text-lg"></i>
                    </button>
                  </div>

                  <!-- Giá & Số lượng -->
                  <div class="flex justify-between items-end mt-4">
                    <div class="flex flex-col gap-0.5">
                      <span class="text-base font-bold text-indigo-600">
                        {{ formatCurrency(item.book.sale_price || item.book.price) }}
                      </span>
                      <span v-if="item.book.sale_price && item.book.price > item.book.sale_price" class="text-xs text-slate-400 font-normal line-through">
                        {{ formatCurrency(item.book.price) }}
                      </span>
                    </div>

                    <!-- Tăng/Giảm -->
                    <div class="flex items-center border border-slate-200/80 rounded-full bg-slate-50/50 overflow-hidden p-0.5">
                      <button 
                        class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white hover:shadow-sm text-slate-500 transition-all disabled:opacity-50 disabled:hover:bg-transparent disabled:hover:shadow-none border-none bg-transparent cursor-pointer" 
                        @click="updateQuantity(item.book.id, item.quantity - 1)" 
                        :disabled="item.quantity <= 1"
                      >
                        <i class="pi pi-minus text-[10px]"></i>
                      </button>
                      <input 
                        type="number" 
                        :value="item.quantity" 
                        @change="(e) => updateQuantity(item.book.id, parseInt(e.target.value) || 1)" 
                        class="w-10 text-center bg-transparent border-none text-sm font-semibold text-slate-900 focus:outline-none focus:ring-0 p-0 hide-arrows" 
                        min="1" 
                      />
                      <button 
                        class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white hover:shadow-sm text-slate-500 transition-all border-none bg-transparent cursor-pointer" 
                        @click="updateQuantity(item.book.id, item.quantity + 1)"
                      >
                        <i class="pi pi-plus text-[10px]"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Cột phải (Tóm tắt đơn hàng) -->
        <div class="lg:col-span-1">
          <div class="bg-white rounded-xl border border-slate-200/60 shadow-sm p-6 sticky top-24">
            <h2 class="text-lg font-semibold text-slate-900 tracking-tight mb-5">Tóm tắt đơn hàng</h2>
            
            <div class="space-y-4 mb-5 border-b border-slate-100 pb-5">
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">Số điện thoại</label>
                <InputText v-model="shippingData.phone" placeholder="Nhập số điện thoại..." class="w-full text-sm !rounded-lg" />
              </div>
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">Địa chỉ giao hàng</label>
                <InputText v-model="shippingData.shipping_address" placeholder="Nhập địa chỉ nhận hàng..." class="w-full text-sm !rounded-lg" />
              </div>
            </div>

            <div class="space-y-4 mb-5 border-b border-slate-100 pb-5">
              <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">Mã giảm giá</label>
                <div class="flex gap-2">
                  <InputText v-model="couponCode" placeholder="Nhập mã giảm giá..." class="flex-1 text-sm !rounded-lg" />
                  <Button label="Áp dụng" class="!px-4 !rounded-lg" :loading="isApplyingCoupon" @click="applyCoupon" />
                </div>
                <div v-if="appliedCoupon" class="text-xs text-emerald-600 font-medium mt-1 flex items-center gap-1">
                  <i class="pi pi-check-circle"></i> Đã áp dụng mã: {{ appliedCoupon.code }} (-{{ formatCurrency(appliedCoupon.discount_amount) }})
                </div>
              </div>
            </div>

            <div class="space-y-3 mb-5 border-b border-slate-100 pb-5">
              <div class="flex justify-between text-sm text-slate-600">
                <span>Tổng phụ ({{ cartStore.totalItems }} sản phẩm)</span>
                <span class="font-medium text-slate-900">{{ formatCurrency(cartStore.totalPrice) }}</span>
              </div>
              <div class="flex justify-between text-sm text-slate-600" v-if="appliedCoupon">
                <span>Giảm giá</span>
                <span class="font-medium text-emerald-600">-{{ formatCurrency(appliedCoupon.discount_amount) }}</span>
              </div>
              <div class="flex justify-between text-sm text-slate-600">
                <span>Phí vận chuyển</span>
                <span class="font-medium text-slate-900">0 đ</span>
              </div>
            </div>

            <div class="flex justify-between items-center mb-6">
              <span class="text-base font-semibold text-slate-900">Tổng cộng</span>
              <span class="text-2xl font-bold text-indigo-600">{{ formatCurrency(finalTotal) }}</span>
            </div>

            <Button label="Tiến hành thanh toán" :loading="isSubmitting" class="w-full !p-3 !text-base !font-bold bg-gradient-to-b from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 shadow-sm border-none !rounded-xl" @click="checkout" />
            
            <p class="text-xs text-center text-slate-400 mt-4 leading-relaxed">
              Vui lòng nhập đầy đủ địa chỉ và số điện thoại để thanh toán.
            </p>
          </div>
        </div>

      </div>
    </div>
    
    <!-- Confirm Dialog từ PrimeVue -->
    <ConfirmDialog></ConfirmDialog>
  </div>
</template>

<script setup>
import { useCartStore } from '@/stores/cart'
import { useRouter } from 'vue-router'
import { ref, computed } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useConfirm } from "primevue/useconfirm"
import { useToast } from "primevue/usetoast"
import ConfirmDialog from 'primevue/confirmdialog'

const cartStore = useCartStore()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()

const isSubmitting = ref(false)
const shippingData = ref({
  phone: '',
  shipping_address: ''
})

const couponCode = ref('')
const isApplyingCoupon = ref(false)
const appliedCoupon = ref(null)

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
      total_amount: cartStore.totalPrice
    })
    appliedCoupon.value = response.data.data
    toast.add({ severity: 'success', summary: 'Thành công', detail: response.data.message || 'Đã áp dụng mã giảm giá!', life: 3000 })
  } catch (error) {
    console.error(error)
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi áp dụng mã'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 3000 })
    appliedCoupon.value = null
  } finally {
    isApplyingCoupon.value = false
  }
}

const checkout = async () => {
  if (!shippingData.value.phone || !shippingData.value.shipping_address) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập đủ SĐT và địa chỉ!', life: 3000 })
    return
  }

  isSubmitting.value = true
  try {
    await cartStore.checkout(shippingData.value)
    
    // Thành công
    cartStore.clearCart()
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đặt hàng thành công!', life: 3000 })
    
    // Redirect về trang chủ (tạm thời về home nếu chưa có trang lịch sử)
    router.push('/')
  } catch (error) {
    console.error(error)
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi thanh toán'
    // Xử lý lỗi validation từ Laravel nếu có
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
/* Ẩn mũi tên mặc định của input type="number" */
.hide-arrows::-webkit-outer-spin-button,
.hide-arrows::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.hide-arrows {
  -moz-appearance: textfield;
  appearance: textfield;
}
</style>
