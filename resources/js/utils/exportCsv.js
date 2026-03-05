/**
 * 根据路径从对象取值，支持嵌套如 "customer.name"
 * @param {object} obj
 * @param {string} path
 * @returns {string|number|null|undefined}
 */
export function getByPath(obj, path) {
  if (obj == null || path == null) return undefined
  const parts = String(path).split('.')
  let v = obj
  for (const p of parts) {
    v = v?.[p]
  }
  if (v === null || v === undefined) return ''
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

/**
 * 将单元格内容转义为 CSV 安全字符串（含逗号、换行、引号时用双引号包裹）
 * @param {string} cell
 * @returns {string}
 */
function escapeCsvCell(cell) {
  const s = String(cell ?? '')
  if (/[",\r\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

/**
 * 将列表数据转为 CSV 字符串（UTF-8，含 BOM 便于 Excel 识别）
 * @param {array} list - 数据行
 * @param {array} columns - [{ key: string, label: string }]
 * @returns {string}
 */
export function buildCsv(list, columns) {
  const header = columns.map((c) => escapeCsvCell(c.label)).join(',')
  const rows = list.map((row) =>
    columns.map((c) => escapeCsvCell(getByPath(row, c.key))).join(',')
  )
  const csv = [header, ...rows].join('\r\n')
  const BOM = '\uFEFF'
  return BOM + csv
}

/**
 * 触发浏览器下载 CSV 文件
 * @param {string} csvContent - 含 BOM 的 CSV 字符串
 * @param {string} filename - 文件名，如 "销售订单.csv"
 */
export function downloadCsv(csvContent, filename) {
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename || 'export.csv'
  a.style.display = 'none'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}
