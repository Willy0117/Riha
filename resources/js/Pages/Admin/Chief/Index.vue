<template>
  <AppLayout>
    <template #header>
      <div>
        <h2 class="text-2xl font-bold text-gray-800">審査委員長ポータル</h2>
        <p class="text-xs text-gray-500 mt-1">更新申請の最終判定（承認・却下・更新なし）を行います。</p>
      </div>
    </template>

    <div class="p-6 space-y-4">

      <!-- 一括操作ツールバー -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <Button
              size="sm"
              class="bg-emerald-600 hover:bg-emerald-700 text-white"
              :disabled="selectedIds.length === 0"
              @click="openBulkModal('approved')"
            >
              <CheckCircle2 class="w-3.5 h-3.5 mr-1" />
              選択した{{ selectedIds.length }}件を承認
            </Button>
            <Button
              size="sm"
              variant="outline"
              class="text-red-600 border-red-300 hover:bg-red-50"
              :disabled="selectedIds.length === 0"
              @click="openBulkModal('reject')"
            >
              <XCircle class="w-3.5 h-3.5 mr-1" />
              選択した{{ selectedIds.length }}件を却下
            </Button>
          </div>
          <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>表示件数</span>
            <select v-model="perPage" @change="changePerPage" class="border rounded pl-2 pr-7 py-1 text-sm">
              <option :value="10">10件</option>
              <option :value="20">20件</option>
              <option :value="50">50件</option>
              <option :value="100">100件</option>
            </select>
          </div>
        </div>
        <span v-if="selectedIds.length > 0" class="text-xs text-gray-500">
          ※ 書類の審査が完了していない申請は一括処理の対象外になります
        </span>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr>
              <th class="px-3 py-3 w-10 bg-gray-50 border-b border-gray-200">
                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" />
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">会員番号</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">氏名</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">申請日</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">書類の審査状況</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">未審査件数</th>
              <th
                class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200 cursor-pointer select-none hover:text-gray-700"
                @click="toggleSort('approved_points_total')"
              >
                承認済み単位数 <span class="text-gray-300">{{ sortArrow('approved_points_total') }}</span>
              </th>
              <th
                class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200 cursor-pointer select-none hover:text-gray-700"
                @click="toggleSort('approved_conference_count')"
              >
                承認済み参加回数 <span class="text-gray-300">{{ sortArrow('approved_conference_count') }}</span>
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">担当審査員</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">審査員判定</th>
              <th
                class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200 cursor-pointer select-none hover:text-gray-700"
                @click="toggleSort('reviewer_judged_at')"
              >
                審査日時 <span class="text-gray-300">{{ sortArrow('reviewer_judged_at') }}</span>
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cycles.data.length === 0">
              <td colspan="12" class="!p-12 text-center text-gray-400">
                判定待ちの申請はありません
              </td>
            </tr>
            <tr v-for="cycle in cycles.data" :key="cycle.id" :class="{ 'bg-blue-50/50': selectedIds.includes(cycle.id) }">
              <td class="px-3 py-3.5 border-b border-gray-100">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(cycle.id)"
                  @change="toggleSelect(cycle.id)"
                />
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100">{{ cycle.member?.code }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 font-medium">{{ cycle.member?.name }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.updated_at?.split('T')[0] }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100">
                <span
                  v-if="cycle.document_review_completed"
                  class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200"
                >
                  審査終了（{{ cycle.document_reviewed_count }}/{{ cycle.document_total_count }}）
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200"
                >
                  審査中（{{ cycle.document_reviewed_count }}/{{ cycle.document_total_count }}）
                </span>
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-center font-semibold" :class="cycle.document_pending_count > 0 ? 'text-amber-600' : 'text-gray-400'">
                {{ cycle.document_pending_count }}
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-center font-semibold text-blue-600">
                {{ cycle.approved_points_total }}
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-center font-semibold text-blue-600">
                {{ cycle.approved_conference_count }}
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">
                {{ cycle.reviewer_admin?.name ?? '未アサイン' }}
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100">
                <span
                  class="text-xs px-2 py-1 rounded-full font-medium"
                  :class="{
                    'bg-gray-100 text-gray-500': cycle.reviewer_judgment === 'unreviewed' || !cycle.reviewer_judgment,
                    'bg-green-50 text-green-600 border border-green-200': cycle.reviewer_judgment === 'pass',
                    'bg-red-50 text-red-600 border border-red-200': cycle.reviewer_judgment === 'fail',
                    'bg-orange-50 text-orange-600 border border-orange-200': cycle.reviewer_judgment === 're_review',
                  }"
                >
                  {{ judgmentLabel(cycle.reviewer_judgment) }}
                </span>
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.reviewer_judged_at?.split('T')[0] ?? '-' }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100">
                <div class="flex items-center gap-2">
                  <Link
                    :href="route('admin.chief.show', cycle.id)"
                    class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded hover:bg-gray-50 text-xs font-semibold"
                  >
                    詳細
                  </Link>
                  <button
                    class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="!isJudgedByReviewer(cycle)"
                    :title="!isJudgedByReviewer(cycle) ? '審査員の判定（合格/不合格）が出ると判定できます' : ''"
                    @click="openModal(cycle)"
                  >
                    判定する
                  </button>
                  <button
                    class="px-3 py-1.5 border border-orange-300 text-orange-600 rounded hover:bg-orange-50 text-xs font-semibold"
                    @click="sendBack(cycle)"
                  >
                    差し戻す
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between text-sm text-gray-500">
        <span>{{ startItem }}〜{{ endItem }} 件 / 全{{ cycles.total }}件</span>
        <Pagination :paginator="cycles" :onPageChange="goPage" />
      </div>
    </div>

    <!-- 個別判定モーダル -->
    <DialogModal :show="modal.show" @close="modal.show = false">
      <template #title>{{ modal.memberName }} の最終判定</template>
      <template #content>
        <div class="mb-4">
          <label class="block mb-2 text-sm font-medium">判定</label>
          <select v-model="modal.status" class="w-full border rounded p-2">
            <option value="approved">承認</option>
            <option value="reject">却下</option>
          </select>
        </div>

        <div class="mb-4" v-if="modal.status === 'reject'">
          <label class="block mb-2 text-sm font-medium">理由（必須）</label>
          <textarea
            v-model="modal.reason"
            class="w-full border rounded p-2"
            rows="4"
            placeholder="理由を入力してください"
          ></textarea>
        </div>
      </template>
      <template #footer>
        <SecondaryButton @click="modal.show = false">キャンセル</SecondaryButton>
        <PrimaryButton class="ms-3" @click="submit">送信</PrimaryButton>
      </template>
    </DialogModal>

    <!-- 一括判定モーダル -->
    <DialogModal :show="bulkModal.show" @close="bulkModal.show = false">
      <template #title>
        選択した{{ selectedIds.length }}件を{{ bulkModal.status === 'approved' ? '承認' : '却下' }}
      </template>
      <template #content>
        <!--
        <p class="text-sm text-gray-500 mb-4">
          書類の審査が完了していない申請は自動的にスキップされます。
        </p>
        -->
        <div class="mb-4" v-if="bulkModal.status === 'reject'">
          <label class="block mb-2 text-sm font-medium">却下理由（必須・選択した全件に共通で適用されます）</label>
          <textarea
            v-model="bulkModal.reason"
            class="w-full border rounded p-2"
            rows="4"
            placeholder="理由を入力してください"
          ></textarea>
        </div>
      </template>
      <template #footer>
        <SecondaryButton @click="bulkModal.show = false">キャンセル</SecondaryButton>
        <PrimaryButton class="ms-3" @click="submitBulk">実行</PrimaryButton>
      </template>
    </DialogModal>
  </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { CheckCircle2, XCircle } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import DialogModal from '@/Components/DialogModal.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
  cycles: Object,
  sortBy: { type: String, default: 'reviewer_judged_at' },
  sortDir: { type: String, default: 'desc' },
})

