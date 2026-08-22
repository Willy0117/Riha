// resources/js/composables/usePermissionLabel.js

const resourceLabels = {
  'organizations':  '所属先',
  'members':        '会員',
  'chief':         '審査委員長',
  'reviewer':      '審査員',
  'subleader':     'サブリーダー', 
  'offices':        '事務局', 
  'invoices':       '請求書',
  'stripes':        'Stripe支払い',
  'tenants':        'テナント',
  'license-fees':   'ライセンス料',
  'roles':          'ロール',
  'permissions':    '権限',
  'admins':         '管理者',
  'users':          'ユーザー',
  'imports':        'インポート',
  'credits':        '単位',
}

const actionLabels = {
  view: '閲覧',
  edit: '編集',
  manage: '',
}

/**
 * permission の name（例: "organization.view"）を日本語ラベル（例: "組織：閲覧"）に変換する
 * 辞書に存在しない resource / action は name のまま表示する（フォールバック）
 */
export function usePermissionLabel() {
  const getPermissionLabel = (name) => {
    if (!name) return ''

    const [resource, action] = name.split('.')
    const resourceLabel = resourceLabels[resource] ?? resource
    const actionLabel = actionLabels[action] ?? action

    return `${resourceLabel}：${actionLabel}`
  }

  return { getPermissionLabel }
}
