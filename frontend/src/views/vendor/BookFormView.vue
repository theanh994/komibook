<template>
  <div class="min-h-screen bg-background text-on-surface antialiased p-4 md:p-6">
    <div class="max-w-[1400px] mx-auto space-y-6">
      
      <!-- Top Action Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/30">
        <div>
          <button 
            @click="$router.push('/vendor/books')" 
            class="inline-flex items-center gap-1.5 text-primary font-bold text-xs uppercase tracking-widest hover:underline mb-1.5 border-none bg-transparent cursor-pointer"
          >
            <span class="material-symbols-outlined text-sm">arrow_back</span>
            Quay lại danh sách sách
          </button>
          <h1 class="text-2xl md:text-3xl font-bold text-on-surface tracking-tight">
            {{ isEditMode ? 'Chỉnh Sửa Tác Phẩm' : 'Thêm Sách Mới' }}
          </h1>
          <p class="text-on-surface-variant text-xs mt-0.5">
            {{ isEditMode ? 'Cập nhật thông số và quản lý nội dung cuốn sách trong gian hàng.' : 'Nhập thông tin chi tiết cho tựa sách mới vào kho lưu trữ của bạn.' }}
          </p>
        </div>

        <div class="flex items-center gap-3 shrink-0">
          <button 
            type="button" 
            @click="$router.push('/vendor/books')" 
            class="px-4 py-2.5 border border-outline-variant/40 rounded-xl text-on-surface-variant font-bold text-xs uppercase tracking-wider hover:bg-surface-container-high transition-all cursor-pointer bg-transparent"
          >
            Hủy
          </button>
          <button 
            v-if="isEditMode"
            type="button" 
            @click="submitForm('draft')" 
            :disabled="saving"
            class="px-4.5 py-2.5 border border-primary/50 text-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-primary/10 transition-all cursor-pointer flex items-center gap-1.5 bg-transparent"
          >
            <span class="material-symbols-outlined text-base">draft</span>
            <span>Lưu Bản Nháp</span>
          </button>
          <button 
            type="button" 
            @click="submitForm('save')"
            :disabled="saving"
            class="px-5 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl shadow-md hover:bg-primary/90 active:scale-95 transition-all cursor-pointer flex items-center gap-1.5 border-none"
          >
            <span v-if="saving" class="material-symbols-outlined animate-spin text-base">progress_activity</span>
            <span v-else class="material-symbols-outlined text-base">check_circle</span>
            <span>{{ isEditMode ? 'Lưu thay đổi' : 'Tạo sách & phiếu nhập' }}</span>
          </button>
        </div>
      </div>

      <!-- Main Form Grid -->
      <form @submit.prevent="submitForm('save')" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- ─── LEFT COLUMN (2/3 width: Core Info, Pricing, Specs) ─── -->
        <div class="lg:col-span-8 space-y-6">
          
          <!-- Card 1: Thông Tin Cơ Bản (Clean Header - No Icon) -->
          <div class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/20 shadow-sm space-y-4">
            <h3 class="text-lg font-bold text-on-surface border-b border-outline-variant/10 pb-3">
              Thông Tin Cơ Bản
            </h3>
            
            <div class="space-y-4">
              <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Tên sách <span class="text-error">*</span></label>
                <InputText v-model="bookForm.title" placeholder="Nhập tên sách..." class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm font-bold" />
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Tác giả <span class="text-error">*</span></label>
                  <InputText v-model="bookForm.author" placeholder="Nhập tên tác giả..." class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Người dịch (Dịch giả)</label>
                  <InputText v-model="bookForm.translator" placeholder="Bỏ trống nếu giữ nguyên ngôn ngữ gốc..." class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Bộ sách (Series)</label>
                  <InputText v-model="bookForm.series_name" list="existing-series-list" placeholder="VD: Komi - Nữ Thần Sợ Giao Tiếp" class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm" />
                  <datalist id="existing-series-list">
                    <option v-for="s in existingSeriesList" :key="s.id" :value="s.title"></option>
                  </datalist>
                </div>
              </div>

              <!-- Mã ISBN / SKU (Ẩn khi chọn E-book Digital) -->
              <div v-if="bookForm.type !== 'ebook'" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <label for="book-isbn" class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Mã ISBN / SKU</label>
                  <InputText id="book-isbn" v-model="bookForm.isbn" placeholder="Ví dụ: 978-604-XXX" class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm" />
                </div>
                <div class="space-y-3">
                  <label for="book-print-edition" class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Bản in <span class="text-error">*</span></label>
                  <Select
                    id="book-print-edition"
                    v-model="printEditionSelection"
                    :options="printEditionOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full !h-11"
                    @change="onPrintEditionSelectionChange"
                  />
                  <div v-if="printEditionSelection === 'custom'" class="flex items-center gap-2">
                    <InputNumber
                      v-model="customPrintEdition"
                      :min="11"
                      :max="999"
                      inputId="book-custom-print-edition"
                      class="w-full !h-11"
                      @update:modelValue="onCustomPrintEditionChange"
                    />
                    <InfoTip text="Dùng khi sách đã vượt quá bản in lần thứ mười. Nhập số lần in thực tế từ 11 trở lên." label="Hướng dẫn nhập Bản in trên 10" />
                  </div>
                  <p class="mt-1.5 text-xs text-on-surface-variant">Tên hiển thị: <strong>{{ displayTitlePreview }}</strong></p>
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Mô tả nội dung chi tiết (Rich Text Editor)</label>
                <Editor 
                  v-model="bookForm.description" 
                  editorStyle="height: 240px" 
                  placeholder="Viết mô tả ngắn giới thiệu cuốn sách (in đậm, nghiêng, kích thước chữ, danh sách...)"
                  class="bg-surface-container-low rounded-xl overflow-hidden border border-outline-variant/20"
                />
              </div>
            </div>
          </div>

          <!-- Card 2: Giá & Tồn Kho (% Giảm Giá Tính Tự Động) -->
          <div class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/20 shadow-sm space-y-4">
            <h3 class="text-lg font-bold text-on-surface border-b border-outline-variant/10 pb-3">
              Giá Niêm Yết & Tồn Kho
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div class="w-full">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Giá gốc (VNĐ) <span class="text-error">*</span></label>
                <InputNumber v-model="bookForm.price" :min="0" :step="1000" class="w-full !h-11" placeholder="0" />
              </div>
              <div class="w-full">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Giảm giá (%)</label>
                <InputNumber v-model="discountPercent" :min="0" :max="99" suffix="%" class="w-full !h-11" placeholder="0%" />
              </div>
              <div class="w-full">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Giá sau giảm (VNĐ)</label>
                <InputNumber v-model="bookForm.sale_price" :min="0" :step="1000" class="w-full !h-11" placeholder="Tự tính theo % giảm" />
              </div>
              <div class="w-full">
                <div class="mb-1.5 flex min-h-5 items-center gap-1.5">
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline">{{ isEditMode ? 'Tồn kho hiện tại' : 'Số lượng nhập ban đầu' }} <span class="text-error">*</span></label>
                  <InfoTip v-if="!isEditMode && bookForm.type !== 'ebook'" text="Số lượng này được đưa vào phiếu nhập nháp. Tồn kho chỉ tăng sau khi phiếu được ghi sổ." label="Cách cập nhật tồn kho ban đầu" />
                </div>
                <div v-if="bookForm.type === 'ebook'" class="h-11 bg-surface-container-low rounded-xl flex items-center px-4 font-bold text-xs text-emerald-600 border border-emerald-600/20 w-full">
                  <span class="material-symbols-outlined text-base mr-1.5">all_inclusive</span>
                  Vô hạn (Digital)
                </div>
                <InputNumber v-else v-model="bookForm.stock" :min="0" class="w-full !h-11" />
              </div>
            </div>
          </div>

          <!-- Card 3: Thông Số Kỹ Thuật (Tối giản cho E-book vs Sách in) -->
          <div class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/20 shadow-sm space-y-4">
            <h3 class="text-lg font-bold text-on-surface border-b border-outline-variant/10 pb-3 flex items-center justify-between">
              <span>{{ bookForm.type === 'ebook' ? 'Thông Số Sách Điện Tử (E-book)' : 'Thông Số Kỹ Thuật & Quy Cách In' }}</span>
              <span v-if="bookForm.type === 'ebook'" class="text-[10px] bg-secondary/10 text-secondary px-3 py-1 rounded-full font-bold uppercase tracking-wider">Tối giản cho E-book</span>
            </h3>

            <div class="space-y-4">
              <!-- Physical Spec: Dimensions Selection (Chỉ hiện khi là Sách Vật Lý) -->
              <div v-if="bookForm.type !== 'ebook'">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Kích thước sách (Rộng x Cao)</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <Select
                    v-model="selectedDimensionPreset"
                    :options="dimensionPresets"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Chọn khổ sách phổ biến"
                    class="w-full !h-11"
                    @change="onDimensionPresetChange"
                  />
                  <InputText 
                    v-model="bookForm.dimensions" 
                    placeholder="Hoặc tự nhập khổ mới (VD: 14 x 21 cm)" 
                    class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm" 
                  />
                </div>
              </div>

              <!-- Physical Spec: Format & Weight in Grams (Chỉ hiện khi là Sách Vật Lý) -->
              <div v-if="bookForm.type !== 'ebook'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Hình thức bìa</label>
                  <Select
                    v-model="bookForm.cover_format"
                    :options="coverFormatOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Chọn hình thức bìa"
                    class="w-full !h-11"
                  />
                </div>
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Trọng lượng (gam)</label>
                  <InputText v-model="bookForm.weight" placeholder="Ví dụ: 350" class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm" />
                </div>
              </div>

              <!-- Shared Specs: Pages & Publication Year (Số Trang & Năm Xuất Bản) -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Số trang</label>
                  <InputNumber v-model="bookForm.pages" :min="1" placeholder="Ví dụ: 320" class="w-full !h-11" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Năm xuất bản</label>
                  <Select
                    v-model="bookForm.release_date"
                    :options="publicationYearOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Chọn năm xuất bản"
                    class="w-full !h-11"
                  />
                </div>
              </div>

              <!-- Shared Specs: Language Selection -->
              <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Ngôn ngữ sách</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <Select
                    v-model="selectedLanguageOption"
                    :options="languageOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Chọn ngôn ngữ"
                    class="w-full !h-11"
                    @change="onLanguageOptionChange"
                  />
                  <InputText 
                    v-if="selectedLanguageOption === 'other'"
                    v-model="customLanguage" 
                    @input="bookForm.language = customLanguage"
                    placeholder="Nhập ngôn ngữ mới (VD: Tiếng Pháp)" 
                    class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm" 
                  />
                </div>
              </div>

              <!-- Shared Specs: Target Age Selection -->
              <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Độ tuổi mục tiêu (Chọn từ mẫu cố định)</label>
                <Select
                  v-model="bookForm.target_age"
                  :options="targetAgePresets"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Chọn mốc độ tuổi phù hợp"
                  class="w-full !h-11"
                />
              </div>
            </div>
          </div>

        </div>

        <!-- ─── RIGHT SIDEBAR COLUMN (1/3 width: Media, Organization, Visibility) ─── -->
        <div class="lg:col-span-4 space-y-6">
          
          <!-- Card 1: Media (Pure Square Corners for Image Previews: rounded-none) -->
          <div class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/20 shadow-sm space-y-4">
            <h3 class="text-lg font-bold text-on-surface border-b border-outline-variant/10 pb-3">
              Hình Ảnh Tác Phẩm
            </h3>

            <!-- Main Cover Preview & Upload -->
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Ảnh bìa chính</label>
              <div v-if="coverPreviewUrl" class="mb-3 relative aspect-[3/4.2] max-h-60 mx-auto rounded-none overflow-hidden border border-outline-variant/30 group shadow-sm">
                <img :src="coverPreviewUrl" class="w-full h-full object-cover rounded-none" />
                <button type="button" @click="clearCoverImage" class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white font-bold text-[10px] uppercase tracking-widest transition-opacity border-none cursor-pointer">
                  Đổi ảnh bìa
                </button>
              </div>
              
              <input 
                type="file" 
                ref="coverFileInput" 
                accept="image/*" 
                class="hidden" 
                @change="handleCoverFileChange" 
              />
              <button 
                type="button" 
                @click="coverFileInput.click()" 
                class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl flex items-center justify-center gap-2 transition-all border-none cursor-pointer"
              >
                <span class="material-symbols-outlined text-sm">add</span>
                Tải lên ảnh bìa chính
              </button>
              <p class="text-[10px] text-outline font-medium mt-1.5 text-center">Hỗ trợ PNG, JPG, WEBP (Tối đa 10MB)</p>
            </div>

            <hr class="border-outline-variant/15" />

            <!-- Gallery Images (Multiple with Re-ordering Controls) -->
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Album ảnh minh họa (Thứ tự hiển thị)</label>
              
              <!-- Existing Gallery Images -->
              <div v-if="existingGalleryImages.length > 0" class="mb-3 space-y-1">
                <span class="text-[10px] text-outline font-bold uppercase tracking-wider block">Ảnh đã lưu (Tùy chỉnh thứ tự):</span>
                <div class="flex gap-2 overflow-x-auto py-1.5">
                  <div v-for="(img, idx) in existingGalleryImages" :key="idx" class="relative w-20 h-24 border border-outline-variant/30 rounded-none overflow-hidden shrink-0 group shadow-sm">
                    <img :src="getCoverUrl(img)" class="w-full h-full object-cover rounded-none" />
                    <!-- Hover overlay controls for Re-ordering & Deleting -->
                    <div class="absolute inset-0 bg-black/75 opacity-0 group-hover:opacity-100 flex flex-col items-center justify-between p-1.5 transition-opacity">
                      <button type="button" @click="deleteExistingGalleryImage(idx)" title="Xóa ảnh" class="text-white hover:text-red-400 transition-colors border-none bg-transparent cursor-pointer">
                        <span class="material-symbols-outlined text-sm">delete</span>
                      </button>
                      <div class="flex justify-between w-full">
                        <button type="button" :disabled="idx === 0" @click="moveExistingGalleryImage(idx, -1)" title="Di chuyển sang trái" class="text-white disabled:opacity-20 hover:text-emerald-400 transition-colors border-none bg-transparent cursor-pointer">
                          <span class="material-symbols-outlined text-xs">arrow_back</span>
                        </button>
                        <button type="button" :disabled="idx === existingGalleryImages.length - 1" @click="moveExistingGalleryImage(idx, 1)" title="Di chuyển sang phải" class="text-white disabled:opacity-20 hover:text-emerald-400 transition-colors border-none bg-transparent cursor-pointer">
                          <span class="material-symbols-outlined text-xs">arrow_forward</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <input 
                type="file" 
                ref="galleryFileInput" 
                accept="image/*" 
                multiple 
                class="hidden" 
                @change="handleGalleryFileChange" 
              />
              <button 
                type="button" 
                @click="galleryFileInput.click()" 
                class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl flex items-center justify-center gap-2 transition-all border-none cursor-pointer mb-2"
              >
                <span class="material-symbols-outlined text-sm">add</span>
                Thêm ảnh minh họa album
              </button>

              <!-- New Gallery Image Previews with Re-ordering Controls -->
              <div v-if="galleryPreviewUrls.length > 0" class="space-y-1">
                <span class="text-[10px] text-primary font-bold uppercase tracking-wider block">Ảnh mới thêm (Chờ lưu - Tùy chỉnh thứ tự):</span>
                <div class="flex gap-2 overflow-x-auto py-1.5">
                  <div v-for="(url, idx) in galleryPreviewUrls" :key="idx" class="relative w-20 h-24 border-2 border-emerald-500 rounded-none overflow-hidden shrink-0 group shadow-sm">
                    <img :src="url" class="w-full h-full object-cover rounded-none" />
                    <!-- Hover overlay controls for Re-ordering & Deleting -->
                    <div class="absolute inset-0 bg-black/75 opacity-0 group-hover:opacity-100 flex flex-col items-center justify-between p-1.5 transition-opacity">
                      <button type="button" @click="removeNewGalleryImage(idx)" title="Xóa ảnh" class="text-white hover:text-red-400 transition-colors border-none bg-transparent cursor-pointer">
                        <span class="material-symbols-outlined text-sm">delete</span>
                      </button>
                      <div class="flex justify-between w-full">
                        <button type="button" :disabled="idx === 0" @click="moveNewGalleryImage(idx, -1)" title="Di chuyển sang trái" class="text-white disabled:opacity-20 hover:text-emerald-400 transition-colors border-none bg-transparent cursor-pointer">
                          <span class="material-symbols-outlined text-xs">arrow_back</span>
                        </button>
                        <button type="button" :disabled="idx === galleryPreviewUrls.length - 1" @click="moveNewGalleryImage(idx, 1)" title="Di chuyển sang phải" class="text-white disabled:opacity-20 hover:text-emerald-400 transition-colors border-none bg-transparent cursor-pointer">
                          <span class="material-symbols-outlined text-xs">arrow_forward</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Card 2: Organization (Hiển thị Nhà Cung Cấp) -->
          <div class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/20 shadow-sm space-y-4">
            <h3 class="text-lg font-bold text-on-surface border-b border-outline-variant/10 pb-3">
              Phân Loại & Gian Hàng
            </h3>

            <div class="space-y-3">
              <!-- Thông tin Nhà Cung Cấp -->
              <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Nhà cung cấp (Gian hàng)</label>
                <div class="h-11 bg-surface-container-low rounded-xl flex items-center px-3 text-sm font-bold text-primary border border-outline-variant/20 shadow-sm">
                  <span class="material-symbols-outlined text-base mr-2 text-primary">storefront</span>
                  {{ vendorShopName }}
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Loại hình phát hành <span class="text-error">*</span></label>
                <Select
                  v-model="bookForm.type"
                  :options="bookTypes"
                  optionLabel="label"
                  optionValue="value"
                  class="w-full !h-11"
                />
              </div>

              <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Danh mục sách (Chọn nhiều) <span class="text-error">*</span></label>
                <MultiSelect
                  v-model="bookForm.category_ids"
                  :options="categories"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Tích chọn danh mục"
                  class="w-full !h-11"
                  :maxSelectedLabels="2"
                />
              </div>

              <div v-if="bookForm.type === 'ebook'" class="p-3 bg-info/5 rounded-xl border border-info/20 space-y-2">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-info">
                  Tệp E-book Digital (PDF/EPUB) <span class="text-error">*</span>
                </label>
                <p v-if="existingEbookFile" class="text-[11px] font-bold text-on-surface flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-emerald-600 text-xs">verified</span>
                  Đã có tệp E-book trên hệ thống
                </p>
                
                <input 
                  type="file" 
                  ref="ebookFileInput" 
                  accept=".pdf,.epub" 
                  class="hidden" 
                  @change="handleEbookFileChange" 
                />
                <button 
                  type="button" 
                  @click="ebookFileInput.click()" 
                  class="w-full h-11 bg-info/10 text-info hover:bg-info/20 font-bold text-xs uppercase tracking-wider rounded-xl flex items-center justify-center gap-2 transition-all border-none cursor-pointer"
                >
                  <span class="material-symbols-outlined text-sm">upload_file</span>
                  Chọn tệp E-book
                </button>
              </div>
            </div>
          </div>

          <!-- Card 3: Vận hành ban đầu cho sách mới -->
          <div v-if="!isEditMode" class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/20 shadow-sm space-y-4">
            <div class="flex items-center justify-between gap-3 border-b border-outline-variant/10 pb-3">
              <h3 class="text-lg font-bold text-on-surface">Kho & Chuỗi Cung Ứng</h3>
              <InfoTip text="Thiết lập một lần khi tạo sách để sản phẩm không còn ở trạng thái thiếu thông tin vận hành." label="Thông tin về Kho và Chuỗi Cung Ứng" />
            </div>

            <div v-if="operationsLoading" class="flex min-h-24 items-center justify-center gap-2 text-sm text-on-surface-variant" role="status">
              <i class="pi pi-spin pi-spinner"></i>
              Đang tải dữ liệu vận hành...
            </div>

            <div v-else class="space-y-4">
              <div v-if="bookForm.type === 'physical'">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Kho tổng nhận hàng <span class="text-error">*</span></label>
                <div v-if="primaryWarehouse" class="min-h-11 rounded-xl border border-outline-variant/40 bg-surface-container-low px-4 py-3" role="status">
                  <p class="text-sm font-bold text-on-surface">{{ primaryWarehouse.name }}</p>
                  <p class="mt-1 text-xs text-on-surface-variant">{{ primaryWarehouse.address }}</p>
                </div>
                <div v-else class="mt-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">
                  Gian hàng cần chọn một kho tổng đang hoạt động trước khi thêm sách vật lý.
                  <RouterLink to="/vendor/warehouses" class="ml-1 font-bold underline">Đăng ký kho ngay</RouterLink>
                </div>
              </div>

              <div v-if="supplyChainMode === 'self_supplied'" class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm leading-6 text-on-surface" role="note">
                <p class="font-bold">Chuỗi cung ứng tự quản</p>
                <p class="mt-1 text-on-surface-variant">Hệ thống dùng hồ sơ tổ chức chính của gian hàng cho Nhà xuất bản, Nhà cung ứng và Đơn vị chịu trách nhiệm. Bạn không cần chọn lại.</p>
              </div>

              <div v-else class="grid grid-cols-1 gap-4">
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Nhà xuất bản <span class="text-error">*</span></label>
                  <Select v-model="operationsForm.publisher_relationship_id" :options="publisherOptions" optionLabel="label" optionValue="value" placeholder="Chọn Nhà xuất bản" class="w-full !h-11" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Nhà cung ứng <span class="text-error">*</span></label>
                  <Select v-model="operationsForm.supplier_relationship_id" :options="supplierOptions" optionLabel="label" optionValue="value" placeholder="Chọn Nhà cung ứng" class="w-full !h-11" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold uppercase tracking-wider text-outline mb-1.5">Đơn vị chịu trách nhiệm <span class="text-error">*</span></label>
                  <Select v-model="operationsForm.responsible_organization_relationship_id" :options="responsibleOptions" optionLabel="label" optionValue="value" placeholder="Chọn đơn vị chịu trách nhiệm" class="w-full !h-11" />
                </div>
              </div>

              <div v-if="supplyChainMode !== 'self_supplied' && !hasUsableRelationships" class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">
                Chưa có quan hệ tổ chức đủ điều kiện. Quan hệ đã xác minh và dữ liệu demo đã chấp nhận đều được sử dụng tại đây.
                <RouterLink to="/vendor/organizations" class="ml-1 font-bold underline">Quản lý tổ chức</RouterLink>
              </div>

              <div v-if="bookForm.type === 'physical'">
                <div>
                  <div class="mb-1.5 flex items-center gap-1.5"><label for="external-printer" class="block text-[11px] font-bold uppercase tracking-wider text-outline">Đơn vị in ngoài hệ thống</label><InfoTip text="Thông tin này chỉ được lưu làm tham chiếu trên phiếu, không tạo quan hệ pháp lý trong hệ thống." label="Ý nghĩa đơn vị in ngoài hệ thống" /></div>
                  <InputText id="external-printer" v-model="operationsForm.external_counterparty_name" placeholder="Tên đơn vị in hoặc nguồn nhập" class="w-full !h-11 !p-3 !rounded-xl !bg-surface-container-low !border-none text-sm" />
                </div>
              </div>

              <div v-if="blockingReasons.length" class="rounded-xl border border-error/30 bg-error-container p-4 text-sm text-on-error-container" role="alert">
                <p class="font-bold">Chưa thể tạo sách vật lý</p>
                <ul class="mt-2 list-disc space-y-1 pl-5"><li v-for="reason in blockingReasons" :key="reason">{{ reason }}</li></ul>
              </div>
            </div>
          </div>

          <!-- Card 3: Visibility Status (Clean Header - No Icon) -->
          <div v-if="isEditMode" class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/20 shadow-sm space-y-4">
            <h3 class="text-lg font-bold text-on-surface border-b border-outline-variant/10 pb-3">
              Trạng Thái Xuất Bản
            </h3>

            <div class="space-y-2.5">
              <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-all" :class="bookForm.status === 'published' ? 'border-primary bg-primary/5 shadow-sm' : 'border-outline-variant/30 hover:bg-surface-container-low'">
                <input type="radio" v-model="bookForm.status" value="published" class="w-4 h-4 text-primary" />
                <div>
                  <p class="font-bold text-xs text-on-surface">Đã xuất bản</p>
                  <p class="text-[10px] text-outline opacity-80">Hiển thị công khai trên gian hàng</p>
                </div>
              </label>

              <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-all" :class="bookForm.status === 'draft' ? 'border-primary bg-primary/5 shadow-sm' : 'border-outline-variant/30 hover:bg-surface-container-low'">
                <input type="radio" v-model="bookForm.status" value="draft" class="w-4 h-4 text-primary" />
                <div>
                  <p class="font-bold text-xs text-on-surface">Bản nháp</p>
                  <p class="text-[10px] text-outline opacity-80">Lưu riêng tư, chưa bán</p>
                </div>
              </label>
            </div>
          </div>

        </div>

      </form>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/axios'
