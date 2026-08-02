const fontFamilies = {
  inter: 'Inter, sans-serif',
  literata: 'Literata, Georgia, serif',
  'times-new-roman': '"Times New Roman", Times, serif',
  arial: 'Arial, sans-serif',
  georgia: 'Georgia, serif',
  monospace: 'ui-monospace, SFMono-Regular, Consolas, monospace',
}

export const articleTypographyStyle = (format, { includeSize = true } = {}) => {
  if (!format || typeof format !== 'object') return undefined

  return {
    fontFamily: fontFamilies[format.font] || undefined,
    fontSize: includeSize && /^\d{2}$/.test(String(format.size || '')) ? `${format.size}px` : undefined,
    textAlign: ['left', 'center', 'right', 'justify'].includes(format.align) ? format.align : undefined,
    fontWeight: format.weight === 'bold' ? '700' : undefined,
    fontStyle: format.style === 'italic' ? 'italic' : undefined,
  }
}