const startItem = computed(() =>
  props.cycles.per_page * (props.cycles.current_page - 1) + 1
)
const endItem = computed(() =>
  Math.min(props.cycles.per_page * props.cycles.current_page, props.cycles.total)
)

const perPage = ref(props.cycles.per_page ?? 20)

const changePerPage = () => {
  router.get(
    route('admin.chief.index'),
    { page: 1, per_page: perPage.value, sort_by: props.sortBy, sort_dir: props.sortDir },
    { preserveState: true, preserveScroll: true }
  )
}

const judgmentLabel = (judgment) => {
  const map = { unreviewed: '未判定', pass: '合格', fail: '不合格', re_review: '再審査' }
  return map[judgment] ?? '未判定'
}

const isJudgedByReviewer = (cycle) => {
  return cycle.reviewer_judgment === 'pass' || cycle.reviewer_judgment === 'fail'
}

// ---- ソート ----
const toggleSort = (column) => {
  const nextDir = (props.sortBy === column && props.sortDir === 'desc') ? 'asc' : 'desc'
  router.get(
    route('admin.chief.index'),
    { sort_by: column, sort_dir: nextDir },
    { preserveState: true, preserveScroll: true }
  )
}

const sortArrow = (column) => {
  if (props.sortBy !== column) return ''
  return props.sortDir === 'asc' ? '▲' : '▼'
}

