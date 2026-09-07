<template>
  <AppLayout>
    <template #header>
        <div>
          <h2 class="text-2xl font-bold page-title-navy">事務局ポータル</h2>
          <p class="text-xs text-gray-500 mt-1">申請者の提出書類を確認し、判定を行います。</p>
        </div>
    </template>
    <div class="p-6">
      <!-- flashメッセージ -->
      <div
        v-if="page.props.flash?.success"
        class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm"
      >
        {{ page.props.flash.success }}
      </div>

      <!-- 絞り込みエリア -->
      <div class="flex flex-wrap items-center gap-2 mb-3 p-3 bg-gray-50 border border-gray-200 rounded-lg">
        <div class="flex items-center gap-2 text-sm text-gray-500">
          <span>表示件数</span>
          <select
            v-model.number="form.per_page"
            @change="submitSearch"
            class="border rounded pl-2 pr-7 py-1 text-sm"
          >
            <option v-for="n in [10,20,30,50]" :key="n" :value="n">{{ n }}件</option>
          </select>
        </div>

        <span class="text-xs text-gray-500 ml-2">絞り込み:</span>

        <input
          v-model="form.name"
          @keyup.enter="submitSearch"
          type="text"
          class="border rounded pl-3 pr-3 py-2 h-9 text-sm"
          placeholder="名前・メール・個人番号で検索..."
        />

        <select v-model="filterYear" @change="submitSearch" class="border rounded pl-3 pr-8 py-2 h-9 text-sm">
          <option value="">更新予定年（すべて）</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}年</option>
        </select>

        <select v-model="cycleStatus" @change="submitSearch" class="border rounded pl-3 pr-8 py-2 h-9 text-sm">
          <option value="">申請ステータス（すべて）</option>
          <option value="before_update">更新前</option>
          <option value="pending">審査中</option>
          <option value="approved">承認</option>
          <option value="reject">却下</option>
          <option value="no_update">更新しない</option>
          <option value="updated">更新済</option>
          <option value="lapsed">資格喪失</option>
        </select>

        <select v-model="renewalFeeStatus" @change="submitSearch" class="border rounded pl-3 pr-8 py-2 h-9 text-sm">
          <option value="">更新料（すべて）</option>
          <option value="unbilled">未請求</option>
          <option value="unpaid">未納</option>
          <option value="paid">支払済</option>
        </select>

        <select v-model="annualFeeStatus" @change="submitSearch" class="border rounded pl-3 pr-8 py-2 h-9 text-sm">
          <option value="">年会費（すべて）</option>
          <option value="unpaid">未納</option>
          <option value="paid">支払済</option>
        </select>

        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          class="text-xs text-gray-500 hover:text-gray-700 underline ml-1"
        >
          絞り込みをクリア
        </button>
      </div>

      <!-- 一括操作エリア -->
      <div class="flex items-center gap-2 mb-4">
        <select
          v-model="bulkAction"
          class="border rounded pl-3 pr-8 py-2 h-9 text-sm"
        >
          <option value="reset">審査前に戻す</option>
          <option value="lapse">資格喪失にする</option>
          <option value="update">認定期間を更新する（更新済にする）</option>
          <option value="stripe">Stripe請求書を作成する</option>
          <option value="invoice">通常の請求書を作成する</option>
        </select>
        <button
          @click="executeBulkAction"
          :disabled="selectedIds.length === 0"
          class="px-3 py-1.5 text-sm rounded-md border bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100 disabled:opacity-50 flex items-center gap-1"
        >
          <span>選択した{{ selectedIds.length }}件に実行</span>
        </button>
        <span
          class="text-xs text-gray-500 whitespace-nowrap"
          :class="{ invisible: selectedIds.length === 0 }"
        >
          ※ 対象外の申請は自動的にスキップされます
        </span>
      </div>

      <!-- テーブルカード -->
      <div class="table-card">
        <div class="table-header">
          <div class="table-header-left">
            <h2 class="table-title">申請者一覧</h2>
            <p class="table-desc">納入状況の更新と更新対象者の設定</p>
          </div>
        </div>

        <!-- テーブル -->
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th class="col-check">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    @change="toggleSelectAll"
                  />
                </th>
                <th>会員番号</th>
                <th>{{ t('name') }}</th>
                <th>取得年</th>
                <th>更新予定年</th>
                <th>年会費</th>
                <th>更新料</th>
                <th>現在の単位</th>
                <th>本申請ステータス</th>
                <th>審査員判定</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="props.members.data.length === 0">
                <td colspan="11" class="empty-row">
                  <div class="empty-state">
                    <span class="empty-icon">📋</span>
                    <p>表示するデータがありません</p>
                  </div>
                </td>
              </tr>
              <tr
                v-for="member in props.members.data"
                :key="member.id"
                :class="{ selected: selectedIds.includes(member.id) }"
              >
                <td class="col-check">
                  <input
                    type="checkbox"
                    :checked="selectedIds.includes(member.id)"
                    @change="toggleSelect(member.id)"
                  />
                </td>
                <td>{{ member.code }}</td>
                <td>{{ member.name }}</td>
                <td>{{ member.update_cycles[0]?.start_date ? new Date(member.update_cycles[0].start_date).getFullYear() : '-' }}年</td>
                <td>{{ member.update_cycles[0]?.renewal_start_date ? new Date(member.update_cycles[0].renewal_start_date).getFullYear() : '-' }}年</td>
                <!-- 年会費 -->
                <td>
                  <span class="status-badge-common w-fit"
                    :class="getAnnualFeeClass(member)">
                    <CheckCircle2 v-if="getAnnualFeeStatus(member) === '納入済'" class="w-3 h-3" />
                    <XCircle v-else class="w-3 h-3" />
                    {{ getAnnualFeeStatus(member) }}
                  </span>
                </td>
                <!-- 更新料 -->
                <td>
                  <span class="status-badge-common w-fit"
                    :class="getRenewalClass(member)">
                    <CheckCircle2 v-if="getRenewalStatus(member) === '納入済'" class="w-3 h-3" />
                    <XCircle v-else class="w-3 h-3" />
                    {{ getRenewalStatus(member) }}
                  </span>
                </td>
                <td class="text-center">{{ member.update_cycles[0]?.total_points ?? '-' }} / 50</td>
                <td>
                  <span :class="['status-badge-common', statusClass(member.update_cycles[0]?.status)]">
                    {{ statusLabel(member.update_cycles[0]?.status) }}
                  </span>
                </td>
                <td>
                  <span :class="['status-badge-common', judgmentClass(member.update_cycles[0]?.reviewer_judgment)]">
                    {{ judgmentLabel(member.update_cycles[0]?.reviewer_judgment) }}
                  </span>
                </td>
                <td class="border px-3 py-2">
                  <div class="flex items-center gap-2">
                    <Link
                      :href="route('admin.instructorMembers.edit', member.id)"
                      class="p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-blue-600"
                      title="編集"
                    >
                      <Pencil class="w-4 h-4" />
                    </Link>
                    <button
                      type="button"
                      class="p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-blue-600"
                      title="年会費・更新料を確認"
                      @click="openFeeDialog(member)"
                    >
                      <Receipt class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ページネーション -->
      <Pagination
        :paginator="props.members"
        :onPageChange="goPage"
        :startItem="startItem"
        :endItem="endItem"
      />

    </div>

    <div>
      <!-- 判定モーダル -->
      <DialogModal :show="reviewModal.show" @close="reviewModal.show = false">
        <template #title>
          {{ t('instructors.update') }}
        </template>

        <template #content>
          <div class="mb-4">
            <label class="block mb-2">{{ t('instructors.choose_status') }}</label>
            <select v-model="reviewModal.status" class="w-full border rounded p-2">
              <option value="updated">{{ t('update') }}</option>
              <option value="no_update">{{ t('no_update') }}</option>
              <option value="rejected">{{ t('rejected') }}</option>
            </select>
          </div>

          <div class="mb-4" v-if="reviewModal.status === 'rejected' || reviewModal.status === 'no_update'">
            <label class="block mb-2">{{ t('instructors.reason') }}</label>
            <textarea
              v-model="reviewModal.reason"
              class="w-full border rounded p-2"
              rows="4"
              placeholder="理由を入力してください"
            ></textarea>
          </div>
        </template>

        <template #footer>
          <SecondaryButton @click="reviewModal.show = false">
            {{ t('cancel') }}
          </SecondaryButton>
          <PrimaryButton class="ms-3" @click="submitReview">
            {{ t('submit') }}
          </PrimaryButton>
        </template>
      </DialogModal>

      <!-- 年会費・更新料 確認ダイアログ -->
      <DialogModal :show="feeDialog.show" @close="feeDialog.show = false" max-width="2xl">
        <template #title>
          {{ feeDialog.memberName }} の年会費・更新料
        </template>

        <template #content>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-gray-500">
                <th class="py-2 text-left">年度</th>
                <th class="py-2 text-left">区分</th>
                <th class="py-2 text-right">請求額</th>
                <th class="py-2 text-right">入金額</th>
                <th class="py-2 text-left">支払方法</th>
                <th class="py-2 text-center">状態</th>
                <th class="py-2 text-center">請求書</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="feeDialog.fees.length === 0">
                <td colspan="7" class="py-8 text-center text-gray-400">請求データがありません</td>
              </tr>
              <tr v-for="fee in feeDialog.fees" :key="fee.id" class="border-b">
                <td class="py-2">{{ fee.fiscal_year }}年度</td>
                <td class="py-2">{{ fee.invoice_name || fee.invoice_type }}</td>
                <td class="py-2 text-right">{{ (fee.total_amount ?? 0).toLocaleString() }}円</td>
                <td class="py-2 text-right">{{ (fee.payment_amount ?? 0).toLocaleString() }}円</td>
                <td class="py-2">{{ fee.payment_method ?? '-' }}</td>
                <td class="py-2 text-center">
                  <span class="status-badge-common" :class="fee.status === 'paid' ? 'status-badge-blue' : 'status-badge-red'">
                    {{ fee.status === 'paid' ? '納入済' : '未納' }}
                  </span>
                </td>
                <td class="py-2 text-center">
                  <a v-if="fee.pdf_path" :href="route('admin.invoices.viewPdf', fee.id)" target="_blank" class="text-blue-600 hover:underline text-xs">
                    表示
                  </a>
                  <span v-else class="text-gray-300 text-xs">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </template>

        <template #footer>
          <SecondaryButton @click="feeDialog.show = false">閉じる</SecondaryButton>
        </template>
      </DialogModal>

    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { CheckCircle2, XCircle, Receipt, Pencil } from 'lucide-vue-next'

