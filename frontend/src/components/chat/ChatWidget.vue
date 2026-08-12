<template>
  <div v-if="authStore.userFetched && authStore.isAuthenticated" class="fixed inset-x-4 bottom-20 z-[60] flex justify-end sm:inset-x-auto sm:bottom-24 sm:right-8" @keydown.esc="chatStore.closeChat()">
    <button
      v-if="!chatStore.isOpen"
      type="button"
      class="relative flex h-16 w-16 cursor-pointer items-center justify-center rounded-full border border-brand-green/40 bg-surface-container-lowest p-1 shadow-elevated transition-[box-shadow,background-color] duration-200 hover:bg-brand-green-container focus-visible:outline-none"
      aria-label="Mở Trợ lý AI KomiBook"
      aria-haspopup="dialog"
      @click="chatStore.toggleChat()"
    >
      <img :src="komiCutoutUrl" alt="" class="h-full w-full object-contain" width="64" height="64" />
      <span class="absolute right-0 top-0 h-4 w-4 rounded-full border-2 border-white bg-brand-green" aria-hidden="true"></span>
    </button>

    <section
      v-else
      ref="panel"
      role="dialog"
      aria-labelledby="komibook-chat-title"
      class="flex h-[min(650px,calc(100dvh-7rem))] w-full max-w-[420px] flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
    >
      <header class="flex items-center justify-between gap-3 bg-brand-green-strong px-4 py-3 text-white">
        <div class="flex min-w-0 items-center gap-3">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-white/40 bg-white/15 p-1">
            <img v-if="chatStore.isAiActive" :src="komiCutoutUrl" alt="" class="h-full w-full object-contain" width="44" height="44" />
            <span v-else class="material-symbols-outlined" aria-hidden="true">support_agent</span>
          </div>
          <div class="min-w-0">
            <h2 id="komibook-chat-title" class="truncate text-base font-bold">{{ chatTitle }}</h2>
            <p class="mt-0.5 flex items-center gap-2 text-sm text-white/85"><span class="h-2.5 w-2.5 rounded-full bg-white" aria-hidden="true"></span>{{ statusText }}</p>
          </div>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <button
            v-if="!chatStore.showConversationList && chatStore.session?.external_ai && (chatStore.externalAi.available || chatStore.hasExternalAiConsent)"
            type="button"
            class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg text-white transition-colors hover:bg-white/15"
            :aria-expanded="showGeminiSettings"
            aria-controls="komibook-gemini-settings"
            aria-label="Cài đặt Google Gemini"
            title="Cài đặt Google Gemini"
            @click="showGeminiSettings = !showGeminiSettings"
          >
            <span class="material-symbols-outlined" aria-hidden="true">privacy_tip</span>
          </button>
          <button type="button" class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg text-white transition-colors hover:bg-white/15" :aria-label="chatStore.showConversationList ? 'Quay lại cuộc trò chuyện' : 'Mở lịch sử trò chuyện'" @click="chatStore.showConversationList ? chatStore.openForTarget(chatStore.targetType, chatStore.vendorId, chatStore.vendorName, chatStore.contextBookId ? { id: chatStore.contextBookId, title: chatStore.contextBookName } : null) : chatStore.openConversationList()">
            <span class="material-symbols-outlined" aria-hidden="true">{{ chatStore.showConversationList ? 'arrow_back' : 'forum' }}</span>
          </button>
          <button ref="closeButton" type="button" class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg text-white transition-colors hover:bg-white/15" aria-label="Đóng cửa sổ trò chuyện" @click="chatStore.closeChat()">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
        </div>
      </header>

      <div
        v-if="showGeminiSettings && !chatStore.showConversationList && chatStore.session?.external_ai"
        id="komibook-gemini-settings"
        class="border-b border-outline-variant bg-surface-container-low px-4 py-3 text-sm text-on-surface"
      >
        <div class="flex items-start gap-3">
          <div class="min-w-0 flex-1">
            <p class="font-bold">Google Gemini</p>
            <p class="mt-1 leading-5 text-on-surface-variant">Chỉ câu hỏi hiện tại và ngữ cảnh công khai liên quan có thể được gửi. Lịch sử trò chuyện và hình ảnh không được gửi.</p>
          </div>
          <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container" aria-label="Đóng cài đặt Google Gemini" @click="showGeminiSettings = false">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
        </div>
        <button
          v-if="chatStore.hasExternalAiConsent"
          type="button"
          class="mt-2 min-h-11 rounded-lg border border-error px-3 text-sm font-bold text-error transition-colors hover:bg-error-container disabled:opacity-50"
          :disabled="chatStore.sending"
          @click="chatStore.setExternalAiConsent(false)"
        >Tắt Google Gemini</button>
        <button
          v-else-if="chatStore.externalAi.available"
          type="button"
          class="mt-2 min-h-11 rounded-lg border border-brand-green-strong px-3 text-sm font-bold text-brand-green-strong transition-colors hover:bg-brand-green-container disabled:opacity-50"
          :disabled="chatStore.sending"
          @click="chatStore.setExternalAiConsent(true)"
        >Bật Google Gemini cho phiên này</button>
      </div>

      <div v-if="!chatStore.showConversationList && chatStore.session && chatStore.isAiActive && showNoticeBanner" class="border-b border-outline-variant/40 bg-brand-green-container px-3 py-1.5 text-xs text-on-brand-green-container transition-all">
        <div class="flex items-center justify-between gap-2">
          <div class="flex min-w-0 items-center gap-1.5 truncate">
            <span class="material-symbols-outlined text-sm shrink-0 text-brand-green-strong">info</span>
            <span class="truncate"><strong class="font-bold">Gợi ý AI tự động:</strong> dựa trên nguồn KomiBook</span>
          </div>
          <div class="flex shrink-0 items-center gap-1.5">
            <button
              v-if="chatStore.isAiActive"
              type="button"
              class="inline-flex items-center gap-1 rounded bg-surface-container-lowest px-2 py-0.5 font-bold text-secondary border border-secondary/40 text-[11px] hover:bg-secondary-fixed cursor-pointer transition-colors"
              :disabled="chatStore.sending"
              title="Gặp nhân viên hỗ trợ"
              @click="chatStore.requestHumanSupport()"
            >
              <span class="material-symbols-outlined text-xs">support_agent</span>
              <span>Gặp tư vấn viên</span>
            </button>
            <button
              type="button"
              class="flex h-5 w-5 items-center justify-center rounded text-on-brand-green-container/70 hover:bg-brand-green/20 hover:text-on-brand-green-container cursor-pointer"
              aria-label="Ẩn thông báo"
              title="Ẩn thông báo"
              @click="showNoticeBanner = false"
            >
              <span class="material-symbols-outlined text-xs">close</span>
            </button>
          </div>
        </div>
        <span v-if="chatStore.contextBookName" class="mt-0.5 block truncate text-[11px] opacity-90">Đang hỏi trong ngữ cảnh: {{ chatStore.contextBookName }}</span>
      </div>

      <div v-if="chatStore.showConversationList" class="flex-1 overflow-y-auto bg-surface-container-low p-4">
        <div class="mb-4 rounded-xl border border-brand-green/30 bg-brand-green-container p-3 text-sm leading-5 text-on-brand-green-container">
          KomiBook dùng một cuộc trò chuyện chung cho AI và tư vấn viên. Mỗi gian hàng có một cuộc trò chuyện riêng và lưu toàn bộ lịch sử.
        </div>
        <div v-if="chatStore.conversationsLoading" class="grid min-h-40 place-items-center text-on-surface-variant" role="status"><i class="pi pi-spin pi-spinner text-xl" aria-hidden="true"></i><span class="sr-only">Đang tải lịch sử</span></div>
        <div v-else class="space-y-2">
          <button type="button" class="flex min-h-20 w-full cursor-pointer items-center gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-3 text-left transition-colors hover:border-brand-green-strong hover:bg-brand-green-container/40" @click="chatStore.openChat()">
            <span class="material-symbols-outlined grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-green-container text-brand-green-strong" aria-hidden="true">support_agent</span>
            <span class="min-w-0 flex-1"><strong class="block truncate text-sm text-on-surface">KomiBook · AI & tư vấn viên</strong><span class="mt-1 block truncate text-sm text-on-surface-variant">{{ platformConversation?.last_message?.message || 'Gợi ý sách, chính sách và hỗ trợ chung' }}</span></span>
            <span class="material-symbols-outlined text-outline" aria-hidden="true">chevron_right</span>
          </button>
          <button v-for="conversation in vendorConversations" :key="conversation.id" type="button" class="flex min-h-20 w-full cursor-pointer items-center gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-3 text-left transition-colors hover:border-secondary hover:bg-secondary-fixed/30" @click="chatStore.selectConversation(conversation)">
            <span class="material-symbols-outlined grid h-11 w-11 shrink-0 place-items-center rounded-full bg-secondary-fixed text-secondary" aria-hidden="true">storefront</span>
            <span class="min-w-0 flex-1"><strong class="block truncate text-sm text-on-surface">{{ conversation.vendor?.shop_name || 'Gian hàng' }}</strong><span class="mt-1 block truncate text-sm text-on-surface-variant">{{ conversation.last_message?.message || 'Chưa có tin nhắn' }}</span><span class="mt-1 block text-xs font-semibold text-outline">{{ conversationStatus(conversation) }}</span></span>
            <span class="material-symbols-outlined text-outline" aria-hidden="true">chevron_right</span>
          </button>
          <p v-if="!vendorConversations.length" class="px-2 py-5 text-center text-sm text-on-surface-variant">Khi bạn hỏi một nhà bán, cuộc trò chuyện riêng với gian hàng sẽ xuất hiện tại đây.</p>
        </div>
      </div>

      <div v-else ref="messageContainer" class="flex-1 space-y-4 overflow-y-auto bg-surface-container-low p-4" role="log" aria-live="polite" aria-relevant="additions text">
        <div v-if="chatStore.loading" class="grid h-full place-items-center" role="status">
          <div class="flex items-center gap-3 text-on-surface-variant"><i class="pi pi-spin pi-spinner text-xl text-primary" aria-hidden="true"></i><span>Đang mở trợ lý…</span></div>
        </div>

        <template v-else>
          <article v-for="message in chatStore.messages" :key="message.id" :class="messageClass(message)">
            <p class="mb-1 text-sm font-semibold text-on-surface-variant">{{ senderLabel(message.sender_type) }}</p>
            <div :class="bubbleClass(message)">
              <a v-if="message.metadata?.attachment" :href="attachmentUrl(message)" target="_blank" rel="noopener" class="mb-2 block overflow-hidden rounded-xl border border-outline-variant/50 bg-surface-container-lowest">
                <img :src="attachmentUrl(message)" :alt="message.metadata.attachment.original_name || 'Hình ảnh trong hội thoại'" class="max-h-64 w-full object-contain" loading="lazy" />
              </a>
              <p class="whitespace-pre-wrap text-base leading-6">
                <template v-for="(part, idx) in parsedMessageParts(message)" :key="idx">
                  <template v-if="part.type === 'text'">
                    <template v-for="(seg, sIdx) in formatTextSegments(part.text)" :key="sIdx">
                      <strong v-if="seg.isBold" class="font-bold text-on-surface">{{ seg.text }}</strong>
                      <span v-else>{{ seg.text }}</span>
                    </template>
                  </template>
                  <RouterLink
                    v-else-if="part.type === 'citation'"
                    :to="part.source.url"
                    class="inline-flex items-center gap-0.5 mx-0.5 rounded border border-brand-green/40 bg-brand-green-container px-1.5 py-0.5 font-mono text-xs font-bold text-brand-green-strong no-underline transition-all hover:border-brand-green-strong hover:bg-brand-green/20 hover:shadow-xs align-baseline"
                    :title="`${part.source.citation} · ${part.source.title}`"
                    @click="chatStore.closeChat()"
                  >
                    <span>[{{ part.source.citation }}]</span>
                    <span class="material-symbols-outlined text-[12px]" aria-hidden="true">open_in_new</span>
                  </RouterLink>
                </template>
              </p>

              <div v-if="message.metadata?.recommended_books?.length" class="mt-3 space-y-2 border-t border-outline-variant/40 pt-3">
                <p class="flex items-center gap-2 text-sm font-bold text-brand-green-strong"><span class="material-symbols-outlined text-lg" aria-hidden="true">menu_book</span>Sách trong catalog đang bán</p>
                <RouterLink v-for="book in message.metadata.recommended_books" :key="book.id" :to="`/books/${book.slug || book.id}`" class="grid min-h-20 grid-cols-[48px_minmax(0,1fr)] gap-3 rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-2 text-on-surface no-underline transition-colors hover:border-brand-green" @click="chatStore.closeChat()">
                  <div class="aspect-[3/4] overflow-hidden rounded-md bg-surface-container">
                    <img v-if="book.cover_image" :src="coverUrl(book.cover_image)" :alt="`Bìa sách ${book.title}`" class="h-full w-full object-cover" loading="lazy" width="48" height="64" />
                    <span v-else class="grid h-full place-items-center material-symbols-outlined text-outline" aria-hidden="true">book</span>
                  </div>
                  <div class="min-w-0">
                    <p class="line-clamp-2 text-sm font-bold">{{ book.title }}</p>
                    <p class="mt-0.5 truncate text-xs text-on-surface-variant">{{ book.author }}</p>
                    <div class="mt-1 flex items-center justify-between gap-2">
                      <span class="text-sm font-bold text-brand-green-strong">{{ formatCurrency(book.display_price ?? book.sale_price ?? book.price) }}</span>
                      <span v-if="book.rating_avg" class="inline-flex items-center gap-0.5 font-bold text-amber-700 text-xs bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200" :title="`Đánh giá trung bình ${book.rating_avg}/5 ⭐ (${book.review_count || 0} nhận xét)`">
                        <span class="material-symbols-outlined text-[13px] fill-1 text-amber-500" aria-hidden="true">star</span>{{ book.rating_avg }}
                      </span>
                    </div>
                  </div>
                </RouterLink>
              </div>

              <div v-if="message.metadata?.recommended_coupons?.length" class="mt-3 space-y-2 border-t border-outline-variant/40 pt-3">
                <p class="flex items-center gap-2 text-sm font-bold text-amber-700"><span class="material-symbols-outlined text-lg" aria-hidden="true">confirmation_number</span>Mã giảm giá khả dụng</p>
                <div v-for="coupon in message.metadata.recommended_coupons" :key="coupon.id" class="flex items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 p-2.5 text-on-surface">
                  <div class="min-w-0">
                    <p class="font-mono text-sm font-bold text-amber-900">Mã: {{ coupon.code }} (Giảm {{ coupon.discount_percent }}%)</p>
                    <p class="text-xs text-amber-800">Đơn từ {{ formatCurrency(coupon.min_order_value || 0) }} · {{ coupon.vendor_name }}</p>
                  </div>
                  <RouterLink to="/cart" class="shrink-0 rounded-lg bg-amber-600 px-2.5 py-1.5 text-xs font-bold text-white no-underline hover:bg-amber-700" @click="chatStore.closeChat()">
                    Dùng ngay
                  </RouterLink>
                </div>
              </div>

              <div v-if="message.metadata?.recommended_orders?.length" class="mt-3 space-y-2 border-t border-outline-variant/40 pt-3">
                <p class="flex items-center gap-2 text-sm font-bold text-sky-800"><span class="material-symbols-outlined text-lg" aria-hidden="true">package_2</span>Đơn hàng gần nhất</p>
                <div v-for="order in message.metadata.recommended_orders" :key="order.id" class="flex flex-col gap-1.5 rounded-xl border border-sky-300 bg-sky-50 p-2.5 text-on-surface">
                  <div class="flex items-center justify-between gap-2">
                    <p class="font-mono text-sm font-bold text-sky-900">#{{ order.order_code }}</p>
                    <span class="rounded-full bg-sky-200 px-2 py-0.5 text-xs font-bold text-sky-900">{{ order.status_label }}</span>
                  </div>
                  <p class="truncate text-xs text-sky-800">{{ order.items_summary }}</p>
                  <div class="flex items-center justify-between text-xs text-sky-900">
                    <span class="font-bold">{{ formatCurrency(order.total_amount) }}</span>
                    <RouterLink to="/profile?tab=orders" class="font-bold text-sky-700 underline" @click="chatStore.closeChat()">Chi tiết</RouterLink>
                  </div>
                </div>
              </div>

              <div v-if="uncitedSources(message).length" class="mt-2.5 border-t border-outline-variant/30 pt-2 text-xs">
                <p class="mb-1.5 flex items-center gap-1 font-bold text-on-surface-variant text-[11px] uppercase tracking-wider">
                  <span class="material-symbols-outlined text-sm text-brand-green-strong" aria-hidden="true">link</span>Nguồn tham khảo khác ({{ uncitedSources(message).length }})
                </p>
                <div class="flex flex-wrap gap-1.5">
                  <RouterLink
                    v-for="source in uncitedSources(message)"
                    :key="`${source.type}-${source.id}`"
                    :to="source.url"
                    class="inline-flex max-w-[210px] items-center gap-1.5 rounded-lg border border-outline-variant/60 bg-surface-container-lowest px-2 py-1 text-xs font-medium text-brand-green-strong no-underline transition-all hover:border-brand-green-strong hover:bg-brand-green-container/40"
                    :title="`${source.citation} · ${source.title}`"
                    @click="chatStore.closeChat()"
                  >
                    <span class="rounded bg-brand-green-container px-1 py-0.2 font-mono text-[10px] font-bold text-brand-green-strong">{{ source.citation }}</span>
                    <span class="truncate">{{ source.title }}</span>
                    <span class="material-symbols-outlined text-xs shrink-0 text-outline" aria-hidden="true">open_in_new</span>
                  </RouterLink>
                </div>
              </div>

              <div v-if="message.metadata?.quick_replies?.length" class="mt-3 flex flex-wrap gap-2">
                <button v-for="reply in message.metadata.quick_replies" :key="reply" type="button" class="min-h-11 cursor-pointer rounded-full border border-brand-green/50 bg-brand-green-container px-3 text-sm font-semibold text-on-brand-green-container transition-colors hover:bg-brand-green/20" @click="handleQuickReply(reply)">{{ reply }}</button>
              </div>

              <div v-if="message.sender_type === 'ai'" class="mt-3 flex items-center gap-2 border-t border-outline-variant/40 pt-2">
                <span class="text-sm text-on-surface-variant">Câu trả lời này hữu ích?</span>
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg border border-outline-variant transition-colors hover:border-brand-green-strong hover:text-brand-green-strong disabled:opacity-50" :class="message.feedback === 'helpful' ? 'bg-brand-green-container text-brand-green-strong' : 'bg-surface-container-lowest text-on-surface-variant'" :disabled="Boolean(chatStore.feedbackPending[message.id])" :aria-pressed="message.feedback === 'helpful'" aria-label="Đánh giá hữu ích" @click="chatStore.submitFeedback(message.id, 'helpful')"><span class="material-symbols-outlined" aria-hidden="true">thumb_up</span></button>
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg border border-outline-variant transition-colors hover:border-secondary hover:text-secondary disabled:opacity-50" :class="message.feedback === 'unhelpful' ? 'bg-secondary-fixed text-secondary' : 'bg-surface-container-lowest text-on-surface-variant'" :disabled="Boolean(chatStore.feedbackPending[message.id])" :aria-pressed="message.feedback === 'unhelpful'" aria-label="Đánh giá chưa hữu ích" @click="chatStore.submitFeedback(message.id, 'unhelpful')"><span class="material-symbols-outlined" aria-hidden="true">thumb_down</span></button>
              </div>
              <p class="mt-2 text-right text-xs opacity-70">{{ formatMessageTime(message.created_at) }}</p>
            </div>
          </article>
          <div v-if="chatStore.sending" class="flex items-center gap-2 text-sm text-on-surface-variant" role="status"><i class="pi pi-spin pi-spinner" aria-hidden="true"></i><span>Đang xử lý…</span></div>
        </template>
      </div>

      <div v-if="chatStore.error" class="border-t border-error/30 bg-error-container px-4 py-3 text-sm text-on-error-container" role="alert">
        <div class="flex items-start gap-2"><span class="material-symbols-outlined text-lg" aria-hidden="true">error</span><p class="flex-1">{{ chatStore.error }}</p></div>
      </div>

      <footer v-if="!chatStore.showConversationList" class="border-t border-outline-variant bg-surface-container-lowest p-3">
        <div v-if="chatStore.canExtendHumanWait" class="mb-2 rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-sm text-on-surface" role="status">
          <p>Phiên sẽ tự trở lại chatbot lúc <strong>{{ autoResumeTime }}</strong> nếu bạn không phản hồi.</p>
          <button type="button" class="mt-2 min-h-11 cursor-pointer rounded-lg border border-brand-green-strong px-3 font-bold text-brand-green-strong transition-colors hover:bg-brand-green-container focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-strong disabled:cursor-not-allowed disabled:opacity-50" :disabled="chatStore.sending" @click="chatStore.extendHumanWait()">
            Tiếp tục chờ tư vấn viên thêm {{ chatStore.session?.human_idle_timeout_minutes || 30 }} phút
          </button>
        </div>
        <div class="mb-2 flex flex-wrap gap-2">
          <button v-if="chatStore.isQueued" type="button" class="min-h-11 cursor-pointer rounded-lg border border-brand-green-strong px-3 text-sm font-bold text-brand-green-strong transition-colors hover:bg-brand-green-container" :disabled="chatStore.sending" @click="chatStore.resumeAi()"><span class="material-symbols-outlined mr-1 align-middle text-lg" aria-hidden="true">smart_toy</span>Quay lại AI</button>
        </div>
        <div v-if="selectedImage" class="mb-2 flex items-center gap-2 rounded-lg border border-brand-green/30 bg-brand-green-container px-3 py-2 text-sm text-on-brand-green-container">
          <span class="material-symbols-outlined text-lg" aria-hidden="true">image</span><span class="min-w-0 flex-1 truncate">{{ selectedImage.name }}</span>
          <button type="button" class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-green/15" aria-label="Bỏ hình ảnh đã chọn" @click="clearSelectedImage"><span class="material-symbols-outlined text-lg" aria-hidden="true">close</span></button>
        </div>
        <form class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-2 transition-colors focus-within:border-brand-green-strong focus-within:ring-1 focus-within:ring-brand-green-strong" @submit.prevent="handleSend">
          <input ref="fileInput" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif" :disabled="chatStore.sending || !chatStore.canSendMessage" @change="handleImageSelection" />
          <label for="komibook-chat-message" class="sr-only">Tin nhắn</label>
          <textarea id="komibook-chat-message" ref="input" v-model="inputMessage" rows="2" maxlength="2000" class="w-full resize-none border-0 bg-transparent px-2 pt-1 text-base text-on-surface placeholder:text-outline focus:outline-none focus:ring-0 min-h-[48px] max-h-36 overflow-y-auto" :disabled="chatStore.sending || !chatStore.canSendMessage" :placeholder="chatStore.isTerminal ? 'Phiên đã kết thúc' : chatStore.isQueued ? 'Đang chờ nhân viên tiếp nhận' : chatStore.canSendMessage ? 'Nhập câu hỏi của bạn…' : 'Phiên chưa sẵn sàng nhận tin nhắn'" @input="resizeInput" @keydown.enter.exact.prevent="handleSend"></textarea>
          <div class="flex items-center justify-between px-1 pt-1 border-t border-outline-variant/30">
            <button type="button" class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg text-on-surface-variant transition-colors hover:bg-brand-green-container hover:text-brand-green-strong disabled:cursor-not-allowed disabled:opacity-50" :disabled="chatStore.sending || !chatStore.canSendMessage" aria-label="Đính kèm hình ảnh" title="Đính kèm hình ảnh" @click="fileInput?.click()"><span class="material-symbols-outlined text-xl" aria-hidden="true">image</span></button>
            <button type="submit" class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg text-brand-green-strong transition-colors hover:bg-brand-green-container disabled:cursor-not-allowed disabled:opacity-40" :disabled="(!inputMessage.trim() && !selectedImage) || chatStore.sending || !chatStore.canSendMessage" aria-label="Gửi tin nhắn" title="Gửi tin nhắn"><span class="material-symbols-outlined text-xl" aria-hidden="true">send</span></button>
          </div>
        </form>
      </footer>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useChatStore } from '@/stores/chatStore'
