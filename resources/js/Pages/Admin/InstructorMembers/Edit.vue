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
            <p class="font-semibold">{{ formatDate(cycle.start_date) }} - {{ formatDate(cycle.end_date) }}</p>
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
              class="status-badge-common"
              :class="{
                'status-badge-gray': cycle.reviewer_judgment === 'unreviewed' || !cycle.reviewer_judgment,
                'status-badge-green': cycle.reviewer_judgment === 'pass',
                'status-badge-red': cycle.reviewer_judgment === 'fail',
                'status-badge-orange': cycle.reviewer_judgment === 're_review',
              }"
            >
              {{ judgmentLabel(cycle.reviewer_judgment) }}
            </span>
          </div>
          <div>
            <p class="text-xs text-gray-500">{{ t('status') }}</p>
            <p class="font-semibold">{{ cycleStatusLabel(cycle.status) }}</p>
          </div>
        </CardContent>
      </Card>

      <!-- [今回追加] 認定期間の編集フォーム（end_date・renewal_end_dateのみ） -->
      <Card>
        <CardContent class="p-4">
          <h2 class="text-sm font-bold text-gray-700 mb-4">指導士認定期間設定</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-gray-500 mb-1 block">認定終了日（end_date）</label>
              <input
                v-model="form.end_date"
                type="date"
                class="input-field"
              />
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">更新受付終了日（renewal_end_date）</label>
              <input
                v-model="form.renewal_end_date"
                type="date"
                class="input-field"
              />
            </div>
          </div>
          <div class="flex justify-end mt-4">
            <Button :disabled="!hasCycle" @click="submit">保存する</Button>
          </div>
        </CardContent>
      </Card>

      <!-- [今回変更] 3つのメッセージ（更新者向け・委員長向け・審査員向け）を横並びカードで表示 -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
          <p class="text-xs font-semibold text-red-700 mb-1">更新者向けメッセージ（不合格理由）</p>
          <p v-if="cycle.reason" class="text-sm text-red-700 whitespace-pre-wrap">{{ cycle.reason }}</p>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
          <p class="text-xs font-semibold text-blue-700 mb-1">委員長向けメッセージ（審査員から）</p>
          <p v-if="cycle.reviewer_response_message" class="text-sm text-blue-700 whitespace-pre-wrap">{{ cycle.reviewer_response_message }}</p>
        </div>

        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
          <p class="text-xs font-semibold text-orange-700 mb-1">審査員向けメッセージ（委員長からの差し戻し理由）</p>
          <p v-if="cycle.chief_feedback" class="text-sm text-orange-700 whitespace-pre-wrap">{{ cycle.chief_feedback }}</p>
        </div>
      </div>

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
            @click="openPreview(upload)"
          >
            <div class="flex items-center justify-center h-16 bg-white/70 rounded-md mb-2">
              <FileText class="w-7 h-7 text-gray-400" />
            </div>

            <p class="text-sm font-bold text-indigo-900 truncate">{{ sessionLabel(upload.session) || upload.credit_conference_name }}</p>
            <p class="text-xs text-gray-500 truncate mt-0.5">{{ upload.role_name }}</p>

            <div class="flex items-center justify-between mt-2">
              <span class="text-[11px] text-gray-400">{{ formatDate(upload.issued_date) }}</span>
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
              {{ uploadStatusLabel(upload.status) }}
            </span>
            <p v-if="upload.status === 'rejected'" class="text-[11px] text-red-500 mt-1 line-clamp-2">
              {{ upload.rejection_message }}
            </p>
          </div>
        </div>
      </div>

    </div>

    <!-- PDF/画像プレビューDialog -->
    <Dialog :open="!!previewUpload" @update:open="previewUpload = null">
      <DialogContent class="w-[95vw] max-w-[95vw] h-[95vh] max-h-[95vh] p-0 flex flex-col">
        <DialogHeader class="px-4 py-3 border-b">
          <DialogTitle>{{ t('PDFpreview') }}</DialogTitle>
        </DialogHeader>
        <div class="flex-1 overflow-hidden flex items-center justify-center bg-gray-50">
          <img
            v-if="previewUpload?.is_image"
            :src="previewFileUrl"
            class="max-w-full max-h-full object-contain"
          />
          <iframe
            v-else-if="previewFileUrl"
            :src="previewFileUrl"
            class="w-full h-full border-0"
          />
        </div>
        <DialogFooter class="px-4 py-3 border-t">
          <Button variant="outline" @click="previewUpload = null">{{ t('closed') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import dayjs from 'dayjs'
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
const page = usePage()

const member = page.props.member
const uploads = page.props.uploads
const filters = page.props.filters
// [今回追加]
const can_edit = page.props.can_edit
const errors = computed(() => page.props.errors ?? {})

const cycle = member.update_cycles?.[0] || {
  id: null,
  start_date: '-',
  end_date: '-',
  renewal_end_date: '-',
  total_points: 0,
  conference_count: 0,
  status: 'before_update',
  reviewer_judgment: 'unreviewed',
  updated_at: null,
}

const hasCycle = computed(() => !!cycle.id)

// [今回追加] ステータス（cycle.status）の日本語ラベル（Index.vueと同じマップ）
const cycleStatusLabel = (status) => {
  const map = {
    'updated':       '更新済',
    'before_update': '更新前',
    'no_update':     '更新しない',
    'pending':       '審査中',
    'approved':      '承認',
    'reject':        '却下',
    'lapsed':        '資格喪失',
  }
  return map[status] ?? '-'
}

// [今回追加] YYYY-MM-DD形式でフォーマットする（時刻部分は表示しない）
const formatDate = (value) => {
  if (!value) return '-'
  return dayjs(value).format('YYYY-MM-DD')
}

// [今回追加] end_date・renewal_end_date 編集用フォーム
const form = reactive({
  end_date: cycle.end_date ? dayjs(cycle.end_date).format('YYYY-MM-DD') : '',
  renewal_end_date: cycle.renewal_end_date ? dayjs(cycle.renewal_end_date).format('YYYY-MM-DD') : '',
})

const submit = () => {
  if (!hasCycle.value) return
  if (!confirm('認定期間を更新します。よろしいですか？')) return

  router.put(
    route('admin.instructorMembers.update', member.id),
    {
      cycle_id: cycle.id,
      end_date: form.end_date,
      renewal_end_date: form.renewal_end_date,
    },
    { preserveScroll: true }
  )
}

// thumbnail_url・role_name・credit_conference_name・category_name はコントローラ側（edit()）で
// 計算済みの値をそのまま使う（クライアント側での復元ロジックは削除）
const uploadList = ref(uploads)

const previewUpload = ref(null)

const totalCount = computed(() => uploadList.value.length)
const reviewedCount = computed(() =>
  uploadList.value.filter(u => u.status === 'approved' || u.status === 'rejected').length
)

const appliedAtLabel = computed(() => formatDate(cycle.updated_at))

const uploadStatusLabel = (status) => {
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

const openPreview = (upload) => {
  previewUpload.value = upload
}

const previewFileUrl = computed(() =>
  previewUpload.value ? route('admin.instructorMembers.view', { id: previewUpload.value.id }) : null
)

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