import InfoTip from '@/components/InfoTip.vue'

import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Editor from 'primevue/editor'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()

const isEditMode = computed(() => !!route.params.id)
const saving = ref(false)
const bookCreateOperationKey = globalThis.crypto?.randomUUID?.() || `book-create-${Date.now()}`

const categories = ref([])
const existingSeriesList = ref([])
const discountPercent = ref(null)
const bookVendorName = ref('')
const operationsLoading = ref(false)
const organizationRelationships = ref([])
const createScope = ref({})
const operationsForm = ref({
  warehouse_id: null,
  publisher_relationship_id: null,
  supplier_relationship_id: null,
  responsible_organization_relationship_id: null,
  external_counterparty_name: '',
})

const primaryWarehouse = computed(() => createScope.value.primary_warehouse || null)
const supplyChainMode = computed(() => createScope.value.supply_chain_mode || 'partner_chain')
const blockingReasons = computed(() => createScope.value.blocking_reasons || [])

const vendorShopName = computed(() => {
  return bookVendorName.value || authStore.user?.vendor?.shop_name || authStore.user?.name || 'Gian hàng của bạn'
})

const acceptedRelationships = computed(() => organizationRelationships.value.filter(rel => {
  const organizationAccepted = rel.organization?.status === 'verified'
    || (rel.organization?.data_mode === 'demo' && rel.organization?.status === 'demo_accepted')
  return ['verified', 'demo_accepted'].includes(rel.status) && organizationAccepted
}))
const relationshipOption = (rel) => ({
  value: rel.id,
  label: `${rel.organization?.display_name || rel.organization?.legal_name || 'Tổ chức'}${rel.status === 'demo_accepted' ? ' · Demo đã chấp nhận' : ' · Đã xác minh'}`,
})
const publisherOptions = computed(() => acceptedRelationships.value
  .filter(rel => rel.organization?.organization_types?.includes('publisher') && ['self_legal_entity', 'publisher_partner'].includes(rel.role))
  .map(relationshipOption))
