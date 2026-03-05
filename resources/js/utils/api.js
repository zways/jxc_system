/**
 * 解析 Laravel 分页 API 响应
 * @param {object} res - axios response (res.data 为后端返回的 { success, data, message })
 * @returns {{ list: array, meta: object }}
 */
export function parsePaginatedResponse(res) {
  const payload = res.data || {}
  const data = payload.data
  let list = []
  let meta = {}
  if (data && typeof data === 'object') {
    if (Array.isArray(data.data)) {
      list = data.data
      meta = data.meta || {
        total: data.total,
        current_page: data.current_page,
        per_page: data.per_page,
        last_page: data.last_page
      }
    } else if (Array.isArray(data)) {
      list = data
    }
  }
  return { list, meta }
}

/**
 * 请求分页列表并更新 pagination 与 list
 * @param {Function} request - () => axios.get(...)
 * @param {import('vue').Ref} listRef - ref([])
 * @param {object} pagination - reactive({ currentPage, pageSize, total })
 */
export async function fetchPaginatedList(request, listRef, pagination) {
  const res = await request()
  const { list, meta } = parsePaginatedResponse(res)
  listRef.value = list
  if (meta.total != null) pagination.total = meta.total
  if (meta.current_page != null) pagination.currentPage = meta.current_page
  if (meta.per_page != null) pagination.pageSize = meta.per_page
  return list
}