const { t } = useI18n()

const page = usePage()

// props
const props = defineProps({
  members: Object,
  filters: Object,
})

const form = reactive({
  name: props.filters.name,
  per_page: props.filters.per_page || 20,
  sort_by: props.filters.sort_by,
  sort_dir: props.filters.sort_dir,
})

// [今回変更] 複合フィルタ（申請ステータス／更新料／年会費）
const cycleStatus = ref(props.filters.cycle_status ?? '')
const renewalFeeStatus = ref(props.filters.renewal_fee_status ?? '')
const annualFeeStatus = ref(props.filters.annual_fee_status ?? '')

const hasActiveFilters = computed(() =>
  !!form.name || !!filterYear.value || !!cycleStatus.value || !!renewalFeeStatus.value || !!annualFeeStatus.value
)

const resetFilters = () => {
  form.name = ''
  filterYear.value = ''
  cycleStatus.value = ''
  renewalFeeStatus.value = ''
  annualFeeStatus.value = ''
  submitSearch()
}

// persistQueryに各検索項目を追加
const persistQuery = () => ({
  name: form.name,
  per_page: form.per_page,
  sort_by: form.sort_by,
  sort_dir: form.sort_dir,
  renewal_year: filterYear.value,
  cycle_status: cycleStatus.value,
  renewal_fee_status: renewalFeeStatus.value,
  annual_fee_status: annualFeeStatus.value,
  page: props.members.current_page
})

