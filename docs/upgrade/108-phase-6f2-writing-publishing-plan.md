# Giai đoạn 6F.2 — kế hoạch Viết sách và Xuất bản

Ngày lập: 2026-07-29

## Phạm vi

- `frontend/src/views/author/AuthorStudioView.vue`
- `frontend/src/views/vendor/LiveEditorView.vue`
- `frontend/src/views/vendor/MultiDevicePreviewView.vue`
- `frontend/src/views/author/CopyrightClaimView.vue`

## Bất biến

- Tác phẩm tự xuất bản là ebook; route viết/xem trước chỉ dành approved Author.
- Không tạo tác phẩm/chương, lưu, autosave, xóa, upload hoặc submit trong browser.
- Giữ revision conflict, ownership, copyright và publish eligibility hiện có.
- Sửa đúng route Author write/preview; responsive bốn viewport, target 44px,
  keyboard và loading/error/empty feedback.
- Không commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa bốn view trong phạm vi. Giữ API, ownership, revision conflict, copyright
> và publishing contract. Sửa route writer/preview theo router Author. Làm editor
> dùng được ở 375/768/1024/1440, chapter rows dùng bàn phím, form có label, target
> 44px, loading/error/empty và autosave feedback. Không tạo/lưu/xóa chương, tạo
> ebook, upload/submit hồ sơ trong browser; không sửa backend/test/route, không
> commit/push. Chạy frontend test/build và backend regression liên quan.

