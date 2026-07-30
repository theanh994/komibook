<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const policies = ref({})
const policyLoading = ref(false)
const policyLoadFailed = ref(false)

const pages = {
  terms: {
    eyebrow: 'Điều khoản sử dụng',
    title: 'Nguyên tắc sử dụng KomiBook',
    summary: 'Phiên bản nội dung 1.0 · Cập nhật ngày 29/07/2026',
    intro: 'Điều khoản này mô tả cách sử dụng tài khoản, nội dung và các luồng thương mại hiện có trên KomiBook.',
    sections: [
      ['Tài khoản và bảo mật', 'Bạn chịu trách nhiệm bảo vệ mật khẩu, mã OTP và phiên đăng nhập. Không chia sẻ thông tin xác thực hoặc sử dụng tài khoản của người khác.'],
      ['Mua sách và thanh toán', 'Giá, định dạng, nguồn gốc và chính sách áp dụng được hiển thị trước khi đặt hàng. Đơn chỉ được coi là thanh toán khi hệ thống ghi nhận trạng thái hợp lệ.'],
      ['Ebook và quyền đọc', 'Ebook là quyền truy cập nội dung số, không phải quyền sở hữu hay phát tán tệp. Quyền đọc và phiên bản khả dụng tuân theo entitlement của đơn hàng.'],
      ['Nội dung do người dùng cung cấp', 'Đánh giá, bài viết, bản thảo và tài liệu tải lên phải hợp pháp, đúng quyền sở hữu và không xâm phạm quyền của người khác.'],
      ['Thay đổi và gián đoạn', 'Tính năng có thể được điều chỉnh để bảo đảm an toàn và tính đúng đắn. KomiBook ghi rõ khi chức năng chưa khả dụng thay vì mô phỏng kết quả.'],
    ],
    links: [
      ['Chính sách bảo mật', '/privacy'],
      ['Chính sách ebook', '/policies/ebooks'],
      ['Sách cũ và hoàn tiền', '/policies/used-books'],
    ],
  },
  privacy: {
    eyebrow: 'Chính sách bảo mật',
    title: 'Dữ liệu được dùng đúng mục đích',
    summary: 'Phiên bản nội dung 1.0 · Cập nhật ngày 29/07/2026',
    intro: 'KomiBook chỉ mô tả các nhóm dữ liệu và mục đích đang phục vụ tài khoản, thương mại, đọc sách và vận hành.',
    sections: [
      ['Dữ liệu tài khoản', 'Tên, email, trạng thái xác minh và thông tin hồ sơ được dùng để đăng nhập, phân quyền và bảo vệ tài khoản.'],
      ['Dữ liệu giao dịch', 'Đơn hàng, chính sách snapshot, lịch sử thanh toán và hoàn tiền được lưu để thực hiện hợp đồng, đối soát và xử lý tranh chấp.'],
      ['Dữ liệu đọc và gợi ý', 'Tiến độ đọc, tủ sách, yêu thích và thể loại quan tâm có thể hỗ trợ trải nghiệm cá nhân hóa. Chỉ số chưa có nguồn thật không được dựng giả.'],
      ['Địa chỉ người bán sách cũ', 'Địa chỉ gửi hàng đã xác minh là dữ liệu riêng tư dùng cho xử lý sách cũ; không được hiển thị cho khách hàng.'],
      ['Chia sẻ và an toàn', 'Dữ liệu chỉ được cung cấp cho bên tham gia xử lý dịch vụ trong phạm vi cần thiết hoặc khi pháp luật yêu cầu. Không gửi mật khẩu hay OTP qua ticket hỗ trợ.'],
    ],
    links: [
      ['Điều khoản sử dụng', '/terms'],
      ['Liên hệ hỗ trợ', '/contact'],
    ],
  },
  ebooks: {
    eyebrow: 'Chính sách ebook',
    title: 'Mua bản mới nhất, giữ quyền đọc từ phiên bản đã mua',
    policyKey: 'ebook_non_returnable',
    intro: 'Chính sách này được tham chiếu trực tiếp khi giỏ hàng có ebook và được snapshot vào đơn hàng.',
    sections: [
      ['Phiên bản tại thời điểm mua', 'Khách hàng luôn mua phiên bản ebook mới nhất đang phát hành tại thời điểm đặt hàng; không có lựa chọn mua một bản cũ hơn.'],
      ['Không trả lại nội dung số', 'Ebook không được đưa vào luồng trả hàng sau khi mua. Khách phải xác nhận điều khoản nội dung số trước khi đặt đơn có ebook.'],
      ['Cập nhật sau khi mua', 'Khi ebook phát hành bản mới, người mua vẫn giữ bản tại thời điểm mua và được chọn đọc các phiên bản từ bản đã mua trở về sau trong trình đọc.'],
      ['Giới hạn sử dụng', 'Quyền đọc gắn với tài khoản và đơn hàng hợp lệ. Việc sao chép, chia sẻ trái phép hoặc vượt cơ chế bảo vệ nội dung không được phép.'],
    ],
    links: [
      ['Điều khoản sử dụng', '/terms'],
      ['Bản quyền và hàng giả', '/policies/copyright'],
    ],
  },
  usedBooks: {
    eyebrow: 'Sách cũ, trả hàng và hoàn tiền',
    title: 'Trả hàng chỉ áp dụng cho sách cũ đủ điều kiện',
    policyKey: 'used_book_return',
    intro: 'Quyền trả hàng được xác định từ policy snapshot của từng dòng đơn, không suy đoán từ dữ liệu sản phẩm đã thay đổi.',
    sections: [
      ['Phạm vi áp dụng', 'Luồng trả hàng hiện dành cho sách cũ vật lý có provenance phù hợp và còn trong thời hạn chính sách của dòng đơn.'],
      ['Tình trạng và bằng chứng', 'Khách cần mô tả lý do, tình trạng thực tế và cung cấp bằng chứng khi được yêu cầu. Số lượng yêu cầu không được vượt số lượng đã mua.'],
      ['Trách nhiệm của người cung cấp', 'Người cung cấp sách cũ chịu trách nhiệm về mô tả, tình trạng và tính xác thực của cuốn sách.'],
      ['Sách giả và tranh chấp', 'Nếu nghi ngờ hàng giả, khách có thể mở tranh chấp và gửi bằng chứng. Quyết định, hoàn tiền và khôi phục tồn kho được xử lý theo trạng thái có dấu vết.'],
      ['Bảo mật địa chỉ', 'Địa chỉ fulfillment đã đăng ký được dùng nội bộ để xử lý hàng hóa và không được tiết lộ cho khách hàng.'],
    ],
    links: [
      ['Bản quyền và hàng giả', '/policies/copyright'],
      ['Gửi yêu cầu hỗ trợ', '/support'],
    ],
  },
  copyright: {
    eyebrow: 'Bản quyền và hàng giả',
    title: 'Tôn trọng quyền tác giả và tính xác thực của sách',
    summary: 'Phiên bản nội dung 1.0 · Cập nhật ngày 29/07/2026',
    intro: 'Trang này mô tả nguyên tắc vận hành của KomiBook; không thay thế tư vấn pháp lý cho một tranh chấp cụ thể.',
    sections: [
      ['Trách nhiệm khi xuất bản', 'Người đăng tác phẩm hoặc tài liệu phải có quyền sử dụng nội dung, hình ảnh, bản dịch và các tài sản liên quan.'],
      ['Chủ thể chịu trách nhiệm', 'Nhà bán phải khai báo đúng Nhà xuất bản, Nhà cung cấp và đơn vị chịu trách nhiệm. Các quyết định kiểm duyệt được lưu dấu vết.'],
      ['Sách cũ và hàng giả', 'Người cung cấp chịu trách nhiệm về tính xác thực. Sách nghi giả có thể bị tranh chấp, gỡ khỏi lưu thông và xử lý theo bằng chứng.'],
      ['Báo cáo vi phạm', 'Hãy gửi ticket hỗ trợ với tác phẩm liên quan, mô tả quyền của bạn và bằng chứng phù hợp. Không công khai giấy tờ riêng tư trên trang sản phẩm.'],
      ['Xử lý thận trọng', 'KomiBook có thể hạn chế hiển thị trong thời gian xem xét nhưng không tự nhận là cơ quan phân xử quyền sở hữu trí tuệ.'],
    ],
    links: [
      ['Liên hệ hỗ trợ', '/contact'],
      ['Chính sách sách cũ', '/policies/used-books'],
    ],
  },
}