// ---- 選択 ----
const selectedIds = ref([])

const isAllSelected = computed(() =>
  props.cycles.data.length > 0 && selectedIds.value.length === props.cycles.data.length
)

const toggleSelectAll = (e) => {
  selectedIds.value = e.target.checked ? props.cycles.data.map(c => c.id) : []
}

const toggleSelect = (id) => {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter(i => i !== id)
  } else {
    selectedIds.value.push(id)
  }
}

// ---- 個別判定 ----
const modal = reactive({
  show: false,
  cycleId: null,
  memberName: '',
  status: 'approved',
  reason: '',
})

const openModal = (cycle) => {
  modal.show = true
  modal.cycleId = cycle.id
  modal.memberName = cycle.member?.name ?? ''
  modal.status = 'approved'
  modal.reason = ''
}

const submit = () => {
  if ((modal.status === 'reject' || modal.status === 'no_update') && !modal.reason) {
    alert('理由を入力してください')
    return
  }

  router.post(
    route('admin.chief.review', modal.cycleId),
    { status: modal.status, reason: modal.reason },
    {
      onSuccess: () => {
        modal.show = false
      },
    }
  )
}

// ---- 差し戻し（審査員へ） ----
const sendBack = (cycle) => {
  if (!confirm(`${cycle.member?.name ?? ''}の申請を審査員に差し戻しますか？`)) return

  router.post(
    route('admin.chief.sendBack', cycle.id),
    {},
    { preserveScroll: true }
  )
}

// ---- 一括判定 ----
const bulkModal = reactive({
  show: false,
  status: 'approved',
  reason: '',
})

const openBulkModal = (status) => {
  bulkModal.show = true
  bulkModal.status = status
  bulkModal.reason = ''
}

const submitBulk = () => {
  if (bulkModal.status === 'reject' && !bulkModal.reason) {
    alert('却下理由を入力してください')
    return
  }

  router.post(
    route('admin.chief.bulkReview'),
    {
      ids: selectedIds.value,
      status: bulkModal.status,
      reason: bulkModal.reason,
    },
    {
      onSuccess: () => {
        bulkModal.show = false
        selectedIds.value = []
      },
    }
  )
}

const goPage = (page) => {
  router.get(
    route('admin.chief.index'),
    { page, per_page: perPage.value, sort_by: props.sortBy, sort_dir: props.sortDir },
    { preserveState: true }
  )
}
</script>