import { useAuthStore } from '@/stores/auth'

const chatStore = useChatStore()
const authStore = useAuthStore()
const komiCutoutUrl = '/images/komi-cutout.png'
const inputMessage = ref('')
const input = ref(null)
const fileInput = ref(null)
const selectedImage = ref(null)
const messageContainer = ref(null)
const showNoticeBanner = ref(true)
const showGeminiSettings = ref(false)

watch(
  () => authStore.user?.id || null,
  () => chatStore.resetChat(),
)

const chatTitle = computed(() => chatStore.showConversationList ? 'Lịch sử trò chuyện' : chatStore.targetType === 'vendor' ? (chatStore.vendorName || chatStore.session?.vendor?.shop_name || 'Hỗ trợ gian hàng') : 'Trợ lý KomiBook')
const platformConversation = computed(() => chatStore.conversations.find(conversation => conversation.target_type === 'platform'))
const vendorConversations = computed(() => chatStore.conversations.filter(conversation => conversation.target_type === 'vendor'))
const autoResumeTime = computed(() => {
  if (!chatStore.session?.auto_resume_at) return ''
  const deadline = new Date(chatStore.session.auto_resume_at)
  if (Number.isNaN(deadline.getTime())) return 'thời điểm đã được hệ thống xác nhận'
  return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit' }).format(deadline)
})
const statusText = computed(() => {
  if (chatStore.showConversationList) return 'Các cuộc trò chuyện đã lưu'
  if (!chatStore.session) return 'Đang khởi tạo phiên hỗ trợ'
  if (chatStore.isTerminal) return 'Phiên đã hoàn tất'
  if (chatStore.isHumanActive) return 'Nhân viên đang hỗ trợ'
  if (chatStore.isQueued) return 'Đang chờ nhân viên tiếp nhận'
  if (chatStore.isAiActive) return 'Trợ lý AI tự động'
  return 'Trạng thái phiên chưa xác định'
})
const conversationStatus = conversation => {
  if (!conversation) return 'Trạng thái phiên chưa xác định'
  const unassigned = conversation.assigned_user === null
  const assigned = conversation.assigned_user != null
  if (conversation.status === 'open' && conversation.responder_mode === 'ai' && unassigned) return 'Trợ lý AI đang hỗ trợ'
  if (conversation.status === 'queued' && conversation.responder_mode === 'human' && unassigned) return 'Đang chờ gian hàng tiếp nhận'
  if (['assigned', 'waiting_customer'].includes(conversation.status) && conversation.responder_mode === 'human' && assigned) return 'Nhân viên đang hỗ trợ'
  if (['resolved', 'closed'].includes(conversation.status) && conversation.responder_mode === 'human' && assigned) return 'Đã hoàn tất · có thể mở lại'
  return 'Trạng thái phiên chưa xác định'
}

