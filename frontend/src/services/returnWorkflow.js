export const returnStatus = {
  requested: { label: 'Đã gửi yêu cầu', tone: 'bg-amber-100 text-amber-800' },
  under_review: { label: 'Đang xem xét', tone: 'bg-blue-100 text-blue-800' },
  approved: { label: 'Đã duyệt', tone: 'bg-indigo-100 text-indigo-800' },
  item_received: { label: 'Đã nhận hàng trả', tone: 'bg-violet-100 text-violet-800' },
  refund_processing: { label: 'Đang hoàn tiền', tone: 'bg-cyan-100 text-cyan-800' },
  refund_failed: { label: 'Hoàn tiền lỗi', tone: 'bg-red-100 text-red-800' },
  refunded: { label: 'Đã hoàn tiền', tone: 'bg-emerald-100 text-emerald-800' },
  rejected: { label: 'Đã từ chối', tone: 'bg-slate-200 text-slate-700' },
}

export const workflowActions = {
  requested: [
    { target: 'under_review', label: 'Tiếp nhận', kind: 'transition' },
    { target: 'rejected', label: 'Từ chối', kind: 'transition', requiresReason: true },
  ],
  under_review: [
    { target: 'approved', label: 'Phê duyệt', kind: 'transition' },
    { target: 'rejected', label: 'Từ chối', kind: 'transition', requiresReason: true },
  ],
  approved: [
    { target: 'item_received', label: 'Xác nhận đã nhận hàng', kind: 'transition' },
  ],
  item_received: [
    { target: 'refund', label: 'Thực hiện hoàn tiền', kind: 'refund' },
  ],
  refund_failed: [
    { target: 'refund', label: 'Thử hoàn tiền lại', kind: 'refund' },
  ],
  refund_processing: [
    { target: 'reconcile', label: 'Đối soát trạng thái VNPAY', kind: 'reconcile' },
  ],
}

export const operationKey = (prefix) => {
  const suffix = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`
  return `${prefix}:${suffix}`
}

export const formatMoney = (value, currency = 'VND') => new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency,
}).format(Number(value || 0))

export const formatReturnDate = (value) => value
  ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
  : '—'