const page = computed(() => pages[route.meta.pageKey] || pages.terms)
const activePolicy = computed(() => page.value.policyKey ? policies.value[page.value.policyKey] : null)
const versionLabel = computed(() => activePolicy.value
  ? `Chính sách hệ thống v${activePolicy.value.version} · Có hiệu lực từ ${new Date(activePolicy.value.active_from).toLocaleDateString('vi-VN')}`
  : page.value.summary)

const loadPolicies = async () => {
  if (!page.value.policyKey) return
  policyLoading.value = true
  policyLoadFailed.value = false
  try {
    const response = await apiClient.get('/api/policies/returns')
    policies.value = response.data?.data || {}
  } catch {
    policyLoadFailed.value = true
  } finally {
    policyLoading.value = false
  }
}

onMounted(loadPolicies)
</script>

<template>
  <article class="mx-auto w-full max-w-[1080px] px-4 py-10 sm:py-14 md:px-8 lg:py-20">
    <header class="max-w-3xl">
      <p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary">{{ page.eyebrow }}</p>
      <h1 class="mt-3 text-3xl font-black leading-tight text-primary sm:text-4xl lg:text-5xl">{{ page.title }}</h1>
      <p class="mt-5 text-base leading-7 text-on-surface-variant sm:text-lg">{{ page.intro }}</p>
      <p v-if="versionLabel" class="mt-4 inline-flex min-h-11 items-center rounded-full bg-primary/10 px-4 text-sm font-bold text-primary">
        {{ versionLabel }}
      </p>
      <p v-else-if="policyLoading" role="status" class="mt-4 text-sm text-on-surface-variant">Đang tải phiên bản chính sách...</p>
      <p v-if="policyLoadFailed" role="status" class="mt-3 text-sm text-amber-800">
        Chưa tải được số phiên bản hệ thống; nội dung công khai bên dưới vẫn khả dụng.
      </p>
    </header>

    <aside
      v-if="activePolicy"
      class="mt-8 rounded-2xl border border-primary/20 bg-primary/5 p-5 text-sm leading-6 text-on-surface sm:p-6"
    >
      <strong>Điều khoản đang hiệu lực:</strong> {{ activePolicy.terms }}
      <span v-if="activePolicy.return_window_days" class="mt-2 block">
        Thời hạn yêu cầu: {{ activePolicy.return_window_days }} ngày.
      </span>
    </aside>

    <div class="mt-10 space-y-4">
      <section
        v-for="([title, body], index) in page.sections"
        :key="title"
        class="grid gap-3 rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5 sm:grid-cols-[44px_1fr] sm:p-6"
      >
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-container-high font-black text-primary" aria-hidden="true">
          {{ index + 1 }}
        </span>
        <div>
          <h2 class="text-lg font-bold text-on-surface">{{ title }}</h2>
          <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ body }}</p>
        </div>
      </section>
    </div>

    <nav class="mt-8 flex flex-wrap gap-3" aria-label="Chính sách liên quan">
      <RouterLink
        v-for="([label, to]) in page.links"
        :key="to"
        :to="to"
        class="inline-flex min-h-11 items-center rounded-xl border border-primary/30 px-4 py-2 font-bold text-primary transition hover:bg-primary/5"
      >
        {{ label }}
        <span class="material-symbols-outlined ml-1 text-lg" aria-hidden="true">arrow_forward</span>
      </RouterLink>
    </nav>
  </article>
</template>
