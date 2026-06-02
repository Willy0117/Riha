<template>
  <AppLayout>
    <template #header>
        <div>
          <h2 class="text-2xl font-bold text-gray-800">事務局ポータル</h2>
          <p class="text-xs text-gray-500 mt-1">申請者の提出書類を確認し、審査を行います。</p>
        </div>
    </template>
    <div class="p-6">
      <!-- per_page + add -->
      <div class="flex flex-wrap md:flex-nowrap md:justify-between mb-4 items-center gap-2">

        <!-- per_page + add -->
        <div class="flex items-center gap-2">
          <select
            v-model.number="form.per_page"
            @change="submitSearch"
            class="border rounded px-3 py-2 w-16 h-10"
          >
            <option v-for="n in [10,20,30,50]" :key="n" :value="n">{{ n }}</option>
          </select>

        </div>

        <button
          @click="bulkUpdate"
          :disabled="selectedIds.length === 0"
          class="px-4 h-10 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 flex items-center space-x-1"
        >
          <BadgeCheck class="w-4 h-4"/>
          <span>{{ t('update_selected') }}</span>
        </button>
      </div>
            <!-- ページヘッダー -->

      <!-- テーブルカード -->
      <div class="table-card">
        <div class="table-header">
          <div class="table-header-left">
            <h2 class="table-title">申請者一覧</h2>
            <p class="table-desc">納入状況の更新と更新対象者の設定</p>
          </div>
          <div class="table-controls">
            <button
              class="btn btn-danger-outline"
              :disabled="selectedIds.length === 0"
              @click="handleDeleteSelected"
            >
              選択した申請者を削除 ({{ selectedIds.length }}名)
            </button>
            <div class="filter-select-wrapper">
              <span class="filter-icon">▼</span>
              <select v-model="filterYear" class="filter-select">
                <option value="">更新予定年（すべて）</option>
                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}年</option>
              </select>
            </div>
            <div class="search-wrapper">
              <span class="search-icon">🔍</span>
              <input
                v-model="searchQuery"
                type="text"
                class="search-input"
                placeholder="名前・メール・個人番号で検索..."
              />
            </div>
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
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="props.members.data.length === 0">
                <td colspan="10" class="empty-row">
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
                  <span class="flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full w-fit"
                    :class="getAnnualFeeClass(member)">
                    <CheckCircle2 v-if="getAnnualFeeStatus(member) === '納入済'" class="w-3 h-3" />
                    <XCircle v-else class="w-3 h-3" />
                    {{ getAnnualFeeStatus(member) }}
                  </span>
                </td>
                <!-- 更新料 -->
                <td>
                  <span class="flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full w-fit"
                    :class="getRenewalClass(member)">
                    <CheckCircle2 v-if="getRenewalStatus(member) === '納入済'" class="w-3 h-3" />
                    <XCircle v-else class="w-3 h-3" />
                    {{ getRenewalStatus(member) }}
                  </span>
                </td>
                <td class="text-center">{{ member.update_cycles[0]?.total_points ?? '-' }} / 50</td>
                <td>
                  <span :class="['status-badge', statusClass(member.update_cycles[0]?.status)]">
                    {{ statusLabel(member.update_cycles[0]?.status) }}
                  </span>
                </td>
                <td class="border px-3 py-2">
                  <Link
                    :href="route('admin.instructorMembers.show', member.id)"
                    class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs"
                  >
                    {{ t('edit') }}
                  </Link>
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
      <!-- 審査モーダル -->
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
import { BadgeCheck, CheckCircle2, XCircle } from 'lucide-vue-next'

const { t } = useI18n()

const page = usePage()

// props
const props = defineProps({
  members: Object, // 👈 paginator オブジェクトに変更
  filters: Object, // { search: "" }
})

const form = reactive({
  name: props.filters.name,
  per_page: props.filters.per_page || 20,
  sort_by: props.filters.sort_by,   // ← 初期値を必ずセット
  sort_dir: props.filters.sort_dir,    // ← 初期値を必ずセット
})
// persistQueryに各検索項目を追加
const persistQuery = () => ({
  name: form.name,
  per_page: form.per_page,
  sort_by: form.sort_by,
  sort_dir: form.sort_dir,
  page: props.members.current_page
})