const supplierOptions = computed(() => acceptedRelationships.value
  .filter(rel => rel.organization?.organization_types?.some(type => ['supplier', 'publisher', 'distributor'].includes(type)) && ['self_legal_entity', 'supplier_partner', 'authorized_distributor'].includes(rel.role))
  .map(relationshipOption))
const responsibleOptions = computed(() => acceptedRelationships.value
  .filter(rel => ['self_legal_entity', 'publisher_partner', 'supplier_partner', 'authorized_distributor'].includes(rel.role))
  .map(relationshipOption))
const hasUsableRelationships = computed(() => publisherOptions.value.length > 0 && supplierOptions.value.length > 0 && responsibleOptions.value.length > 0)
const displayTitlePreview = computed(() => {
  const title = bookForm.value?.title?.trim() || 'Tên sách'
  const edition = Math.max(1, Number(bookForm.value?.print_edition) || 1)
  return edition === 1 ? title : `${title} — Tái bản lần ${edition}`
})

const bookForm = ref({
  title: '',
  author: '',
  translator: '',
  series_name: '',
  category_ids: [],
  description: '',
  isbn: '',
  print_edition: 1,
  dimensions: '13 x 18 cm',
  cover_format: 'Bìa mềm',
  weight: '',
  language: 'Tiếng Việt',
  target_age: 'Tuổi trưởng thành (Trên 18 tuổi)',
  pages: null,
  release_date: '2026',
  price: 0,
  sale_price: null,
  stock: 0,
  type: 'physical',
  status: 'draft',
})

