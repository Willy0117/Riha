<template>
  <AppLayout>
    <template #header>
      <div>
        <h2 class="text-2xl font-bold page-title-navy">審査員ポータル</h2>
        <p class="text-xs text-gray-500 mt-1">担当申請の書類を確認し、承認/差し戻しを行います。</p>
      </div>
    </template>

    <div class="p-6">
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
              <th
                class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell table-header-navy-sortable"
                @click="toggleSort('reviewer_assigned_at')"
              >
                アサイン日 <span class="text-white/60">{{ sortArrow('reviewer_assigned_at') }}</span>
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">書類審査状況</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">承認済み回数</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">承認済み単位数</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">判定</th>
              <th class="px-5 py-3 text-left text-xs font-semibold table-header-navy-cell">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cycles.data.length === 0">
              <td colspan="10" class="!p-12 text-center text-gray-400">
                担当している申請はありません
              </td>
            </tr>
            <tr v-for="cycle in cycles.data" :key="cycle.id">
              <td class="px-5 py-3.5 border-b border-gray-100">{{ cycle.member?.code }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 font-medium">{{ cycle.member?.name }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.start_date?.split('T')[0] }} 〜 {{ cycle.end_date?.split('T')[0] }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.updated_at?.split('T')[0] }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-500">{{ cycle.reviewer_assigned_at?.split('T')[0] ?? '-' }}</td>
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
                  :href="route('admin.reviewer.show', cycle.id)"
                  class="px-3 py-1.5 rounded text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100"
                >
                  審査する
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
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  cycles: Object,
  sortBy: { type: String, default: 'updated_at' },
  sortDir: { type: String, default: 'asc' },
})

const perPage = ref(props.cycles.per_page ?? 20)
const sortBy = ref(props.sortBy)
const sortDir = ref(props.sortDir)

const goPage = (page) => {
  router.get(
    route('admin.reviewer.index'),
    { page, per_page: perPage.value, sort_by: sortBy.value, sort_dir: sortDir.value },
    { preserveState: true }
  )
}

const changePerPage = () => {
  router.get(
    route('admin.reviewer.index'),
    { page: 1, per_page: perPage.value, sort_by: sortBy.value, sort_dir: sortDir.value },
    { preserveState: true }
  )
}

const toggleSort = (column) => {
  const nextDir = (sortBy.value === column && sortDir.value === 'asc') ? 'desc' : 'asc'
  sortBy.value = column
  sortDir.value = nextDir
  router.get(
    route('admin.reviewer.index'),
    { page: 1, per_page: perPage.value, sort_by: sortBy.value, sort_dir: sortDir.value },
    { preserveState: true }
  )
}

const sortArrow = (column) => {
  if (sortBy.value !== column) return ''
  return sortDir.value === 'asc' ? '▲' : '▼'
}

const judgmentLabel = (judgment) => {
  const map = { unreviewed: '未判定', pass: '合格', fail: '不合格', re_review: '差し戻し' }
  return map[judgment] ?? '未判定'
}

// [今回追加] 審査員判定バッジ用（共通クラス名を返す）
const judgmentClass = (judgment) => {
  const map = {
    pass: 'status-badge-green',
    fail: 'status-badge-red',
    re_review: 'status-badge-red', // 差し戻しは目立たせるため赤系（font-boldと併用）
  }
  return map[judgment] ?? 'status-badge-gray'
}
</script>