const submitSearch = () => {
  router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page: 1 }, {
    preserveState: true,
    replace: true,
    onSuccess: () => resetSelectedIds()
  })
}

// ページ番号クリック
const goPage = (page) => {
  router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page }, {
    preserveState: true,
    replace: true,
    onSuccess: () => resetSelectedIds()
  })
}

// 列ヘッダクリックでソート
const sortBy = (field) => {
  if (form.sort_by === field) form.sort_dir = form.sort_dir==='asc'?'desc':'asc'
  else { form.sort_by = field; form.sort_dir = 'desc' }
  submitSearch()
}
// ---------- 件数計算 ----------
const startItem = computed(() => {
  if (props.members.total === 0) return 0
  return form.per_page * (props.members.current_page - 1) + 1
})

const endItem = computed(() => {
  if (props.members.total === 0) return 0
  return Math.min(form.per_page * props.members.current_page, props.members.total)
})

// 選択削除
const selectedIds = ref([])

// 一覧取得時に既に member.invoices が渡ってきているので、通信は行わず、そのままダイアログに表示するだけにする
const feeDialog = reactive({
  show: false,
  memberName: '',
  fees: [],
})

const openFeeDialog = (member) => {
  feeDialog.memberName = member.name
  feeDialog.fees = member.invoices ?? []
  feeDialog.show = true
}

