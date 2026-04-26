import { computed, unref } from 'vue'

export const useSearchCollection = (itemsSource, searchSource, fields) => computed(() => {
  const items = (typeof itemsSource === 'function' ? itemsSource() : unref(itemsSource)) ?? []
  const rawQuery = typeof searchSource === 'function' ? searchSource() : unref(searchSource)
  const query = String(rawQuery ?? '').trim().toLowerCase()

  if (!query) {
    return items
  }

  return items.filter((item) => {
    const searchableText = fields
      .map((field) => (typeof field === 'function' ? field(item) : item?.[field]))
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return searchableText.includes(query)
  })
})