const scrollToBottom = async () => {
  await nextTick()
  if (messageContainer.value) {
    messageContainer.value.scrollTop = messageContainer.value.scrollHeight
  }
  setTimeout(() => {
    if (messageContainer.value) {
      messageContainer.value.scrollTop = messageContainer.value.scrollHeight
    }
  }, 60)
}

watch(() => chatStore.isOpen, async isOpen => {
  if (!isOpen) {
    showGeminiSettings.value = false
    return
  }
  await nextTick()
  input.value?.focus()
  await scrollToBottom()
})

watch(() => chatStore.session?.id, async () => {
  showGeminiSettings.value = false
  await scrollToBottom()
})

watch(() => chatStore.messages.length, async () => {
  await scrollToBottom()
})

watch(() => chatStore.loading, async loading => {
  if (!loading) await scrollToBottom()
})

const messageClass = message => message.sender_type === 'customer' ? 'flex flex-col items-end' : message.sender_type === 'system' ? 'flex flex-col items-center' : 'flex flex-col items-start'
const bubbleClass = message => [
  'max-w-[88%] rounded-2xl border px-4 py-3 shadow-sm',
  message.sender_type === 'customer' ? 'rounded-br-md border-brand-green-strong bg-brand-green-strong text-white' :
    message.sender_type === 'system' ? 'border-outline-variant bg-surface-container text-on-surface-variant' :
      'rounded-bl-md border-outline-variant bg-surface-container-lowest text-on-surface',
]
const senderLabel = type => ({ customer: 'Bạn', ai: 'Trợ lý AI', vendor: 'Nhân viên gian hàng', admin: 'Tư vấn viên KomiBook', system: 'Hệ thống' })[type] || 'Hệ thống'
const formatCurrency = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value) || 0)
const coverUrl = path => /^https?:\/\//i.test(path) || path.startsWith('/') ? path : `/storage/${path}`
const formatMessageTime = value => value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : ''
const attachmentUrl = message => `/api/chat/sessions/${chatStore.session?.id}/messages/${message.id}/attachment`

