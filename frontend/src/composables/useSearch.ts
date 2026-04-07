import { ref, watch } from 'vue'

export function useSearch(delay = 300) {
  const query = ref('')
  const debouncedQuery = ref('')
  let timeout: ReturnType<typeof setTimeout> | null = null

  watch(query, (val) => {
    if (timeout) clearTimeout(timeout)
    timeout = setTimeout(() => {
      debouncedQuery.value = val
    }, delay)
  })

  function clear() {
    query.value = ''
    debouncedQuery.value = ''
    if (timeout) clearTimeout(timeout)
  }

  return { query, debouncedQuery, clear }
}
