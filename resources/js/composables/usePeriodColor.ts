// 申請した「期」ごとの色（行の左端に表示）
const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6']

export function periodColor(no: number | null | undefined): string {
  if (!no) return 'transparent'
  return COLORS[(no - 1) % COLORS.length]
}

export function periodCellStyle(no: number | null | undefined) {
  return { boxShadow: `inset 6px 0 0 ${periodColor(no)}` }
}