const cleanMarkdownText = text => {
  if (!text) return ''
  let cleaned = text.replace(/(^|\n)\s*[*|-]\s+/g, '$1• ')
  cleaned = cleaned.replace(/(?<!\*)\*(?!\*)/g, '')
  return cleaned
}

const formatTextSegments = text => {
  if (!text) return []
  const sanitized = cleanMarkdownText(text)
  const regex = /\*\*(.*?)\*\*/g
  const segments = []
  let lastIndex = 0
  let match

  while ((match = regex.exec(sanitized)) !== null) {
    if (match.index > lastIndex) {
      segments.push({ isBold: false, text: sanitized.slice(lastIndex, match.index) })
    }
    segments.push({ isBold: true, text: match[1] })
    lastIndex = regex.lastIndex
  }

  if (lastIndex < sanitized.length) {
    segments.push({ isBold: false, text: sanitized.slice(lastIndex) })
  }

  return segments
}

const parsedMessageParts = message => {
  const text = message.message || ''
  const sources = message.metadata?.sources || []
  if (!sources.length || !text) {
    return [{ type: 'text', text }]
  }

  const sourceMap = new Map(sources.map(s => [s.citation, s]))
  const regex = /\[(S\d+)\]/g
  const parts = []
  let lastIndex = 0
  let match

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      parts.push({ type: 'text', text: text.slice(lastIndex, match.index) })
    }
    const citationKey = match[1]
    const source = sourceMap.get(citationKey)
    if (source) {
      parts.push({ type: 'citation', citation: citationKey, source })
    } else {
      parts.push({ type: 'text', text: match[0] })
    }
    lastIndex = regex.lastIndex
  }

  if (lastIndex < text.length) {
    parts.push({ type: 'text', text: text.slice(lastIndex) })
  }

  return parts
}