const printEditionOptions = [
  { label: 'Bản in đầu', value: 1 },
  { label: 'Bản in lần thứ hai', value: 2 },
  { label: 'Bản in lần thứ ba', value: 3 },
  { label: 'Bản in lần thứ tư', value: 4 },
  { label: 'Bản in lần thứ năm', value: 5 },
  { label: 'Bản in lần thứ sáu', value: 6 },
  { label: 'Bản in lần thứ bảy', value: 7 },
  { label: 'Bản in lần thứ tám', value: 8 },
  { label: 'Bản in lần thứ chín', value: 9 },
  { label: 'Bản in lần thứ mười', value: 10 },
  { label: 'Bản in lần thứ… (nhập số)', value: 'custom' },
]
const printEditionSelection = ref(1)
const customPrintEdition = ref(11)
const onPrintEditionSelectionChange = ({ value }) => {
  bookForm.value.print_edition = value === 'custom' ? customPrintEdition.value : value
}
const onCustomPrintEditionChange = (value) => {
  bookForm.value.print_edition = Math.max(11, Number(value) || 11)
}

// Tự động tính "Giá sau giảm" khi nhập "Giá gốc" hoặc "% Giảm giá"
watch(
  [() => bookForm.value.price, discountPercent],
  ([newPrice, newPct]) => {
    const price = Number(newPrice) || 0
    const pct = Number(newPct)
    if (price > 0 && newPct !== null && newPct !== undefined && pct > 0 && pct < 100) {
      bookForm.value.sale_price = Math.round(price * (1 - pct / 100))
    } else if (newPct === 0 || newPct === null || newPct === undefined) {
      bookForm.value.sale_price = null
    }
  }
)

