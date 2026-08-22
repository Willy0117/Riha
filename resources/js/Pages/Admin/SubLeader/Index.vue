<template>
  <AppLayout>
    <template #header>
      <div>
        <h2 class="text-2xl font-bold text-[#1D4E89]">アサイン担当者ポータル</h2>
        <p class="text-xs text-gray-500 mt-1">更新申請ごとに担当審査員を割り当てます。</p>
      </div>
    </template>

    <div class="p-6 space-y-6">

      <!-- ツールバー -->
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <select v-model="perPage" @change="changePerPage" class="border rounded pl-2 pr-7 py-1.5 text-sm h-9">
            <option :value="10">10件</option>
            <option :value="20">20件</option>
            <option :value="50">50件</option>
            <option :value="100">100件</option>
          </select>

          <select v-model="filter" @change="changeFilter" class="border rounded pl-2 pr-7 py-1.5 text-sm h-9">
            <option value="unassigned">未アサインのみ</option>
            <option value="assigned">アサイン済みのみ</option>
            <option value="all">すべて</option>
          </select>

          <select v-model="reviewerId" @change="changeReviewerSelect" class="border rounded pl-2 pr-7 py-1.5 text-sm h-9">
            <option :value="null">すべての先生</option>
            <option v-for="r in reviewers" :key="r.id" :value="r.id">
              {{ r.name }}
            </option>
          </select>

          <!-- 一括操作ボタン群（チェック時のみ表示） -->
          <template v-if="selectedIds.length > 0">
            <select v-model="bulkReviewerId" class="border rounded pl-2 pr-7 py-1.5 text-sm h-9">
              <option value="" disabled>審査員を選択</option>
              <option v-for="r in reviewers" :key="r.id" :value="r.id">
                {{ r.name }}（{{ r.active_count }}名）
              </option>
            </select>
            <Button size="sm" :disabled="!bulkReviewerId" @click="bulkAssign">
              {{ selectedIds.length }}件 アサイン
            </Button>
          </template>
        </div>

        <div class="flex items-center gap-2">
          <Button
            size="sm"
            class="bg-[#1D4E89] hover:bg-[#163B68] text-white"
            :disabled="reviewers.length === 0"
            @click="confirmAutoAssign"
          >
            <Shuffle class="w-3.5 h-3.5 mr-1" />
            未アサインを自動割振
          </Button>
        </div>
      </div>

      <!-- 検索条件バッジ -->
      <div v-if="hasActiveFilters" class="flex items-center gap-2 flex-wrap">
        <span class="text-xs text-gray-400">検索条件:</span>
        <span v-if="filter !== 'unassigned'" class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 rounded-full px-2.5 py-1">
          表示対象: {{ filterLabels[filter] }}
          <button @click="filter = 'unassigned'; applyFilters()"><X class="w-3 h-3" /></button>
        </span>
        <span v-if="reviewerId" class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 rounded-full px-2.5 py-1">
          先生: {{ reviewers.find(r => r.id === reviewerId)?.name }}
          <button @click="clearReviewerFilter"><X class="w-3 h-3" /></button>
        </span>
      </div>

      <!-- 審査員ごとの現在の担当者数 + 未アサイン件数 -->
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center gap-2 mb-3">
          <h3 class="text-sm font-bold text-gray-700">審査員の現在の担当者数</h3>
          <span class="text-xs text-amber-600 font-bold">（未アサイン {{ unassignedCount }}名）</span>
        </div>
        <div class="flex flex-wrap gap-3">
          <button
            v-for="r in reviewers"
            :key="r.id"
            type="button"
            class="border rounded-lg px-4 py-2 text-sm text-left transition"
            :class="reviewerId === r.id
              ? 'border-[#1D4E89] bg-[#EAF1FA]'
              : 'border-gray-200 hover:border-[#1D4E89]/50 hover:bg-[#EAF1FA]/60'"
            @click="filterByReviewer(r.id)"
          >
            <span class="font-medium">{{ r.name }}</span>
            <span class="ml-2 text-[#1D4E89] font-bold">{{ r.active_count }}名</span>
          </button>
        </div>
      </div>

      <!-- 申請一覧 + アサイン -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr>
              <th class="px-3 py-3 w-10 bg-[#1D4E89] border-b border-[#163B68]">
                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" />
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68]">会員番号</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68]">氏名</th>
              <th
                class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68] cursor-pointer select-none hover:text-white"
                @click="toggleSort('updated_at')"
              >
                申請日 <span class="text-white/60">{{ sortArrow('updated_at') }}</span>
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68]">担当審査員</th>
              <th
                class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68] cursor-pointer select-none hover:text-white"
                @click="toggleSort('reviewer_assigned_at')"
              >
                アサイン日時 <span class="text-white/60">{{ sortArrow('reviewer_assigned_at') }}</span>
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68]">審査状況</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68]">承認済回数</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68]">承認済単位数</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-white/90 bg-[#1D4E89] border-b border-[#163B68]">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cycles.data.length === 0">
              <td colspan="10" class="!p-12 text-center text-gray-400">
                該当する申請はありません
              </td>
            </tr>
            <tr v-for="cycle in cycles.data" :key="cycle.id" :class="{ 'bg-[#EAF1FA]': selectedIds.includes(cycle.id) }">
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
                  v-if="cycle.reviewer_admin"
                  class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200"
                >
                  {{ cycle.reviewer_admin.name }}
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200"
                >
                  未アサイン
                </span>
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">
                {{ cycle.reviewer_assigned_at?.split('T')[0] ?? '-' }}
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.document_review_status }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.approved_conference_count }} 回</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.approved_points_total }} 単位</td>
              <td class="px-5 py-3.5 border-b border-gray-100">
                <div class="flex items-center gap-2">
                  <select
                    v-model="selectedReviewer[cycle.id]"
                    class="border rounded pl-2 pr-7 py-1.5 text-xs"
                  >
                    <option value="" disabled>審査員を選択</option>
                    <option v-for="r in reviewers" :key="r.id" :value="r.id">
                      {{ r.name }}（{{ r.active_count }}名）
                    </option>
                  </select>
                  <button
                    class="px-3 py-1.5 bg-[#1D4E89] text-white rounded hover:bg-[#163B68] text-xs font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="!selectedReviewer[cycle.id]"
                    @click="assign(cycle)"
                  >
                    {{ cycle.reviewer_admin ? '変更' : 'アサイン' }}
                  </button>
                </div>
                <p v-if="cycle.review_started" class="text-xs text-amber-600 mt-1">※ 審査着手済みです。変更すると担当が引き継がれます</p>
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
  </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Shuffle, X } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Button } from '@/components/ui/button'