const uncitedSources = message => {
  const sources = message.metadata?.sources || []
  if (!sources.length) return []
  const text = message.message || ''
  const citedKeys = new Set((text.match(/\[S\d+\]/g) || []).map(m => m.replace(/[[\]]/g, '')))
  return sources.filter(s => !citedKeys.has(s.citation))
}

const resizeInput = () => {
  if (!input.value) return
  input.value.style.height = 'auto'
  input.value.style.height = `${Math.min(input.value.scrollHeight, 144)}px`
}

const clearSelectedImage = () => {
  selectedImage.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const handleImageSelection = event => {
  if (!chatStore.canSendMessage) {
    clearSelectedImage()
    return
  }
  const file = event.target.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/') || file.size > 5 * 1024 * 1024) {
    chatStore.error = 'Chỉ hỗ trợ ảnh JPG, PNG, WebP hoặc GIF có dung lượng tối đa 5 MB.'
    clearSelectedImage()
    return
  }
  selectedImage.value = file
  chatStore.error = ''
}

const handleSend = async () => {
  const text = inputMessage.value.trim()
  if (!chatStore.canSendMessage || (!text && !selectedImage.value)) return
  if (await chatStore.sendMessage(text, selectedImage.value)) {
    inputMessage.value = ''
    clearSelectedImage()
    await nextTick()
    resizeInput()
  }
  await nextTick()
  input.value?.focus()
}

const handleQuickReply = reply => {
  const lower = reply.toLocaleLowerCase('vi-VN')
  if (lower.includes('gặp') && (lower.includes('nhân viên') || lower.includes('tư vấn viên'))) {
    chatStore.requestHumanSupport()
    return
  }
  chatStore.sendMessage(reply)
}

</script>