const submitSearch = () => {
  console.log(persistQuery())
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
// ---------- 件数計算（あなたのロジック） ----------
const startItem = computed(() => {
  if (props.members.total === 0) return 0
  return form.per_page * (props.members.current_page - 1) + 1
})

const endItem = computed(() => {
  if (props.members.total === 0) return 0
  return Math.min(form.per_page * props.members.current_page, props.members.total)
})

// statusLabel / statusColor を function で定義
/*
function statusLabel(s) {
  return {
    updated: t('updated'),
    before_update: t('before_update'),
    no_update: t('no_update'),
    rejected: t('rejected')
  }[s] || '-'
}
*/
// 選択削除
const selectedIds = ref([])

const toggleSelectAll = (checked) => {
  selectedIds.value = checked ? props.members.data.map(s => s.id) : []
}

const resetSelectedIds = () => {
  selectedIds.value = []
}

const selectAll = computed({
  get() {
    return selectedIds.value.length === props.members.data.length
  }
})

// 複数更新
const bulkUpdate = () => {
  if (!confirm(t('confirm_update_selected'))) return
  router.post(
    route('admin.instructorMembers.bulkUpdate'),
    { ids: selectedIds.value },
    {
      preserveState: true,
      onSuccess: () => {
        router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page: props.members.current_page }, { preserveState: true })
      }
    }
  )
}

const statusLabel = (status) => {
  const map = {
    'updated':       '更新済',
    'before_update': '未更新',
    'no_update':     '更新しない',
    'pending':       '本申請中',
  }
  return map[status] ?? '-'
}

const reviewModal = ref({
  show: false,
  member: null,
  status: 'updated',
  reason: '',
})

function openReviewModal(member) {
  const cycle = member.update_cycles[0] // 最新の更新サイクル
  if (!cycle) return

  reviewModal.value.show = true
  reviewModal.value.cycleId = cycle.id
  reviewModal.value.memberName = member.name
  reviewModal.value.status = 'updated'  // デフォルト選択
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
  const fees = member.annual_fees?.filter(f => f.annual_fee > 0)
  if (!fees || fees.length === 0) return '未納'
  return fees.every(f => f.status === 'paid') ? '納入済' : '未納'
}

const getAnnualFeeClass = (member) => {
  return getAnnualFeeStatus(member) === '納入済'
    ? 'bg-blue-50 text-blue-700'
    : 'bg-red-50 text-red-700'
}

const getRenewalStatus = (member) => {
  const fee = member.annual_fees?.find(f => f.renewal_fee > 0)
  if (!fee) return '未請求'
  return fee.status === 'paid' ? '納入済' : '未納'
}

const getRenewalClass = (member) => {
  const status = getRenewalStatus(member)
  if (status === '納入済') return 'bg-blue-50 text-blue-700'
  if (status === '未納') return 'bg-red-50 text-red-700'
  return 'bg-gray-100 text-gray-500'
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
const searchQuery = ref('')
const filterYear = ref('')
const yearOptions = [2024, 2025, 2026, 2027]

// --- ステータスクラス ---
const statusClass = (status) => {
  const map = {
    'updated':       'bg-green-50 text-green-600 font-semibold',
    'before_update': 'bg-blue-50 text-blue-600 font-semibold',
    'no_update':     'bg-gray-100 text-gray-600',
    'pending':       'bg-yellow-50 text-yellow-600',
  }
  return map[status] ?? 'bg-gray-100 text-gray-600'
}

// --- アクション ---
const handleImport = () => alert('会員情報インポート (SMOOSY)')
const handlePaymentSync = () => alert('入金データ同期 (SMOOSY)')
const handleExport = () => alert('審査完了者リスト出力 (SMOOSY)')
const handleDeleteSelected = () => {
  if (confirm(`選択した ${selectedIds.value.length} 名を削除しますか？`)) {
    members.value = members.value.filter((m) => !selectedIds.value.includes(m.id))
    selectedIds.value = []
  }
}
const handleEdit = (member) => alert(`編集: ${member.name}`)

</script>
<style>
/* ===== リセット・基本 ===== */
* { box-sizing: border-box; margin: 0; padding: 0; }

.app-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f4f5f7;
  font-family: 'Hiragino Sans', 'Noto Sans JP', sans-serif;
  color: #1a1a2e;
}

/* ===== ヘッダー ===== */
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 32px;
  height: 64px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #2563eb;
  color: #fff;
  font-weight: 800;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-title {
  display: block;
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
}

