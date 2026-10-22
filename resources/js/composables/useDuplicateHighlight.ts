import { computed, type Ref } from 'vue'

// 同一の「区分・学会名・役割」（学術集会・講習会は「開催回」も含む）を持つ書類が複数ある場合に
// カラーハイライトする（差し戻し済みは対象外）。Excelの条件付き書式による重複チェックのイメージ。
export function useDuplicateHighlight(list: Ref<any[]>) {
  const key = (u: any) =>
    [u.credit_category_id, u.credit_conference_id, u.credit_role_id, u.session ?? ''].join('|')

  const duplicateKeys = computed(() => {
    const count: Record<string, number> = {}
    for (const u of list.value) {
      if (u.status === 'rejected') continue
      const k = key(u)
      count[k] = (count[k] ?? 0) + 1
    }
    return new Set(Object.keys(count).filter(k => count[k] > 1))
  })

  const isDuplicate = (u: any) => u.status !== 'rejected' && duplicateKeys.value.has(key(u))

  // クラスの背景色より優先させるため、インラインstyleで指定する
  const duplicateStyle = (u: any) =>
    isDuplicate(u) ? { backgroundColor: '#FEF3C7', borderColor: '#F59E0B' } : {}

  const duplicateTitle = (u: any) =>
    isDuplicate(u) ? '同一の区分・学会名・役割（・開催回）の書類が他にもあります' : ''

  return { isDuplicate, duplicateStyle, duplicateTitle }
}