const coverFile = ref(null)
const coverPreviewUrl = ref('')
const ebookFile = ref(null)
const existingEbookFile = ref(false)

const coverFileInput = ref(null)
const galleryFileInput = ref(null)
const ebookFileInput = ref(null)

const galleryFiles = ref([])
const existingGalleryImages = ref([])
const galleryPreviewUrls = ref([])

const handleCoverFileChange = (e) => {
  const file = e.target.files?.[0] || null
  if (file) {
    if (file.size > 10 * 1024 * 1024) {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Dung lượng ảnh bìa tối đa là 10MB.', life: 4000 })
      e.target.value = ''
      return
    }
    if (coverPreviewUrl.value.startsWith('blob:')) URL.revokeObjectURL(coverPreviewUrl.value)
    coverFile.value = file
    coverPreviewUrl.value = URL.createObjectURL(file)
  }
}

const handleGalleryFileChange = (e) => {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if (file.size > 10 * 1024 * 1024) {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: `Ảnh ${file.name} vượt quá dung lượng tối đa 10MB.`, life: 4000 })
      continue
    }
    galleryFiles.value.push(file)
    galleryPreviewUrls.value.push(URL.createObjectURL(file))
  }
}

const handleEbookFileChange = (e) => {
  ebookFile.value = e.target.files?.[0] || null
}

