<template>
  <AppLayout :title="`${member.name} - ${t('instructor_details')}`">
    <template #header>{{ member.name }}</template>

    <div class="p-6 space-y-6">

      <!-- 戻るボタン -->
      <button
        @click="backToIndex"
        class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700"
      >
        <ArrowLeft class="w-4 h-4" />
        {{ t('back') }}
      </button>

      <!-- 会員情報 -->
      <Card>
        <CardContent class="p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <p class="text-xs text-gray-500">{{ t('name') }}</p>
            <p class="font-semibold">{{ member.name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">会員番号：{{ member.code }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">申込日時</p>
            <p class="font-semibold">{{ appliedAtLabel }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">{{ t('instructors.update_period') }}</p>
            <p class="font-semibold">{{ cycle.start_date?.split('T')[0] }} - {{ cycle.end_date?.split('T')[0] }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">審査数 / 総審査</p>
            <p class="font-semibold">{{ reviewedCount }} / {{ totalCount }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">{{ t('instructors.total_points') }}</p>
            <p class="font-semibold">{{ cycle.total_points }} 単位</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">{{ t('instructors.conference_count') }}</p>
            <p class="font-semibold">{{ cycle.conference_count }} 回</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">審査員による判定</p>
            <span
              class="text-sm px-2 py-1 rounded-full font-medium"
              :class="{
                'bg-gray-100 text-gray-500': cycle.reviewer_judgment === 'unreviewed' || !cycle.reviewer_judgment,
                'bg-green-50 text-green-600 border border-green-200': cycle.reviewer_judgment === 'pass',
                'bg-red-50 text-red-600 border border-red-200': cycle.reviewer_judgment === 'fail',
                'bg-orange-50 text-orange-600 border border-orange-200': cycle.reviewer_judgment === 're_review',
              }"
            >
              {{ judgmentLabel(cycle.reviewer_judgment) }}
            </span>
          </div>
          <div>
            <p class="text-xs text-gray-500">{{ t('status') }}</p>
            <p class="font-semibold">{{ t(`cycle_status.${cycle.status}`) }}</p>
          </div>
        </CardContent>
      </Card>

      <!-- アップロード一覧 -->
      <div>
        <h2 class="text-xl font-bold text-gray-800 mb-4">{{ t('uploaded_files') }}</h2>

        <div
          v-if="uploadList.length === 0"
          class="bg-gray-50 border border-dashed border-gray-300 rounded-2xl p-10 text-center text-gray-500"
        >
          {{ t('no_uploads') }}
        </div>

        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
          <div
            v-for="upload in uploadList"
            :key="upload.id"
            class="p-3 rounded-lg border cursor-pointer hover:shadow-sm transition"
            :class="{
              'bg-yellow-50/60 border-yellow-200 hover:border-yellow-300': upload.status === 'pending',
              'bg-green-50/60 border-green-200 hover:border-green-300': upload.status === 'approved',
              'bg-red-50/60 border-red-200 hover:border-red-300': upload.status === 'rejected',
            }"
            @click="openPreview(upload.id)"
          >
            <div class="flex items-center justify-center h-16 bg-white/70 rounded-md mb-2">
              <FileText class="w-7 h-7 text-gray-400" />
            </div>

            <p class="text-sm font-bold text-indigo-900 truncate">{{ sessionLabel(upload.session) || upload.credit_conference_name }}</p>
            <p class="text-xs text-gray-500 truncate mt-0.5">{{ upload.role_name }}</p>

            <div class="flex items-center justify-between mt-2">
              <span class="text-[11px] text-gray-400">{{ upload.issued_date?.split('T')[0] }}</span>
              <span class="text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-full">
                +{{ upload.points }}
              </span>
            </div>

            <span
              class="inline-block mt-2 text-[11px] px-2 py-0.5 rounded-full font-medium bg-white/80"
              :class="{
                'text-yellow-600 border border-yellow-200': upload.status === 'pending',
                'text-green-600 border border-green-200': upload.status === 'approved',
                'text-red-600 border border-red-200': upload.status === 'rejected',
              }"
            >
              {{ statusLabel(upload.status) }}
            </span>
            <p v-if="upload.status === 'rejected'" class="text-[11px] text-red-500 mt-1 line-clamp-2">
              {{ upload.rejection_message }}
            </p>
          </div>
        </div>
      </div>

    </div>

    <!-- PDFプレビューDialog -->
    <Dialog :open="!!previewPdf" @update:open="previewPdf = null">
      <DialogContent class="w-[95vw] max-w-[95vw] h-[95vh] max-h-[95vh] p-0 flex flex-col">
        <DialogHeader class="px-4 py-3 border-b">
          <DialogTitle>{{ t('PDFpreview') }}</DialogTitle>
        </DialogHeader>
        <div class="flex-1 overflow-hidden">
          <iframe
            v-if="previewPdf"
            :src="previewPdf"
            class="w-full h-full border-0"
          />
        </div>
        <DialogFooter class="px-4 py-3 border-t">
          <Button variant="outline" @click="previewPdf = null">{{ t('closed') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
  ArrowLeft, FileText
} from 'lucide-vue-next'

import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogHeader,
  DialogTitle, DialogFooter
} from '@/components/ui/dialog'

const { t } = useI18n()
const props = usePage()

const member = props.props.member
const uploads = props.props.uploads
const filters = props.props.filters

const cycle = member.update_cycles?.[0] || {
  start_date: '-',
  end_date: '-',
  total_points: 0,
  conference_count: 0,
  status: 'before_update',
  reviewer_judgment: 'unreviewed',
  updated_at: null,
}

// thumbnail_url・role_name・credit_conference_name・category_name はコントローラ側（show()）で
// 計算済みの値をそのまま使う（クライアント側での復元ロジックは削除）
const uploadList = ref(uploads)

const previewPdf = ref(null)

const totalCount = computed(() => uploadList.value.length)
const reviewedCount = computed(() =>
  uploadList.value.filter(u => u.status === 'approved' || u.status === 'rejected').length
)

const appliedAtLabel = computed(() => {
  if (!cycle.updated_at) return '-'
  return cycle.updated_at.split('T')[0]
})

const statusLabel = (status) => {
  const map = {
    pending:  '未審査',
    approved: '承認済み',
    rejected: '差し戻し',
  }
  return map[status] ?? '-'
}

const judgmentLabel = (judgment) => {
  const map = { unreviewed: '未判定', pass: '合格', fail: '不合格', re_review: '再審査' }
  return map[judgment] ?? '未判定'
}

const sessionLabel = (session) => {
  return session ? `第${session}回` : ''
}

const openPreview = (id) => {
  previewPdf.value = route('admin.instructorMembers.view', { id })
}

const backToIndex = () => {
  router.get(route('admin.instructorMembers.index'), {
    search: filters?.search ?? '',
    page: filters?.page ?? 1,
  })
}
</script>

<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>
