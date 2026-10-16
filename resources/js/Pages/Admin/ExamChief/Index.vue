<template>
  <AppLayout>
    <template #header>新規指導士申請 委員長承認</template>

    <div class="p-6 space-y-4">
      <div v-if="selectedIds.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center gap-3">
        <span class="text-sm text-blue-800">{{ selectedIds.length }}件選択中</span>
        <Button type="button" size="sm" class="bg-emerald-600 hover:bg-emerald-700" @click="bulkReview">
          まとめて承認する
        </Button>
        <span class="text-xs text-gray-500">（審査員判定に応じて、受験資格あり／なしが自動確定します）</span>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2.5 w-8">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" />
              </th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">氏名・会員番号</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">ルート</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">担当審査員</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600">審査員判定</th>
              <th class="px-4 py-2.5 text-left font-medium text-gray-600 cursor-pointer" @click="sortBy('reviewer_judged_at')">
                判定日 <SortIcon :active="props.sortBy === 'reviewer_judged_at'" :dir="props.sortDir" />
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
              <td class="px-4 py-3 text-gray-600">{{ app.reviewer_admin?.name ?? '-' }}</td>
              <td class="px-4 py-3">
                <span class="text-sm font-semibold" :class="app.reviewer_judgment === 'pass' ? 'text-emerald-700' : 'text-red-700'">
                  {{ app.reviewer_judgment === 'pass' ? '合格相当' : '不合格相当' }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ app.reviewer_judged_at ? dayjs(app.reviewer_judged_at).format('YYYY-MM-DD') : '-' }}
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('admin.examChief.show', app.id)" class="text-blue-600 hover:underline text-sm font-medium">
                  確認する
                </Link>
              </td>
            </tr>
            <tr v-if="exams.data.length === 0">
              <td colspan="7" class="px-4 py-10 text-center text-gray-400">委員長判定待ちの申請はありません</td>
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
import Pagination from '@/Components/Pagination.vue'
import SortIcon from '@/Components/SortIcon.vue'

const props = defineProps({
  exams: Object,
  sortBy: String,
  sortDir: String,
})

const selectedIds = ref([])

const allSelected = computed(() =>
  props.exams.data.length > 0 && selectedIds.value.length === props.exams.data.length
)

const toggleSelectAll = () => {
  selectedIds.value = allSelected.value ? [] : props.exams.data.map(a => a.id)
}

const sortBy = (column) => {
  const nextDir = props.sortBy === column && props.sortDir === 'asc' ? 'desc' : 'asc'
  router.get(route('admin.examChief.index'), { sort_by: column, sort_dir: nextDir }, {
    preserveState: true, preserveScroll: true,
  })
}

const bulkReview = () => {
  if (!confirm(`${selectedIds.value.length}件をまとめて承認します。よろしいですか？`)) return
  router.post(route('admin.examChief.bulkReview'), { ids: selectedIds.value }, {
    preserveScroll: true,
    onSuccess: () => { selectedIds.value = [] },
  })
}
</script>