.logo-sub {
  display: block;
  font-size: 10px;
  color: #6b7280;
  letter-spacing: 0.08em;
}

.header-nav {
  display: flex;
  gap: 4px;
  background: #f3f4f6;
  border-radius: 8px;
  padding: 4px;
}

.nav-btn {
  padding: 6px 18px;
  border: none;
  border-radius: 6px;
  background: transparent;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
}

.nav-btn.active {
  background: #fff;
  color: #111827;
  box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}

.header-user {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-name {
  display: block;
  font-size: 13px;
  font-weight: 600;
  text-align: right;
}

.user-role {
  display: block;
  font-size: 10px;
  color: #9ca3af;
  text-align: right;
}

.user-avatar img {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
}

/* ===== メイン ===== */
.main {
  flex: 1;
  max-width: 1280px;
  width: 100%;
  margin: 0 auto;
  padding: 40px 32px 32px;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* ===== ページヘッダー ===== */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 28px;
  font-weight: 800;
  color: #1e3a6e;
  font-style: italic;
  letter-spacing: -0.5px;
}

.page-desc {
  margin-top: 6px;
  font-size: 13px;
  color: #6b7280;
  line-height: 1.6;
}

.page-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

/* ===== ボタン ===== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: 1.5px solid transparent;
  transition: all 0.15s;
  white-space: nowrap;
}

.btn-icon {
  font-size: 14px;
}

.btn-primary {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.btn-outline {
  background: #fff;
  color: #2563eb;
  border-color: #2563eb;
}

.btn-outline:hover {
  background: #eff6ff;
}

.btn-danger-outline {
  background: #fff;
  color: #dc2626;
  border-color: #fca5a5;
  font-size: 12px;
  padding: 7px 14px;
}

.btn-danger-outline:disabled {
  color: #9ca3af;
  border-color: #e5e7eb;
  cursor: not-allowed;
}

/* ===== タブ ===== */
.tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #e5e7eb;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.15s;
}

.tab-btn.active {
  color: #2563eb;
  border-bottom-color: #2563eb;
  font-weight: 600;
}

/* ===== テーブルカード ===== */
.table-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  gap: 16px;
  flex-wrap: wrap;
  border-bottom: 1px solid #f3f4f6;
}

.table-title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}

.table-desc {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.table-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-select-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.filter-icon {
  position: absolute;
  left: 10px;
  font-size: 10px;
  color: #6b7280;
  pointer-events: none;
}

.filter-select {
  padding: 7px 12px 7px 26px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #374151;
  background: #fff;
  cursor: pointer;
  appearance: none;
}

.search-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 10px;
  font-size: 13px;
  pointer-events: none;
}

.search-input {
  padding: 7px 12px 7px 32px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #374151;
  width: 240px;
  outline: none;
  transition: border-color 0.15s;
}

.search-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* ===== テーブル ===== */
.table-wrapper {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.data-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  white-space: nowrap;
}

.data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f3f4f6;
  color: #374151;
  white-space: nowrap;
}

.data-table tr:last-child td {
  border-bottom: none;
}

.data-table tr.selected td {
  background: #eff6ff;
}

.data-table tbody tr:hover td {
  background: #f9fafb;
}

.col-check {
  width: 44px;
  text-align: center;
}

.empty-row {
  padding: 60px 16px !important;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #9ca3af;
}

.empty-icon {
  font-size: 32px;
  opacity: 0.4;
}

/* ===== ステータスバッジ ===== */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.status-applied   { background: #dbeafe; color: #1d4ed8; }
.status-reviewing { background: #fef3c7; color: #d97706; }
.status-approved  { background: #d1fae5; color: #065f46; }
.status-pending   { background: #f3f4f6; color: #6b7280; }
.status-rejected  { background: #fee2e2; color: #dc2626; }

/* ===== 操作ボタン ===== */
.action-btn {
  padding: 5px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  color: #374151;
  transition: all 0.15s;
}

.action-btn:hover {
  border-color: #2563eb;
  color: #2563eb;
}

/* ===== フッター ===== */
.footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 32px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
  font-size: 12px;
  color: #9ca3af;
}

.footer-nav {
  display: flex;
  gap: 20px;
}

.footer-nav a {
  color: #6b7280;
  text-decoration: none;
  transition: color 0.15s;
}

.footer-nav a:hover {
  color: #2563eb;
}
</style>