const selectedDimensionPreset = ref('13 x 18 cm')
const selectedLanguageOption = ref('Tiếng Việt')
const customLanguage = ref('')

const dimensionPresets = [
  { label: '11.3 x 17.6 cm (Manga Tiêu chuẩn / Tankobon)', value: '11.3 x 17.6 cm' },
  { label: '13 x 18 cm (Manga B6 Seinen / Shounen)', value: '13 x 18 cm' },
  { label: '14.5 x 20.5 cm (Deluxe / Kanzenban / Tiêu chuẩn)', value: '14.5 x 20.5 cm' },
  { label: '16.8 x 25.7 cm (Comic Mỹ Regular / TPB)', value: '16.8 x 25.7 cm' },
  { label: '21 x 29.7 cm (Comic Châu Âu Album / A4)', value: '21 x 29.7 cm' },
  { label: '13 x 19 cm (Light Novel Chuẩn VN)', value: '13 x 19 cm' },
  { label: '10.5 x 17.5 cm (Tiểu thuyết Khổ nhỏ / Pocket)', value: '10.5 x 17.5 cm' },
  { label: '13 x 20.5 cm (Tiểu thuyết Khổ phổ thông VN)', value: '13 x 20.5 cm' },
  { label: '16 x 24 cm (Khổ lớn / Bìa cứng)', value: '16 x 24 cm' },
]

const languageOptions = [
  { label: 'Tiếng Việt (Mặc định)', value: 'Tiếng Việt' },
  { label: 'Tiếng Anh', value: 'Tiếng Anh' },
  { label: 'Tiếng Nhật', value: 'Tiếng Nhật' },
  { label: 'Tiếng Trung', value: 'Tiếng Trung' },
  { label: 'Tiếng Hàn', value: 'Tiếng Hàn' },
  { label: 'Khác... (Nhập mới)', value: 'other' },
]

const targetAgePresets = [
  { label: 'Nhà trẻ - mẫu giáo (0 - 6)', value: 'Nhà trẻ - mẫu giáo (0 - 6)' },
  { label: 'Nhi đồng (6 - 11)', value: 'Nhi đồng (6 - 11)' },
  { label: 'Thiếu niên (11 - 15)', value: 'Thiếu niên (11 - 15)' },
  { label: 'Tuổi mới lớn (15 - 18)', value: 'Tuổi mới lớn (15 - 18)' },
  { label: 'Tuổi trưởng thành (Trên 18 tuổi)', value: 'Tuổi trưởng thành (Trên 18 tuổi)' },
]

