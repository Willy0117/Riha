<template>
  <AppLayout>
    <template #header>新規指導士申請 審査員アサイン</template>

    <div class="p-6 space-y-4">
      <!-- フィルタタブ -->
      <div class="flex items-center gap-2">
        <button v-for="f in filterTabs" :key="f.value" type="button"
          class="px-3 py-1.5 rounded-full text-sm font-medium border"
          :class="filter === f.value ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
          @click="setFilter(f.value)">
          {{ f.label }}
          <span v-if="f.value === 'unassigned'" class="ml-1">({{ unassignedCount }})</span>
        </button>

        <Button type="button" variant="outline" class="ml-auto" @click="autoAssign">
          <Shuffle class="w-4 h-4 mr-1" /> 未アサイン分を自動割振
        </Button>
      </div>

      <!-- 審査員ごとの担当件数バッジ -->
      <div class="flex flex-wrap gap-2">
        <button v-for="r in reviewers" :key="r.id" type="button"
          class="px-3 py-1.5 rounded-full text-xs font-medium border flex items-center gap-1.5"
          :class="reviewerId === r.id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
          @click="filterByReviewer(r.id)">
          {{ r.name }}
          <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="reviewerId === r.id ? 'bg-white/20' : 'bg-gray-100'">
            {{ r.active_count }}件
          </span>
        </button>
        <button v-if="reviewerId" type="button" class="text-xs text-gray-400 hover:text-gray-600 underline" @click="filterByReviewer(null)">
          絞り込み解除
        </button>
      </div>

      <!-- 一括アサイン操作バー -->
      <div v-if="selectedIds.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center gap-3">
        <span class="text-sm text-blue-800">{{ selectedIds.length }}件選択中</span>
        <select v-model="bulkReviewerId" class="input-field w-56">
          <option value="">審査員を選択</option>
          <option v-for="r in reviewers" :key="r.id" :value="r.id">{{ r.name }}（{{ r.active_count }}件）</option>
        </select>
        <Button type="button" size="sm" :disabled="!bulkReviewerId" @click="bulkAssign">まとめてアサイン</Button>
      </div>

      <!-- 一覧 -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2.5 w-8">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
              </th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">氏名・会員番号</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">ルート</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">提出書類</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">担当審査員</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600 cursor-pointer" @click="sortBy('applied_at')">
                申請日 <SortIcon :active="props.sortBy === 'applied_at'" :dir="props.sortDir" />
              </th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="app in exams.data" :key="app.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <input type="checkbox" :value="app.id" v-model="selectedIds" />
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900">{{ app.last_name }} {{ app.first_name }}</div>
                <div class="text-xs text-gray-500">{{ app.member?.code ?? '-' }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
                  :class="app.route === 'A' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'">
                  {{ app.route }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">
                書類{{ app.documents_count }}件 / 症例{{ app.case_reports_count }}件
              </td>
              <td class="px-4 py-3">
                <select :value="app.reviewer_admin_id" class="input-field text-xs py-1"
                  @change="assignOne(app.id, $event.target.value)">
                  <option value="">未アサイン</option>
                  <option v-for="r in reviewers" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ app.applied_at ? dayjs(app.applied_at).format('YYYY-MM-DD') : '-' }}
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('admin.examOffice.show', app.id)" class="text-blue-600 hover:underline text-xs">
                  詳細
                </Link>
              </td>
            </tr>
            <tr v-if="exams.data.length === 0">
              <td colspan="7" class="px-4 py-10 text-center text-gray-400">該当する申請がありません</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :links="exams.links" />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Shuffle } from 'lucide-vue-next'
import Pagination from '@/Components/Pagination.vue'
import SortIcon from '@/Components/SortIcon.vue'

const props = defineProps({
  exams: Object,
  reviewers: Array,
  filter: String,
  reviewerId: Number,
  unassignedCount: Number,
  sortBy: String,
  sortDir: String,
})

const filterTabs = [
  { value: 'unassigned', label: '未アサイン' },
  { value: 'assigned', label: 'アサイン済み' },
  { value: 'all', label: 'すべて' },
]

const selectedIds = ref([])
const bulkReviewerId = ref('')

const allSelected = computed(() =>
  props.exams.data.length > 0 && selectedIds.value.length === props.exams.data.length
)

const toggleSelectAll = () => {
  selectedIds.value = allSelected.value ? [] : props.exams.data.map(a => a.id)
}

const reload = (extra = {}) => {
  router.get(route('admin.examAssignment.index'), {
    filter: props.filter,
    reviewer_id: props.reviewerId,
    sort_by: props.sortBy,
    sort_dir: props.sortDir,
    ...extra,
  }, { preserveState: true, preserveScroll: true })
}

const setFilter = (value) => reload({ filter: value, reviewer_id: null })
const filterByReviewer = (id) => reload({ reviewer_id: id })
const sortBy = (column) => {
  const nextDir = props.sortBy === column && props.sortDir === 'asc' ? 'desc' : 'asc'
  reload({ sort_by: column, sort_dir: nextDir })
}

const assignOne = (applicationId, reviewerAdminId) => {
  if (!reviewerAdminId) return
  router.post(route('admin.examAssignment.assign', applicationId), {
    reviewer_admin_id: reviewerAdminId,
  }, { preserveScroll: true })
}

const bulkAssign = () => {
  if (!bulkReviewerId.value) return
  router.post(route('admin.examAssignment.bulkAssign'), {
    ids: selectedIds.value,
    reviewer_admin_id: bulkReviewerId.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { selectedIds.value = []; bulkReviewerId.value = '' },
  })
}

const autoAssign = () => {
  if (!confirm('未アサインの申請を、審査員に自動で均等割振します。よろしいですか？')) return
  router.post(route('admin.examAssignment.autoAssign'), {}, { preserveScroll: true })
}
</script>

<style scoped>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>
