<template>
  <AppLayout>
    <template #header>
      <h2 class="text-2xl font-bold page-title-navy">指導士認定期間設定</h2>
    </template>

    <div class="p-6 space-y-4">

      <!-- flashメッセージ -->
      <div
        v-if="page.props.flash?.success"
        class="px-4 py-3 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm"
      >
        {{ page.props.flash.success }}
      </div>

      <!-- [今回追加] 編集権限が無い場合の警告バナー -->
      <div
        v-if="!can_edit"
        class="bg-amber-50 border border-amber-300 text-amber-800 text-sm rounded-lg px-4 py-3"
      >
        閲覧はできますが、編集・登録はできません。
      </div>

      <!-- [今回追加] 保存時に権限エラーが返ってきた場合の表示 -->
      <div
        v-if="errors.permission"
        class="bg-red-50 border border-red-300 text-red-700 text-sm rounded-lg px-4 py-3"
      >
        {{ errors.permission }}
      </div>

      <!-- 新規追加 -->
      <div class="bg-white border border-gray-200 rounded-xl p-4">
        <h2 class="text-sm font-bold text-gray-700 mb-3">新規追加</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="text-xs text-gray-500 mb-1 block">第N回（exam_round）</label>
            <input v-model.number="createForm.exam_round" type="number" min="1" class="input-field" />
          </div>
          <div>
            <label class="text-xs text-gray-500 mb-1 block">認定開始日</label>
            <input v-model="createForm.start_date" type="date" class="input-field" />
          </div>
          <div>
            <label class="text-xs text-gray-500 mb-1 block">認定終了日</label>
            <input v-model="createForm.end_date" type="date" class="input-field" />
          </div>
          <div class="flex items-end">
            <Button class="w-full" :disabled="!can_edit" @click="submitCreate">追加する</Button>
          </div>
        </div>
      </div>

      <!-- 一覧 -->
      <div class="border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr>
              <th class="px-3 py-2.5 text-left font-medium table-header-navy-cell">第N回</th>
              <th class="px-3 py-2.5 text-left font-medium table-header-navy-cell">認定開始日</th>
              <th class="px-3 py-2.5 text-left font-medium table-header-navy-cell">認定終了日</th>
              <th class="px-3 py-2.5 text-center font-medium table-header-navy-cell">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-if="cycles.length === 0">
              <td colspan="4" class="px-3 py-12 text-center text-muted-foreground">
                データがありません
              </td>
            </tr>
            <tr v-for="cycle in cycles" :key="cycle.id" class="hover:bg-muted/30 transition-colors">
              <td class="px-3 py-2.5">
                <template v-if="editingId === cycle.id">
                  <input v-model.number="editForm.exam_round" type="number" min="1" class="input-field w-24" />
                </template>
                <template v-else>第{{ cycle.exam_round }}回</template>
              </td>
              <td class="px-3 py-2.5">
                <template v-if="editingId === cycle.id">
                  <input v-model="editForm.start_date" type="date" class="input-field" />
                </template>
                <template v-else>{{ formatDate(cycle.start_date) }}</template>
              </td>
              <td class="px-3 py-2.5">
                <template v-if="editingId === cycle.id">
                  <input v-model="editForm.end_date" type="date" class="input-field" />
                </template>
                <template v-else>{{ formatDate(cycle.end_date) }}</template>
              </td>
              <td class="px-3 py-2.5 text-center">
                <template v-if="editingId === cycle.id">
                  <div class="flex items-center justify-center gap-2">
                    <button
                      class="px-3 py-1 rounded text-xs bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100"
                      :disabled="!can_edit"
                      @click="submitEdit(cycle.id)"
                    >
                      保存
                    </button>
                    <button
                      class="px-3 py-1 rounded text-xs bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100"
                      @click="cancelEdit"
                    >
                      キャンセル
                    </button>
                  </div>
                </template>
                <template v-else>
                  <button
                    class="p-1.5 rounded hover:bg-gray-100 text-gray-500 hover:text-blue-600"
                    title="編集"
                    @click="startEdit(cycle)"
                  >
                    <Pencil class="w-4 h-4" />
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import { Pencil } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import { Button } from '@/components/ui/button'

const page = usePage()

const props = defineProps({
  cycles: Array,
  can_edit: Boolean,
})

const errors = computed(() => page.props.errors ?? {})

// [今回追加] YYYY-MM-DD形式でフォーマットする（時刻部分は表示しない）
const formatDate = (value) => {
  if (!value) return '-'
  return dayjs(value).format('YYYY-MM-DD')
}

// ---- 新規追加 ----
const createForm = reactive({
  exam_round: null,
  start_date: '',
  end_date: '',
})

const submitCreate = () => {
  if (!createForm.exam_round || !createForm.start_date || !createForm.end_date) {
    alert('全ての項目を入力してください')
    return
  }
  router.post(route('admin.instructorCycles.store'), createForm, {
    preserveScroll: true,
    onSuccess: () => {
      createForm.exam_round = null
      createForm.start_date = ''
      createForm.end_date = ''
    },
  })
}

// ---- 編集 ----
const editingId = ref(null)
const editForm = reactive({
  exam_round: null,
  start_date: '',
  end_date: '',
})

const startEdit = (cycle) => {
  editingId.value = cycle.id
  editForm.exam_round = cycle.exam_round
  editForm.start_date = formatDate(cycle.start_date) === '-' ? '' : formatDate(cycle.start_date)
  editForm.end_date = formatDate(cycle.end_date) === '-' ? '' : formatDate(cycle.end_date)
}

const cancelEdit = () => {
  editingId.value = null
}

const submitEdit = (id) => {
  router.put(route('admin.instructorCycles.update', id), editForm, {
    preserveScroll: true,
    onSuccess: () => {
      editingId.value = null
    },
  })
}
</script>

<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>