const coverFormatOptions = [
  { label: 'Bìa mềm', value: 'Bìa mềm' },
  { label: 'Bìa cứng', value: 'Bìa cứng' },
  { label: 'Bìa dẻo', value: 'Bìa dẻo' },
  { label: 'Hộp/Boxset', value: 'Hộp/Boxset' },
]

const bookTypes = [
  { label: 'Sách vật lý', value: 'physical' },
  { label: 'E-book Digital', value: 'ebook' },
]

const onDimensionPresetChange = (e) => {
  if (e.value) {
    bookForm.value.dimensions = e.value
  }
}

const onLanguageOptionChange = (e) => {
  if (e.value && e.value !== 'other') {
    bookForm.value.language = e.value
    customLanguage.value = ''
  } else if (e.value === 'other') {
    bookForm.value.language = customLanguage.value || ''
  }
}

const fetchCategories = async () => {
  try {
    const res = await apiClient.get('/api/categories')
    const raw = res.data.data || res.data
    categories.value = raw.map(c => ({ label: c.name, value: c.id }))
  } catch (e) {
    console.warn('Không tải được danh mục', e)
  }
}

const fetchBookDetail = async (id) => {
  try {
    const res = await apiClient.get(`/api/vendor/books/${id}`)
    const bookData = res.data.data || res.data
    
    if (bookData.category_id && !bookForm.value.category_ids.length) {
      bookForm.value.category_ids = [bookData.category_id]
    }

    if (bookData.series?.title) {
      bookForm.value.series_name = bookData.series.title
    }
    
    bookVendorName.value = bookData.vendor?.name || bookData.vendor?.shop_name || ''
    bookForm.value = {
      title: bookData.title || '',
      author: bookData.author || '',
      translator: bookData.translator || '',
      category_ids: bookData.categories?.map(c => c.id) || [],
      description: bookData.description || '',
      isbn: bookData.isbn || '',
      print_edition: bookData.print_edition || 1,
      dimensions: bookData.dimensions || '13 x 18 cm',
      cover_format: bookData.cover_format || 'Bìa mềm',
      weight: bookData.weight || '350',
      language: bookData.language || 'Tiếng Việt',
      target_age: bookData.target_age || 'Tuổi trưởng thành (Trên 18 tuổi)',
      pages: bookData.pages || null,
      release_date: bookData.release_date || '',
      price: bookData.price || 0,
      sale_price: bookData.sale_price || null,
      stock: bookData.type === 'ebook' ? 999999 : (bookData.stock || 0),
      type: bookData.type || 'physical',
      status: bookData.status || 'draft',
    }
    printEditionSelection.value = bookForm.value.print_edition <= 10 ? bookForm.value.print_edition : 'custom'
    customPrintEdition.value = bookForm.value.print_edition > 10 ? bookForm.value.print_edition : 11

    if (bookData.price > 0 && bookData.sale_price && bookData.sale_price < bookData.price) {
      discountPercent.value = Math.round((1 - bookData.sale_price / bookData.price) * 100)
    }

    if (bookData.cover_image) {
      coverPreviewUrl.value = getCoverUrl(bookData.cover_image)
    }

    existingGalleryImages.value = Array.isArray(bookData.gallery_images) ? [...bookData.gallery_images] : []
    existingEbookFile.value = !!bookData.file_path
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải thông tin sách.', life: 3000 })
    router.push('/vendor/books')
  }
}

const clearCoverImage = () => {
  if (coverPreviewUrl.value.startsWith('blob:')) URL.revokeObjectURL(coverPreviewUrl.value)
  coverFile.value = null
  coverPreviewUrl.value = ''
}

const removeNewGalleryImage = (idx) => {
  if (galleryPreviewUrls.value[idx]?.startsWith('blob:')) URL.revokeObjectURL(galleryPreviewUrls.value[idx])
  galleryFiles.value.splice(idx, 1)
  galleryPreviewUrls.value.splice(idx, 1)
}

const deleteExistingGalleryImage = (idx) => {
  existingGalleryImages.value.splice(idx, 1)
}

const moveExistingGalleryImage = (idx, direction) => {
  const targetIdx = idx + direction
  if (targetIdx < 0 || targetIdx >= existingGalleryImages.value.length) return
  const item = existingGalleryImages.value.splice(idx, 1)[0]
  existingGalleryImages.value.splice(targetIdx, 0, item)
}

const moveNewGalleryImage = (idx, direction) => {
  const targetIdx = idx + direction
  if (targetIdx < 0 || targetIdx >= galleryFiles.value.length) return
  const file = galleryFiles.value.splice(idx, 1)[0]
  galleryFiles.value.splice(targetIdx, 0, file)

  const url = galleryPreviewUrls.value.splice(idx, 1)[0]
  galleryPreviewUrls.value.splice(targetIdx, 0, url)
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  return `/storage/${path}`
}

