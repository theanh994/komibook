const INTERNAL_ERROR_PATTERN = /SQLSTATE|\bselect\b.+\bfrom\b|\bconnection\b.+\bdatabase\b|stack trace|unknown column/i

export const userSafeApiError = (error, fallback) => {
  const status = Number(error?.response?.status || 0)
  const message = error?.response?.data?.message

  if (status >= 500 || typeof message !== 'string' || INTERNAL_ERROR_PATTERN.test(message)) return fallback

  return message.trim() || fallback
}