const toggleSelectAll = (e) => {
  selectedIds.value = e.target.checked
    ? props.members.data.map(m => m.id)
    : []
}

const toggleSelect = (id) => {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter(i => i !== id)
  } else {
    selectedIds.value.push(id)
  }
}

const resetSelectedIds = () => {
  selectedIds.value = []
}

const selectAll = computed({
  get() {
    return selectedIds.value.length === props.members.data.length
  }
})

// [今回追加] 一括操作：セレクトで選んだ操作を、選択した件数に対して実行する
const bulkAction = ref('reset')

const executeBulkAction = () => {
  const actions = {
    reset: bulkResetToBeforeUpdate,
    lapse: bulkLapse,
    update: bulkUpdate,
    stripe: bulkCreateStripeInvoice,
    invoice: bulkCreateInvoice,
  }
  actions[bulkAction.value]?.()
}

// 複数更新（認定期間の更新処理。承認済みのもののみ対象、コントローラ側でも二重チェック）
const bulkUpdate = () => {
  if (!confirm(t('confirm_update_selected'))) return
  router.post(
    route('admin.instructorMembers.bulkUpdate'),
    { ids: selectedIds.value },
    {
      preserveState: true,
      onSuccess: () => {
        resetSelectedIds()
        router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page: props.members.current_page }, { preserveState: true })
      }
    }
  )
}

// 選択した申請を審査前（before_update）に戻す
const bulkResetToBeforeUpdate = () => {
  if (!confirm(`選択した${selectedIds.value.length}件を「審査前」に戻します。よろしいですか？`)) return
  router.post(
    route('admin.instructorMembers.bulkResetToBeforeUpdate'),
    { ids: selectedIds.value },
    {
      preserveState: true,
      onSuccess: () => {
        resetSelectedIds()
        router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page: props.members.current_page }, { preserveState: true })
      }
    }
  )
}

// 一括資格喪失
const bulkLapse = () => {
  if (!confirm(`選択した${selectedIds.value.length}件を指導士資格喪失にします。この操作は取り消せません。よろしいですか？`)) return
  router.post(
    route('admin.instructorMembers.bulkLapse'),
    { ids: selectedIds.value },
    {
      preserveState: true,
      onSuccess: () => {
        resetSelectedIds()
        router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page: props.members.current_page }, { preserveState: true })
      }
    }
  )
}

// [今回追加・現在未実装] Stripe請求書を作成する
const bulkCreateStripeInvoice = () => {
  if (!confirm(`選択した${selectedIds.value.length}件のStripe請求書を作成します。よろしいですか？`)) return
  router.post(
    route('admin.instructorMembers.bulkCreateStripeInvoice'),
    { ids: selectedIds.value },
    {
      preserveState: true,
      onSuccess: () => resetSelectedIds(),
    }
  )
}

// [今回追加・現在未実装] 通常の請求書を作成する
const bulkCreateInvoice = () => {
  if (!confirm(`選択した${selectedIds.value.length}件の請求書を作成します。よろしいですか？`)) return
  router.post(
    route('admin.instructorMembers.bulkCreateInvoice'),
    { ids: selectedIds.value },
    {
      preserveState: true,
      onSuccess: () => resetSelectedIds(),
    }
  )
}

const statusLabel = (status) => {
  const map = {
    'updated':       '更新済',
    'before_update': '未更新',
    'no_update':     '更新しない',
    'pending':       '本申請中',
    'approved':      '承認済み',
    'reject':        '却下',
    'lapsed':        '資格喪失', // [今回追加]
  }
  return map[status] ?? '-'
}

const judgmentLabel = (judgment) => {
  const map = { unreviewed: '未判定', pass: '合格', fail: '不合格', re_review: '再審査' }
  return map[judgment] ?? '未判定'
}

const reviewModal = ref({
  show: false,
  member: null,
  status: 'updated',
  reason: '',
})

