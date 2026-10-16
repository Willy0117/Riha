<template>
  <AppLayout>
    <template #header>新規指導士申請 一覧</template>

    <div class="p-6 space-y-4">
      <!-- 検索・フィルタ -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
          <Label class="text-xs text-muted-foreground">氏名・会員番号・メールで検索</Label>
          <div class="relative mt-1">
            <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="form.search" placeholder="例: 山田太郎" class="pl-9" @keyup.enter="applyFilters" />
          </div>
        </div>

        <div class="w-48">
          <Label class="text-xs text-muted-foreground">ステータス</Label>
          <select v-model="form.status" class="input-field mt-1" @change="applyFilters">
            <option value="">すべて</option>
            <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
          </select>
        </div>

        <div class="w-40">
          <Label class="text-xs text-muted-foreground">ルート</Label>
          <select v-model="form.route_filter" class="input-field mt-1" @change="applyFilters">
            <option value="">すべて</option>
            <option v-for="(label, value) in routeLabels" :key="value" :value="value">{{ value }}</option>
          </select>
        </div>

        <Button type="button" @click="applyFilters">
          <Search class="w-4 h-4 mr-1" /> 検索
        </Button>
      </div>

      <!-- 一覧 -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600 cursor-pointer" @click="sortBy('created_at')">
                会員番号・氏名
              </th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">第N回</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">ルート</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">提出書類</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">担当審査員</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600 cursor-pointer" @click="sortBy('status')">
                ステータス <SortIcon :active="filters.sort_by === 'status'" :dir="filters.sort_dir" />
              </th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600 cursor-pointer" @click="sortBy('applied_at')">
                申請日 <SortIcon :active="filters.sort_by === 'applied_at'" :dir="filters.sort_dir" />
              </th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="app in exams.data" :key="app.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900">{{ app.last_name }} {{ app.first_name }}</div>
                <div class="text-xs text-gray-500">{{ app.member?.code ?? app.member_code_snapshot ?? '-' }}</div>
              </td>
              <td class="px-4 py-3 text-gray-600">第{{ app.exam_round }}回</td>
              <td class="px-4 py-3">
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
                  :class="app.route === 'A' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'">
                  {{ app.route }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">
                書類{{ app.documents_count }}件 / 症例{{ app.case_reports_count }}件
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ app.reviewer_admin?.name ?? '未アサイン' }}
              </td>
              <td class="px-4 py-3">
                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusBadgeClass(app.status)">
                  {{ statusLabels[app.status] }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ app.applied_at ? dayjs(app.applied_at).format('YYYY-MM-DD') : '-' }}
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('admin.examOffice.show', { exam: app.id, ...persistQuery() })"
                  class="text-blue-600 hover:underline text-sm font-medium">
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
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Search } from 'lucide-vue-next'
import Pagination from '@/Components/Pagination.vue'
import SortIcon from '@/Components/SortIcon.vue'

const props = defineProps({
  exams: Object,
  statusLabels: Object,
  routeLabels: Object,
  filters: Object,
})

const form = reactive({
  search: props.filters.search ?? '',
  status: props.filters.status ?? '',
  route_filter: props.filters.route_filter ?? '',
})

const persistQuery = () => ({
  search: form.search || undefined,
  status: form.status || undefined,
  route_filter: form.route_filter || undefined,
  sort_by: props.filters.sort_by,
  sort_dir: props.filters.sort_dir,
  per_page: props.filters.per_page,
})

const applyFilters = () => {
  router.get(route('admin.examOffice.index'), { ...persistQuery(), page: 1 }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const sortBy = (column) => {
  const sameColumn = props.filters.sort_by === column
  const nextDir = sameColumn && props.filters.sort_dir === 'asc' ? 'desc' : 'asc'

  router.get(route('admin.examOffice.index'), {
    ...persistQuery(),
    sort_by: column,
    sort_dir: nextDir,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const statusBadgeClass = (status) => {
  switch (status) {
    case 'draft':     return 'bg-gray-100 text-gray-600'
    case 'submitted': return 'bg-amber-50 text-amber-700'
    case 'pending':   return 'bg-blue-50 text-blue-700'
    case 'approved':  return 'bg-emerald-50 text-emerald-700'
    case 'reject':    return 'bg-red-50 text-red-700'
    case 'certified': return 'bg-teal-100 text-teal-800'
    default:          return 'bg-gray-100 text-gray-600'
  }
}
</script>

<style scoped>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>
