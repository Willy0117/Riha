<template>
  <AppLayout>
    <template #header>担当申請一覧</template>

    <div class="p-6 space-y-4">
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">氏名・会員番号</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">ルート</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">提出書類</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600 cursor-pointer" @click="sortBy('reviewer_assigned_at')">
                アサイン日 <SortIcon :active="props.sortBy === 'reviewer_assigned_at'" :dir="props.sortDir" />
              </th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">判定</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="app in exams.data" :key="app.id" class="hover:bg-gray-50">
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
              <td class="px-4 py-3 text-gray-600">
                {{ app.reviewer_assigned_at ? dayjs(app.reviewer_assigned_at).format('YYYY-MM-DD') : '-' }}
              </td>
              <td class="px-4 py-3">
                <span v-if="app.reviewer_judgment === 're_review'" class="text-amber-600 text-xs font-semibold">委員長から差し戻し</span>
                <span v-else class="text-gray-400 text-xs">未審査</span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('admin.examReviewer.show', app.id)" class="text-blue-600 hover:underline text-sm font-medium">
                  審査する
                </Link>
              </td>
            </tr>
            <tr v-if="exams.data.length === 0">
              <td colspan="6" class="px-4 py-10 text-center text-gray-400">担当している申請はありません</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :links="exams.links" />
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SortIcon from '@/Components/SortIcon.vue'

const props = defineProps({
  exams: Object,
  sortBy: String,
  sortDir: String,
})

const sortBy = (column) => {
  const nextDir = props.sortBy === column && props.sortDir === 'asc' ? 'desc' : 'asc'
  router.get(route('admin.examReviewer.index'), { sort_by: column, sort_dir: nextDir }, {
    preserveState: true, preserveScroll: true,
  })
}
</script>