function openReviewModal(member) {
  const cycle = member.update_cycles[0]
  if (!cycle) return

  reviewModal.value.show = true
  reviewModal.value.cycleId = cycle.id
  reviewModal.value.memberName = member.name
  reviewModal.value.status = 'updated'
  reviewModal.value.reason = ''
}

function submitReview() {
  if (!reviewModal.value.cycleId) return

  router.post(
    route('admin.instructorUpdateCycles.review', reviewModal.value.cycleId),
    {
      status: reviewModal.value.status,
      reason: reviewModal.value.reason,
    },
    {
      onSuccess: () => {
        reviewModal.value.show = false
        router.get(
          route('admin.instructorMembers.index'),
          { search: search.value, page: props.members.current_page },
          { preserveState: true, preserveScroll: true }
        )
      },
      onError: (errors) => {
        console.error(errors)
      }
    }
  )
}
const getAnnualFeeStatus = (member) => {
  const fees = member.invoices?.filter(f => f.annual_fee > 0)
  if (!fees || fees.length === 0) return '未納'
  return fees.every(f => f.status === 'paid') ? '納入済' : '未納'
}

const getAnnualFeeClass = (member) => {
  return getAnnualFeeStatus(member) === '納入済'
    ? 'status-badge-blue'
    : 'status-badge-red'
}

const getRenewalStatus = (member) => {
  const fee = member.invoices?.find(f => f.renewal_fee > 0)
  if (!fee) return '未請求'
  return fee.status === 'paid' ? '納入済' : '未納'
}

const getRenewalClass = (member) => {
  const status = getRenewalStatus(member)
  if (status === '納入済') return 'status-badge-blue'
  if (status === '未納') return 'status-badge-red'
  return 'status-badge-gray'
}

// --- ロール切替 ---
const roles = [
  { key: 'office', label: '事務局' },
  { key: 'examiner', label: '審査員' },
  { key: 'applicant', label: '申請者' },
]
const currentRole = ref('office')

// --- タブ ---
const tabs = [
  { key: 'applicants', label: '申請者・納入管理', icon: '👤' },
  { key: 'settings', label: 'システム設定', icon: '⚙️' },
]
const currentTab = ref('applicants')

// --- 検索・フィルター ---
const filterYear = ref(props.filters.renewal_year ?? '')
const yearOptions = [2024, 2025, 2026, 2027]

// --- ステータスクラス ---
const statusClass = (status) => {
  const map = {
    'updated':       'status-badge-emerald',
    'before_update': 'status-badge-blue',
    'no_update':     'status-badge-gray',
    'pending':       'status-badge-yellow',
    'approved':      'status-badge-green',
    'reject':        'status-badge-red',
    'lapsed':        'status-badge-gray', // [今回変更] 濃い塗りつぶしから淡い共通スタイルに統一
  }
  return map[status] ?? 'status-badge-gray'
}

// [今回追加] 審査員判定バッジ用（共通クラス名を返す）
const judgmentClass = (judgment) => {
  const map = {
    'pass':      'status-badge-green',
    'fail':      'status-badge-red',
    're_review': 'status-badge-orange',
  }
  return map[judgment] ?? 'status-badge-gray'
}

// --- アクション ---
const handleImport = () => alert('会員情報インポート (SMOOSY)')
const handlePaymentSync = () => alert('入金データ同期 (SMOOSY)')
const handleExport = () => alert('判定完了者リスト出力 (SMOOSY)')
const handleEdit = (member) => alert(`編集: ${member.name}`)

</script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
.table-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; }
.table-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; gap: 16px; flex-wrap: wrap; border-bottom: 1px solid #f3f4f6; }
.table-title { font-size: 15px; font-weight: 700; color: #111827; }
.table-desc { font-size: 12px; color: #6b7280; margin-top: 2px; }
.table-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th { padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.9); background: #1D4E89; border-bottom: 1px solid #163B68; white-space: nowrap; }
.data-table td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; color: #374151; white-space: nowrap; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr.selected td { background: #eff6ff; }
.data-table tbody tr:hover td { background: #f9fafb; }
.col-check { width: 44px; text-align: center; }
.empty-row { padding: 60px 16px !important; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #9ca3af; }
.empty-icon { font-size: 32px; opacity: 0.4; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1.5px solid transparent; transition: all 0.15s; white-space: nowrap; }
</style>
