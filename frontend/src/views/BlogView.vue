<template>
  <main class="min-h-screen bg-background px-gutter py-xxl font-inter text-on-surface">
    <section class="mx-auto flex max-w-3xl flex-col items-center rounded-[48px] border border-outline-variant/10 bg-surface-container-lowest px-8 py-16 text-center shadow-2xl md:px-16">
      <div class="mb-8 flex h-24 w-24 items-center justify-center rounded-[32px] bg-primary/10 text-primary">
        <span class="material-symbols-outlined text-5xl">newspaper</span>
      </div>

      <p class="mb-4 text-[10px] font-bold uppercase tracking-[0.3em] text-primary">
        KomiBook Editorial
      </p>
      <h1 class="mb-6 text-4xl font-bold tracking-tight md:text-6xl">Tin tức &amp; sự kiện</h1>
      <p class="max-w-xl text-base font-medium leading-relaxed text-on-surface-variant md:text-lg">
        Nội dung biên tập chưa được mở vì KomiBook chưa có nguồn bài viết được xác minh.
        Chúng tôi sẽ chỉ hiển thị chuyên mục này khi có dữ liệu và quy trình xuất bản chính thức.
      </p>

      <div class="mt-10 flex w-full flex-col justify-center gap-4 sm:flex-row">
        <RouterLink
          to="/"
          class="rounded-2xl bg-primary px-8 py-4 text-xs font-bold uppercase tracking-widest text-on-primary shadow-lg transition-transform hover:scale-105"
        >
          Về trang chủ
        </RouterLink>
        <RouterLink
          to="/help-center"
          class="rounded-2xl border border-outline-variant/30 bg-surface-container-low px-8 py-4 text-xs font-bold uppercase tracking-widest text-on-surface transition-colors hover:bg-surface-container-high"
        >
          Trung tâm trợ giúp
        </RouterLink>
      </div>
    </section>
  </main>
</template>