const submitForm = async (targetStatus) => {
  if (isEditMode.value && targetStatus === 'draft') bookForm.value.status = 'draft'
  if (!bookForm.value.title || !bookForm.value.author) {
    toast.add({ severity: 'warn', summary: 'Thiếu thông tin', detail: 'Vui lòng nhập tên sách và tác giả.', life: 3000 })
    return
  }
  if (!bookForm.value.category_ids || bookForm.value.category_ids.length === 0) {
    toast.add({ severity: 'warn', summary: 'Thiếu thông tin', detail: 'Vui lòng chọn ít nhất một danh mục.', life: 3000 })
    return
  }
  if (!isEditMode.value) {
    if (bookForm.value.type === 'physical' && (!primaryWarehouse.value || blockingReasons.value.length)) {
      toast.add({ severity: 'warn', summary: 'Chưa sẵn sàng vận hành', detail: blockingReasons.value[0] || 'Vui lòng chọn kho tổng cho sách vật lý.', life: 4000 })
      return
    }
    if (supplyChainMode.value !== 'self_supplied' && (!operationsForm.value.publisher_relationship_id || !operationsForm.value.supplier_relationship_id || !operationsForm.value.responsible_organization_relationship_id)) {
      toast.add({ severity: 'warn', summary: 'Thiếu chuỗi cung ứng', detail: 'Vui lòng chọn đủ Nhà xuất bản, Nhà cung ứng và Đơn vị chịu trách nhiệm.', life: 4000 })
      return
    }
  }

  if (bookForm.value.type === 'ebook') {
    bookForm.value.stock = 999999
  }

  saving.value = true
  try {
    const formData = new FormData()
    Object.entries(bookForm.value).forEach(([key, val]) => {
      if (key === 'status' && !isEditMode.value) return
      if (key === 'category_ids' && Array.isArray(val)) {
        val.forEach(id => formData.append('category_ids[]', id))
      } else if (val !== null && val !== undefined && val !== '') {
        formData.append(key, val)
      }
    })

    if (coverFile.value) formData.append('cover_image', coverFile.value)
    if (ebookFile.value) formData.append('ebook_file', ebookFile.value)

    galleryFiles.value.forEach(f => formData.append('gallery_images[]', f))
    if (existingGalleryImages.value.length > 0) {
      existingGalleryImages.value.forEach(img => formData.append('existing_gallery_images[]', img))
    } else if (isEditMode.value) {
      formData.append('existing_gallery_images', JSON.stringify([]))
    }

    if (!isEditMode.value) {
      formData.append('operation_key', bookCreateOperationKey)
      Object.entries(operationsForm.value).forEach(([key, value]) => {
        if (value !== null && value !== undefined) formData.append(key, value)
      })
      const response = await apiClient.post('/api/vendor/books', formData)
      const receiptId = response.data.receipt_document?.id
      toast.add({ severity: 'success', summary: 'Đã tạo sách và phiếu nhập', detail: 'Sách đã công khai. Hãy kiểm tra và ghi sổ phiếu để mở bán.', life: 4500 })
      if (receiptId) {
        router.push({ name: 'vendor-warehouse-documents', query: { document_id: receiptId } })
        return
      }
    } else {
      formData.append('_method', 'PUT')
      await apiClient.post(`/api/vendor/books/${route.params.id}`, formData)
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật cuốn sách!', life: 3000 })
    }

    router.push('/vendor/books')
  } catch (e) {
    const msg = e.response?.data?.message || 'Có lỗi xảy ra khi lưu sách.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

const fetchExistingSeries = async () => {
  try {
    const res = await apiClient.get('/api/series')
    existingSeriesList.value = res.data.data || []
  } catch (e) {
    console.warn('Không thể tải danh sách bộ sách:', e)
  }
}

const fetchOperationalOptions = async () => {
  operationsLoading.value = true
  try {
    const response = await apiClient.get('/api/vendor/books/create-scope')
    createScope.value = response.data.data || {}
    organizationRelationships.value = createScope.value.relationships || []
    operationsForm.value.warehouse_id = createScope.value.primary_warehouse?.id ?? null
    operationsForm.value.publisher_relationship_id = publisherOptions.value[0]?.value ?? null
    operationsForm.value.supplier_relationship_id = supplierOptions.value[0]?.value ?? null
    operationsForm.value.responsible_organization_relationship_id = responsibleOptions.value[0]?.value ?? null
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Không tải được dữ liệu vận hành', detail: 'Vui lòng tải lại trang trước khi tạo sách.', life: 4500 })
  } finally {
    operationsLoading.value = false
  }
}

onMounted(() => {
  fetchCategories()
  fetchExistingSeries()
  if (isEditMode.value) {
    fetchBookDetail(route.params.id)
  } else {
    fetchOperationalOptions()
  }
})

onBeforeUnmount(() => {
  if (coverPreviewUrl.value.startsWith('blob:')) URL.revokeObjectURL(coverPreviewUrl.value)
  galleryPreviewUrls.value.forEach(url => {
    if (url.startsWith('blob:')) URL.revokeObjectURL(url)
  })
})

const publicationYearOptions = computed(() => {
  const years = []
  const currentYear = new Date().getFullYear()
  for (let y = currentYear; y >= 1950; y--) {
    years.push({ label: `Năm ${y}`, value: y.toString() })
  }
  return years
})
</script>

<style scoped>
/* Uniform 42px height & 100% width for all PrimeVue components in form */
:deep(.p-select),
:deep(.p-multiselect),
:deep(.p-inputnumber),
:deep(.p-inputnumber-input),
:deep(.p-fileupload-basic) {
  width: 100% !important;
  height: 42px !important;
  border-radius: 12px !important;
  font-size: 0.875rem !important;
}

:deep(.p-inputnumber) {
  display: flex !important;
}

:deep(.p-select-label),
:deep(.p-multiselect-label) {
  display: flex;
  align-items: center;
  font-size: 0.875rem !important;
}

/* Hide 'No file chosen' filename text in FileUpload basic mode */
:deep(.p-fileupload-filename),
:deep(.p-fileupload-file-name),
:deep(.p-fileupload-basic > span:not(.p-button)) {
  display: none !important;
}

:deep(.p-fileupload-basic .p-button) {
  width: 100% !important;
  height: 42px !important;
  border-radius: 12px !important;
  justify-content: center !important;
}
</style>
