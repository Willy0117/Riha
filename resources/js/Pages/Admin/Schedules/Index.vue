<template>
  <AppLayout>
    <template #header>
      <div>
        <h2 class="text-2xl font-bold text-gray-800">期間スケジュール管理</h2>
        <p class="text-xs text-gray-500 mt-1">申請受付期間・サブリーダー/審査員/審査委員長の作業期間を管理します。</p>
      </div>
    </template>

    <div class="p-6 space-y-4">
      <!-- flashメッセージ -->
      <div
        v-if="page.props.flash?.success"
        class="px-4 py-3 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm"
      >
        {{ page.props.flash.success }}
      </div>

      <div class="flex justify-end">
        <Button size="sm" class="bg-blue-600 hover:bg-blue-700 text-white" @click="openCreateModal">
          <Plus class="w-3.5 h-3.5 mr-1" />
          新規期区分を追加
        </Button>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">区分</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">申請受付期間</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">サブリーダー作業期間</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">審査員作業期間</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">審査員長作業期間</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">通知状況</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="schedules.length === 0">
              <td colspan="7" class="!p-12 text-center text-gray-400">
                スケジュールが登録されていません
              </td>
            </tr>
            <tr v-for="s in schedules" :key="s.id" class="border-b border-gray-100">
              <td class="px-4 py-3 font-medium">{{ s.period_name }}</td>
              <td class="px-4 py-3 text-gray-600">{{ formatRange(s.application_start, s.application_end) }}</td>
              <td class="px-4 py-3 text-gray-600">{{ formatRange(s.subleader_start, s.subleader_end) }}</td>
              <td class="px-4 py-3 text-gray-600">{{ formatRange(s.reviewer_start, s.reviewer_end) }}</td>
              <td class="px-4 py-3 text-gray-600">{{ formatRange(s.chief_start, s.chief_end) }}</td>
              <td class="px-4 py-3">
                <div class="flex gap-1">
                  <span :class="notifiedBadgeClass(s.subleader_notified)">サブ</span>
                  <span :class="notifiedBadgeClass(s.reviewer_notified)">審査員</span>
                  <span :class="notifiedBadgeClass(s.chief_notified)">委員長</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <button class="text-gray-500 hover:text-blue-600" @click="openEditModal(s)">
                    <Pencil class="w-4 h-4" />
                  </button>
                  <button class="text-gray-500 hover:text-red-600" @click="destroy(s)">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 登録・編集モーダル -->
    <Dialog :open="showModal" @update:open="showModal = false">
      <DialogContent class="sm:max-w-2xl bg-white">
        <DialogHeader>
          <DialogTitle>{{ editingId ? 'スケジュール編集' : '新規期区分の登録' }}</DialogTitle>
        </DialogHeader>

        <div class="space-y-4 py-2">
          <div>
            <label class="text-xs font-medium text-gray-600">区分名</label>
            <input v-model="form.period_name" type="text" placeholder="例: 第1期" class="w-full border rounded p-2 text-sm mt-1" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-medium text-gray-600">申請受付開始</label>
              <input v-model="form.application_start" type="date" class="w-full border rounded p-2 text-sm mt-1" />
            </div>
            <div>
              <label class="text-xs font-medium text-gray-600">申請受付終了</label>
              <input v-model="form.application_end" type="date" class="w-full border rounded p-2 text-sm mt-1" />
            </div>

            <div>
              <label class="text-xs font-medium text-gray-600">サブリーダー作業開始</label>
              <input v-model="form.subleader_start" type="date" class="w-full border rounded p-2 text-sm mt-1" />
            </div>
            <div>
              <label class="text-xs font-medium text-gray-600">サブリーダー作業終了</label>
              <input v-model="form.subleader_end" type="date" class="w-full border rounded p-2 text-sm mt-1" />
            </div>

            <div>
              <label class="text-xs font-medium text-gray-600">審査員作業開始</label>
              <input v-model="form.reviewer_start" type="date" class="w-full border rounded p-2 text-sm mt-1" />
            </div>
            <div>
              <label class="text-xs font-medium text-gray-600">審査員作業終了</label>
              <input v-model="form.reviewer_end" type="date" class="w-full border rounded p-2 text-sm mt-1" />
            </div>

            <div>
              <label class="text-xs font-medium text-gray-600">審査員長作業開始</label>
              <input v-model="form.chief_start" type="date" class="w-full border rounded p-2 text-sm mt-1" />
            </div>
            <div>
              <label class="text-xs font-medium text-gray-600">審査員長作業終了</label>
              <input v-model="form.chief_end" type="date" class="w-full border rounded p-2 text-sm mt-1" />
            </div>
          </div>

          <p v-if="Object.keys(errors).length > 0" class="text-xs text-red-500">
            入力内容を確認してください。
          </p>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="showModal = false">キャンセル</Button>
          <Button class="bg-blue-600 hover:bg-blue-700 text-white" @click="submit">
            {{ editingId ? '更新する' : '登録する' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter
} from '@/components/ui/dialog'

const page = usePage()

defineProps({
  schedules: Array,
})

const showModal = ref(false)
const editingId = ref(null)
const errors = ref({})

const emptyForm = () => ({
  period_name: '',
  application_start: '',
  application_end: '',
  subleader_start: '',
  subleader_end: '',
  reviewer_start: '',
  reviewer_end: '',
  chief_start: '',
  chief_end: '',
})

const form = reactive(emptyForm())

const openCreateModal = () => {
  editingId.value = null
  Object.assign(form, emptyForm())
  errors.value = {}
  showModal.value = true
}

const openEditModal = (s) => {
  editingId.value = s.id
  Object.assign(form, {
    period_name: s.period_name,
    application_start: s.application_start?.split('T')[0],
    application_end: s.application_end?.split('T')[0],
    subleader_start: s.subleader_start?.split('T')[0],
    subleader_end: s.subleader_end?.split('T')[0],
    reviewer_start: s.reviewer_start?.split('T')[0],
    reviewer_end: s.reviewer_end?.split('T')[0],
    chief_start: s.chief_start?.split('T')[0],
    chief_end: s.chief_end?.split('T')[0],
  })
  errors.value = {}
  showModal.value = true
}

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => { showModal.value = false },
    onError: (e) => { errors.value = e },
  }

  if (editingId.value) {
    router.put(route('admin.schedules.update', editingId.value), form, options)
  } else {
    router.post(route('admin.schedules.store'), form, options)
  }
}

const destroy = (s) => {
  if (!confirm(`「${s.period_name}」を削除しますか？`)) return
  router.delete(route('admin.schedules.destroy', s.id), { preserveScroll: true })
}

const formatRange = (start, end) => {
  const s = start?.split('T')[0]
  const e = end?.split('T')[0]
  return s && e ? `${s} 〜 ${e}` : '-'
}

const notifiedBadgeClass = (notified) => [
  'text-[10px] px-1.5 py-0.5 rounded-full font-medium',
  notified ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-400',
]
</script>
