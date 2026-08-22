<template>
  <AppLayout :title="`${member.name} - ${t('instructor_details')}`">
    <template #header>{{ member.name }}</template>

    <div class="p-6 space-y-6">

      <!-- 戻るボタン -->
      <button
        @click="backToIndex"
        class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700"
      >
        <ArrowLeft class="w-4 h-4" />
        {{ t('back') }}
      </button>

      <!-- 会員情報 -->
      <div class="grid grid-cols-6 gap-6">
        <div class="col-span-6 md:col-span-4 bg-white rounded-xl border border-gray-200 p-6">
          <div class="flex justify-between items-start mb-1">
            <div class="flex items-center gap-2 mb-1.5">
              <Award class="w-5 h-5 text-blue-600" />
              <h2 class="text-base font-bold text-gray-900">{{ member.name }}</h2>
            </div>
            <span :class="badgeClass">{{ badgeLabel }}</span>
          </div>

          <p class="text-[13px] text-gray-500 mb-5">資格更新状況の確認画面です。</p>

          <div class="grid grid-cols-2 gap-4 mt-2">
            <div class="border border-gray-200 rounded-lg py-3.5 px-4">
              <span class="block text-xs text-gray-500 mb-1.5">{{ t('instructors.total_points') }}</span>
              <span class="block text-[28px] font-bold text-blue-600">{{ cycle.total_points }} <span class="text-sm font-normal text-gray-500">単位</span></span>
            </div>
            <div class="border border-gray-200 rounded-lg py-3.5 px-4">
              <span class="block text-xs text-gray-500 mb-1.5">{{ t('instructors.conference_count') }}</span>
              <span class="block text-2xl font-bold text-red-600">{{ cycle.conference_count }} <span class="text-sm font-normal text-gray-500">回</span></span>
            </div>
          </div>
        </div>

        <div class="col-span-6 md:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
          <div class="flex items-center gap-2 mb-1.5">
            <Calendar class="text-orange-500" :size="20" />
            <h2 class="text-base font-bold text-gray-900">認定・更新期間</h2>
          </div>

          <div class="mb-4">
            <p class="text-xs text-gray-500 mb-1">{{ t('instructors.update_period') }}</p>
            <p class="text-lg font-bold text-gray-900">{{ cycle.start_date ? dayjs(cycle.start_date).format('YYYY-MM-DD') : '-' }} 〜 {{ cycle.end_date ? dayjs(cycle.end_date).format('YYYY-MM-DD') : '-' }}</p>
          </div>

          <div class="bg-orange-50 rounded-lg p-3">
            <p class="text-xs text-orange-500 font-semibold mb-1">現在のステータス</p>
            <p class="text-lg font-bold text-orange-500">{{ t(`cycle_status.${cycle.status}`) }}</p>
          </div>
        </div>
      </div>

      <!-- 参加登録なし警告 -->
      <div
        v-if="missingParticipationSessions.length > 0"
        class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-2 text-red-600"
      >
        <XCircle class="w-5 h-5 flex-shrink-0 mt-0.5" />
        <p class="text-sm font-semibold">
          第{{ missingParticipationSessions.join('回、第') }}回の日本腎臓リハビリテーション学会について、「参加」の登録がありません。
        </p>
      </div>

      <!-- アップロード一覧 -->
      <div>
        <h2 class="text-xl font-bold text-gray-800 mb-4">{{ t('uploaded_files') }}</h2>

        <div
          v-if="groupedUploads.length === 0"
          class="bg-gray-50 border border-dashed border-gray-300 rounded-2xl p-10 text-center text-gray-500"
        >
          {{ t('no_uploads') }}
        </div>

        <div v-else class="space-y-6">
          <div
            v-for="group in groupedUploads"
            :key="group.key"
            class="space-y-3"
          >
            <!-- グループ見出し -->
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-bold text-indigo-900">
                {{ group.displayTitle }}
              </h3>
              <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                合計 +{{ group.points }} 単位
              </span>
            </div>

            <!-- グループ内の各アップロード -->
            <div
              v-for="upload in group.items"
              :key="upload.id"
              class="flex gap-6 p-6 bg-white rounded-xl border border-gray-200"
            >
              <!-- 左：サムネイル -->
              <div class="w-64 flex-none">
                <img
                  v-if="upload.thumbnail_url"
                  :src="upload.thumbnail_url"
                  class="w-full h-64 object-cover rounded-lg cursor-pointer"
                  @click="openPreview(upload.id)"
                />
                <div
                  v-else
                  class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm cursor-pointer"
                  @click="openPreview(upload.id)"
                >
                  <FileText class="w-8 h-8" />
                </div>
              </div>

              <!-- 右：詳細 -->
              <div class="flex-1 flex flex-col gap-4">

                <!-- ヘッダー -->
                <div class="flex items-start justify-between">
                  <div>
                    <p class="text-sm text-gray-700 mt-1">
                      {{ upload.role_name }}
                    </p>
                    <div class="flex items-center gap-3 mt-2">
                      <span class="flex items-center gap-1 text-xs text-gray-500">
                        <Calendar class="w-3.5 h-3.5" />
                        {{ upload.issued_date?.split('T')[0] }}
                      </span>
                      <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                        +{{ upload.points }} 単位
                      </span>
                    </div>
                  </div>
                  <span
                    class="text-xs px-2 py-1 rounded-full font-medium"
                    :class="{
                      'bg-yellow-50 text-yellow-600 border border-yellow-200': upload.status === 'pending',
                      'bg-green-50 text-green-600 border border-green-200': upload.status === 'approved',
                      'bg-red-50 text-red-600 border border-red-200': upload.status === 'rejected',
                    }"
                  >
                    {{ statusLabel(upload.status) }}
                  </span>
                </div>

                <!-- 承認済み -->
                <div v-if="upload.status === 'approved'" class="bg-green-50 rounded-xl p-4 flex items-center gap-2 text-green-700">
                  <CheckCircle2 class="w-5 h-5" />
                  <span class="font-semibold">この書類は承認済みです。</span>
                  <p class="text-xs mt-1 text-green-600">{{ dayjs(upload.updated_at).format('YYYY/MM/DD HH:mm') }}・ {{ upload.reviewer?.name }}</p>
                </div>

                <!-- 差し戻し済み -->
                <div v-else-if="upload.status === 'rejected'" class="bg-red-50 rounded-xl p-4 space-y-1">
                  <div class="flex items-center gap-2 text-red-600 font-semibold">
                    <XCircle class="w-5 h-5" />
                    差し戻し
                  </div>
                  <p class="text-xs mt-1 text-red-400">{{ dayjs(upload.updated_at).format('YYYY/MM/DD HH:mm') }}・ {{ upload.reviewer?.name }}</p>
                  <p class="text-sm text-red-500">{{ upload.rejection_message }}</p>
                </div>

                <!-- 未審査：フォーム表示 -->
                <template v-else>
                  <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                    <div class="space-y-1">
                      <label class="text-xs font-medium text-gray-600">差し戻し理由の選択</label>
                      <select v-model="rejectReasons[upload.id]" class="input-field text-sm">
                        <option value="" disabled>理由を選択してください</option>
                        <option value="unclear">書類が不鮮明</option>
                        <option value="wrong">書類の種類が違う</option>
                        <option value="missing">必要情報が不足</option>
                        <option value="other">その他</option>
                      </select>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs font-medium text-gray-600">詳細コメント</label>
                      <textarea
                        v-model="rejectComments[upload.id]"
                        class="input-field text-sm resize-none w-full"
                        rows="3"
                        placeholder="具体的な不備内容を入力してください..."
                      />
                    </div>
                  </div>

                  <div class="flex gap-3">
                    <Button
                      class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold"
                      @click="handleApprove(upload.id)"
                    >
                      <CheckCircle2 class="w-4 h-4 mr-2" />
                      この書類を承認する
                    </Button>
                    <Button
                      variant="outline"
                      class="flex-1 text-red-600 border-red-300 hover:bg-red-50 font-bold"
                      @click="handleReject(upload)"
                    >
                      <XCircle class="w-4 h-4 mr-2" />
                      差し戻す
                    </Button>
                  </div>
                </template>

              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow-sm border border-gray-200">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full border-2 border-gray-300 flex items-center justify-center text-gray-400">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <div>
          <p class="font-semibold text-gray-800">審査の最終確認</p>
          <p class="text-sm text-gray-500">すべての提出書類が「承認済み」になると完了ボタンが有効になります。</p>
        </div>
      </div>
      <button
        :disabled="!allApproved"
        :class="[
          'px-6 py-2 rounded-lg text-sm font-semibold transition',
          allApproved
            ? 'bg-blue-600 text-white hover:bg-blue-700'
            : 'bg-gray-100 text-gray-400 cursor-not-allowed'
        ]"
      >
        審査を完了として確定する
      </button>
    </div>

    <!-- PDFプレビューDialog -->
    <Dialog :open="!!previewPdf" @update:open="previewPdf = null">
      <DialogContent class="w-[95vw] max-w-[95vw] h-[95vh] max-h-[95vh] p-0 flex flex-col">
        <DialogHeader class="px-4 py-3 border-b">
          <DialogTitle>{{ t('PDFpreview') }}</DialogTitle>
        </DialogHeader>
        <div class="flex-1 overflow-hidden">
          <iframe
            v-if="previewPdf"
            :src="previewPdf"
            class="w-full h-full border-0"
          />
        </div>
        <DialogFooter class="px-4 py-3 border-t">
          <Button variant="outline" @click="previewPdf = null">{{ t('closed') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
  ArrowLeft, FileText, Calendar, CheckCircle2, XCircle, Award
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogHeader,
  DialogTitle, DialogFooter
} from '@/components/ui/dialog'
import dayjs from 'dayjs'

const { t } = useI18n()

const props = defineProps({
  member: { type: Object, required: true },
  uploads: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const member = computed(() => props.member)

const cycle = computed(() => member.value.update_cycles?.[0] || {
  start_date: '-',
  end_date: '-',
  total_points: 0,
  conference_count: 0,
  status: 'before_update'
})

const badgeLabel = computed(() => {
  switch (cycle.value.status) {
    case 'pending':   return '申請中'
    case 'no_update': return '更新を辞退'
    default:          return '蓄積中（一時保存）'
  }
})

const badgeClass = computed(() => {
  switch (cycle.value.status) {
    case 'pending':
      return 'text-[11px] font-semibold text-blue-600 bg-blue-50 border border-blue-200 rounded-full px-2.5 py-[3px] whitespace-nowrap'
    case 'no_update':
      return 'text-[11px] font-semibold text-gray-500 bg-gray-100 border border-gray-200 rounded-full px-2.5 py-[3px] whitespace-nowrap'
    default:
      return 'text-[11px] font-semibold text-amber-600 bg-amber-100 border border-amber-200 rounded-full px-2.5 py-[3px] whitespace-nowrap'
  }
})

// グループごとの表示タイトルを組み立てる
// 「学術集会」「講習会」の場合は 第◯回+学会名、それ以外は session + 区分名
function buildDisplayTitle(group) {
  if (group.credit_category_name === '学術集会' || group.credit_category_name === '講習会') {
    return group.session
      ? `第${group.session}回 ${group.credit_conference_name}`
      : group.credit_conference_name
  }
  return `${group.session ?? ''} ${group.credit_category_name ?? ''}`.trim()
}

// グループ + 各アイテムにフロント表示用のフィールドを付与
// props.uploads が変わるたび（＝Inertiaの再訪問のたび）に自動で再計算される
const groupedUploads = computed(() =>
  (props.uploads ?? []).map((group, index) => ({
    key: `${group.session}-${group.credit_category_id}-${group.credit_conference_id}-${index}`,
    ...group,
    displayTitle: buildDisplayTitle(group),
    items: (group.items ?? []).map(u => ({
      ...u,
      thumbnail_url: u.thumbnail_path ? `/storage/${u.thumbnail_path}` : null,
      credit_conference_name: u.credit_conference?.name || u.credit_conference_name || '',
      category_name: u.credit_category?.name || u.credit_category_name || '',
      role_name: u.credit_role?.role || u.role_name || ''
    }))
  }))
)

// 参加登録が無い「学術集会・日本腎臓リハビリテーション学会」グループを検出
const missingParticipationSessions = computed(() => {
  return groupedUploads.value
    .filter(group =>
      group.credit_category_name === '学術集会' &&
      group.credit_conference_name === '日本腎臓リハビリテーション学会' &&
      !(group.roles ?? []).includes('参加')
    )
    .map(group => group.session)
})

// 各uploadごとに差し戻し理由・コメントを管理
const rejectReasons = ref({})
const rejectComments = ref({})

const previewPdf = ref(null)

const statusLabel = (status) => {
  const map = {
    pending:  '未審査',
    approved: '承認済み',
    rejected: '差し戻し',
  }
  return map[status] ?? '-'
}

const openPreview = (id) => {
  previewPdf.value = `/admin/pdf-uploads/${id}/view`
}

const handleApprove = (id) => {
  if (!confirm('この書類を承認しますか？')) return
  router.post(
    route('admin.pdf-uploads.approve', { pdf: id }),
    {},
    { preserveScroll: true }
  )
}

const handleReject = (upload) => {
  const reason = rejectReasons.value[upload.id]
  const comment = rejectComments.value[upload.id] ?? ''

  if (!reason) {
    alert('差し戻し理由を選択してください')
    return
  }
  if (!confirm('この書類を差し戻しますか？')) return

  router.post(
    route('admin.pdf-uploads.reject', { pdf: upload.id }),
    { rejection_message: comment || reason },
    { preserveScroll: true }
  )
}

const backToIndex = () => {
  router.get(route('admin.approvals.index'), {
    search: props.filters?.search ?? '',
    page: props.filters?.page ?? 1,
  })
}

const allApproved = computed(() =>
  groupedUploads.value.every(group =>
    group.items.every(upload => upload.status === 'approved')
  )
)

</script>

<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
.dashboard {
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px;
  display: flex;
  flex-direction: column;
  gap: 24px;
  font-family: 'Hiragino Sans', 'Noto Sans JP', sans-serif;
  color: #1a1a2e;
}

/* 上段グリッド */
.grid-top {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
}

/* カード共通 */
.card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 24px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 4px;
}

.card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.card-title h2 {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}

.card-icon { font-size: 18px; }
.card-icon.orange { color: #f97316; }
.card-icon.green  { color: #10b981; }

.card-desc {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 20px;
}

/* 蓄積中バッジ */
.badge-draft {
  font-size: 11px;
  font-weight: 600;
  color: #d97706;
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 20px;
  padding: 3px 10px;
  white-space: nowrap;
}

/* プログレス */
.progress-area {
  margin-bottom: 20px;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 10px;
}

.unit-display {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.unit-current {
  font-size: 28px;
  font-weight: 700;
  color: #2563eb;
}

.unit-separator {
  font-size: 14px;
  color: #6b7280;
}

.unit-right {
  text-align: right;
}

.unit-remaining {
  display: block;
  font-size: 13px;
  color: #2563eb;
  font-weight: 600;
}

.gakkai-info {
  display: block;
  font-size: 12px;
  color: #dc2626;
  font-weight: 600;
  margin-top: 2px;
}

.progress-bar-bg {
  height: 8px;
  background: #e5e7eb;
  border-radius: 99px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: #2563eb;
  border-radius: 99px;
  transition: width 0.4s ease;
}

/* 統計ボックス */
.stats-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 8px;
}

.stat-box {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px 16px;
}

.stat-label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
}

.stat-value {
  display: block;
  font-size: 24px;
  font-weight: 700;
  color: #111827;
}

.stat-value.pending {
  color: #2563eb;
}

/* 認定・更新期間 */
.period-block {
  margin-bottom: 16px;
}

.period-block.renewal {
  background: #fff7ed;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 16px;
}

.period-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.period-label.orange { color: #f97316; font-weight: 600; }

.period-value {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.period-value.orange { color: #f97316; }

/* 情報ボックス */
.info-box {
  display: flex;
  gap: 8px;
  background: #f0f9ff;
  border-radius: 8px;
  padding: 12px;
  font-size: 12px;
  color: #374151;
  line-height: 1.6;
}

.info-icon { flex-shrink: 0; }

/* 支払い */
.card-payment { max-width: calc(100% - 340px - 24px); }

.payment-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

.payment-box {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.payment-label {
  font-size: 12px;
  color: #6b7280;
  display: block;
  margin-bottom: 4px;
}

.payment-status {
  display: flex;
  align-items: center;
  gap: 8px;
}

.payment-value {
  font-size: 16px;
  font-weight: 600;
}

.payment-value.unpaid { color: #111827; }
.payment-value.paid   { color: #10b981; }

.payment-clock { font-size: 18px; color: #d1d5db; }

.warning-box {
  display: flex;
  gap: 8px;
  background: #eff6ff;
  border-radius: 8px;
  padding: 12px;
  font-size: 12px;
  color: #374151;
  line-height: 1.6;
}

.warning-icon { flex-shrink: 0; }

/* セクションヘッダー */
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  font-size: 18px;
  font-weight: 800;
  color: #1e3a6e;
  font-style: italic;
}

.btn-primary {
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px 20px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-primary:hover { background: #1d4ed8; }

/* テーブル */
.card-table { padding: 0; overflow: hidden; }

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.data-table th {
  padding: 12px 20px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.data-table td {
  padding: 14px 20px;
  border-bottom: 1px solid #f3f4f6;
  color: #374151;
}

.empty-row { padding: 48px 20px !important; }

.empty-state {
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
}

/* ステータスバッジ */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.status-approved  { background: #d1fae5; color: #065f46; }
.status-reviewing { background: #fef3c7; color: #d97706; }
.status-applied   { background: #dbeafe; color: #1d4ed8; }
.status-rejected  { background: #fee2e2; color: #dc2626; }

.action-btn {
  padding: 5px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  font-size: 12px;
  cursor: pointer;
  color: #374151;
  transition: all 0.15s;
}

.action-btn:hover {
  border-color: #2563eb;
  color: #2563eb;
}
/* DialogのOverlayを薄くする */
[data-radix-popper-content-wrapper],
.fixed.inset-0.z-50.bg-black\/80 {
  background-color: rgba(0, 0, 0, 0.4) !important;
}

</style>