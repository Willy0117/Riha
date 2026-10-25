<template>
  <AppLayout>
    <template #header>
      <div>
        <h2 class="text-2xl font-bold page-title-navy">審査済一覧</h2>
        <p class="text-xs text-gray-500 mt-1">審査が完了した申請の一覧です。審査結果を閲覧できます。</p>
      </div>
    </template>

    <div class="p-6">
      <!-- 検索 -->
      <form class="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap items-end gap-3" @submit.prevent="search">
        <div>
          <label class="text-xs text-gray-500 mb-1 block">氏名</label>
          <input v-model="filters.name" type="text" placeholder="氏名・かな" class="border rounded px-2 py-1.5 text-sm w-48" />
        </div>
        <div>
          <label class="text-xs text-gray-500 mb-1 block">第N回（認定期間）</label>
          <select v-model="filters.round_id" @change="search" class="border rounded pl-2 pr-7 py-1.5 text-sm">
            <option value="">指定なし</option>
            <option v-for="r in rounds" :key="r.id" :value="r.id">第{{ r.exam_round }}回（{{ r.start_date?.split('T')[0] }} 〜 {{ r.end_date?.split('T')[0] }}）</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-500 mb-1 block">委員長承認日（from）</label>
          <input v-model="filters.date_from" type="date" class="border rounded px-2 py-1.5 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-500 mb-1 block">委員長承認日（to）</label>
          <input v-model="filters.date_to" type="date" class="border rounded px-2 py-1.5 text-sm" />
        </div>
        <button type="submit" class="px-4 py-1.5 bg-[#1D4E89] text-white rounded hover:bg-[#163B68] text-sm font-semibold">検索</button>
        <button type="button" class="px-4 py-1.5 bg-gray-50 text-gray-700 border border-gray-200 rounded hover:bg-gray-100 text-sm" @click="clearSearch">クリア</button>
      </form>

      <!-- ツールバー -->
      <div class="flex items-center justify-between mb-4">
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

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">会員番号</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">氏名</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">認定期間</th>
              <th
                class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell table-header-navy-sortable"
                @click="toggleSort('updated_at')"
              >
                申請日 <span class="text-white/60">{{ sortArrow('updated_at') }}</span>
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">委員長承認日</th>
              <th
                class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell table-header-navy-sortable"
                @click="toggleSort('reviewer_assigned_at')"
              >
                アサイン日 <span class="text-white/60">{{ sortArrow('reviewer_assigned_at') }}</span>
              </th>
              <th
                class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell table-header-navy-sortable"
                @click="toggleSort('reviewer_judged_at')"
              >
                判定日 <span class="text-white/60">{{ sortArrow('reviewer_judged_at') }}</span>
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">書類審査状況</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">腎リハ学会確認済回数</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">確認済単位数</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">判定</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cycles.data.length === 0">
              <td colspan="12" class="!p-12 text-center text-gray-400">
                審査済みの申請はありません
              </td>
            </tr>
            <tr v-for="cycle in cycles.data" :key="cycle.id">
              <td class="px-5 py-3.5 border-b border-gray-100">{{ cycle.member?.code }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 font-medium">{{ cycle.member?.name }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.start_date?.split('T')[0] }} 〜 {{ cycle.end_date?.split('T')[0] }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.status === 'pending' ? cycle.updated_at?.split('T')[0] : '-' }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ ['approved', 'reject'].includes(cycle.status) ? cycle.updated_at?.split('T')[0] : '-' }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.reviewer_assigned_at?.split('T')[0] ?? '-' }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.reviewer_judged_at?.split('T')[0] ?? '-' }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.document_review_status }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.approved_conference_count }} 回</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.approved_points_total }} 単位</td>
              <td class="px-5 py-3.5 border-b border-gray-100">
                <span
                  class="status-badge-common"
                  :class="[judgmentClass(cycle.reviewer_judgment), { 'font-bold': cycle.reviewer_judgment === 're_review' }]"
                >
                  {{ judgmentLabel(cycle.reviewer_judgment) }}
                </span>
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100">
                <Link
                  :href="route('admin.reviewer.reviewed.show', cycle.id)"
                  class="inline-flex p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-blue-600"
                  title="閲覧"
                >
                  <FileSearch class="w-5 h-5" />
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-end mt-4">
        <Pagination :paginator="cycles" :onPageChange="goPage" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { FileSearch } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  cycles: Object,
  sortBy: { type: String, default: 'reviewer_judged_at' },
  sortDir: { type: String, default: 'asc' },
  filters: { type: Object, default: () => ({}) },
  rounds: { type: Array, default: () => [] },
})

const perPage = ref(props.cycles.per_page ?? 20)
const sortBy = ref(props.sortBy)
const sortDir = ref(props.sortDir)

const filters = reactive({
  name: props.filters?.name ?? '',
  round_id: props.filters?.round_id ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
})

// 現在の検索条件・並び順・件数をまとめたクエリ（ページ移動・並び替え時も検索条件を維持する）
const buildQuery = (extra = {}) => ({
  page: 1,
  per_page: perPage.value,
  sort_by: sortBy.value,
  sort_dir: sortDir.value,
  name: filters.name || undefined,
  round_id: filters.round_id || undefined,
  date_from: filters.date_from || undefined,
  date_to: filters.date_to || undefined,
  ...extra,
})

const search = () => {
  router.get(route('admin.reviewer.reviewed'), buildQuery(), { preserveState: true })
}

const clearSearch = () => {
  filters.name = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.round_id = ''
  search()
}

const goPage = (page) => {
  router.get(route('admin.reviewer.reviewed'), buildQuery({ page }), { preserveState: true })
}

const changePerPage = () => {
  router.get(route('admin.reviewer.reviewed'), buildQuery(), { preserveState: true })
}

const toggleSort = (column) => {
  const nextDir = (sortBy.value === column && sortDir.value === 'asc') ? 'desc' : 'asc'
  sortBy.value = column
  sortDir.value = nextDir
  router.get(route('admin.reviewer.reviewed'), buildQuery(), { preserveState: true })
}

const sortArrow = (column) => {
  if (sortBy.value !== column) return ''
  return sortDir.value === 'asc' ? '▲' : '▼'
}

const judgmentLabel = (judgment) => {
  const map = { unreviewed: '未判定', pass: '合格', fail: '不合格', re_review: '差し戻し' }
  return map[judgment] ?? '未判定'
}

const judgmentClass = (judgment) => {
  const map = {
    pass: 'status-badge-green',
    fail: 'status-badge-red',
    re_review: 'status-badge-red',
  }
  return map[judgment] ?? 'status-badge-gray'
}
</script>