const props = defineProps({
  cycles: Object,
  reviewers: Array,
  filter: { type: String, default: 'unassigned' },
  reviewerId: { type: Number, default: null },
  unassignedCount: { type: Number, default: 0 },
  sortBy: { type: String, default: 'updated_at' },
  sortDir: { type: String, default: 'asc' },
})

const filter = ref(props.filter)
const reviewerId = ref(props.reviewerId)
const sortBy = ref(props.sortBy)
const sortDir = ref(props.sortDir)

const startItem = computed(() =>
  props.cycles.per_page * (props.cycles.current_page - 1) + 1
)
const endItem = computed(() =>
  Math.min(props.cycles.per_page * props.cycles.current_page, props.cycles.total)
)

const perPage = ref(props.cycles.per_page ?? 20)

const filterLabels = {
  unassigned: '未アサインのみ',
  assigned: 'アサイン済みのみ',
  all: 'すべて',
}

const hasActiveFilters = computed(() =>
  filter.value !== 'unassigned' || !!reviewerId.value
)

const applyFilters = (extra = {}) => {
  router.get(
    route('admin.subleader.index'),
    {
      page: 1,
      per_page: perPage.value,
      filter: filter.value,
      reviewer_id: reviewerId.value,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
      ...extra,
    },
    { preserveState: true }
  )
}

const toggleSort = (column) => {
  const nextDir = (sortBy.value === column && sortDir.value === 'asc') ? 'desc' : 'asc'
  sortBy.value = column
  sortDir.value = nextDir
  applyFilters({ page: 1 })
}

const sortArrow = (column) => {
  if (sortBy.value !== column) return ''
  return sortDir.value === 'asc' ? '▲' : '▼'
}

const changeFilter = () => applyFilters()

// 検索部の「先生」セレクトを変更したとき
const changeReviewerSelect = () => applyFilters()

// 先生バッジをクリック → その先生の担当分のみに絞り込む（もう一度押すと解除）
const filterByReviewer = (id) => {
  reviewerId.value = reviewerId.value === id ? null : id
  applyFilters()
}

const clearReviewerFilter = () => {
  reviewerId.value = null
  applyFilters()
}

const selectedReviewer = reactive({})

// ---- 一括選択・一括アサイン ----
const selectedIds = ref([])
const bulkReviewerId = ref('')

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

const bulkAssign = () => {
  if (!bulkReviewerId.value) return

  const startedCount = props.cycles.data.filter(
    c => selectedIds.value.includes(c.id) && c.review_started
  ).length

  const warning = startedCount > 0
    ? `\n※ うち${startedCount}件は審査着手済みです。担当を変更すると引き継がれます。`
    : ''

  if (!confirm(`選択した${selectedIds.value.length}件を、指定した審査員にアサインしますか？${warning}`)) return

  router.post(
    route('admin.subleader.bulkAssign'),
    { ids: selectedIds.value, reviewer_admin_id: bulkReviewerId.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        selectedIds.value = []
        bulkReviewerId.value = ''
      },
    }
  )
}

const assign = (cycle) => {
  const reviewerId = selectedReviewer[cycle.id]
  if (!reviewerId) return

  if (cycle.review_started) {
    if (!confirm('この申請は審査着手済みです。担当審査員を変更しますか？変更すると担当が引き継がれます。')) return
  }

  router.post(
    route('admin.subleader.assign', cycle.id),
    { reviewer_admin_id: reviewerId },
    { preserveScroll: true }
  )
}

const confirmAutoAssign = () => {
  if (!confirm('未アサインの申請を自動で割り当てます。よろしいですか？')) return

  router.post(
    route('admin.subleader.autoAssign'),
    {},
    { preserveScroll: true }
  )
}

const goPage = (page) => applyFilters({ page })

const changePerPage = () => applyFilters()
</script>
