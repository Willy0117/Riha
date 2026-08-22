<template>
  <AppLayout :title="`${member.name} - 詳細`">
    <template #header>{{ member.name }} の申請詳細</template>

    <div class="p-6 space-y-6">
      <button
        @click="backToIndex"
        class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700"
      >
        <ArrowLeft class="w-4 h-4" />
        一覧に戻る
      </button>

      <Card>
        <CardContent class="p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <p class="text-xs text-gray-500">氏名</p>
            <p class="font-semibold">{{ member.name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">会員番号：{{ member.code }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">申込日時</p>
            <p class="font-semibold">{{ appliedAtLabel }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">認定・更新期間</p>
            <p class="font-semibold">{{ cycle.start_date?.split('T')[0] }} - {{ cycle.end_date?.split('T')[0] }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">審査数 / 総審査</p>
            <p class="font-semibold">{{ reviewedCount }} / {{ totalCount }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">合計単位</p>
            <p class="font-semibold">{{ totalPoints }} 単位</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">学術集会参加</p>
            <p class="font-semibold">{{ conferenceCount }} 回</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">担当審査員</p>
            <p class="font-semibold">{{ cycle.reviewer_admin?.name ?? '未アサイン' }}</p>
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
            <p class="text-xs text-gray-500">ステータス</p>
            <p class="font-semibold">{{ cycle.status }}</p>
          </div>
        </CardContent>
      </Card>

      <div>
        <h2 class="text-xl font-bold text-gray-800 mb-4">提出書類（閲覧専用）</h2>

        <div
          v-if="uploadList.length === 0"
          class="bg-gray-50 border border-dashed border-gray-300 rounded-2xl p-10 text-center text-gray-500"
        >
          提出書類がありません
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="upload in uploadList"
            :key="upload.id"
            class="flex gap-6 p-6 bg-white rounded-xl border border-gray-200"
          >
            <div class="w-64 flex-none">
              <img
                v-if="upload.thumbnail_url"
                :src="upload.thumbnail_url"
                class="w-full h-80 object-contain rounded-lg cursor-pointer"
                @click="openPreview(upload.id)"
              />
              <div
                v-else
                class="w-full h-80 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm cursor-pointer"
                @click="openPreview(upload.id)"
              >
                <FileText class="w-8 h-8" />
              </div>
            </div>

            <div class="flex-1 flex flex-col gap-2">
              <div class="flex items-start justify-between">
                <div>
                  <h3 class="text-lg font-bold text-indigo-900">{{ sessionLabel(upload.session) || upload.credit_conference_name }}</h3>
                  <p class="text-sm text-gray-700 mt-1">
                    【{{ upload.credit_conference_name }}】{{ upload.role_name }}
                  </p>
                  <div class="flex items-center gap-3 mt-2">
                    <span class="flex items-center gap-1 text-xs text-gray-500">
                      <Calendar class="w-3.5 h-3.5" />
                      {{ upload.issued_date?.split('T')[0] }}
                    </span>
                    <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                      +{{ upload.points }} 単位
                    </span>
                  </div>
                </div>
                <span
                  class="text-xs px-2 py-1 rounded-full font-medium"
                  :class="{
                    'bg-yellow-50 text-yellow-600 border border-yellow-200': upload.status === 'pending',
                    'bg-green-50 text-green-600 border border-green-200': upload.status === 'approved',
                    'bg-red-50 text-red-600 border border-red-200': upload.status === 'rejected',
                  }"
                >
                  {{ statusLabel(upload.status) }}
                </span>
              </div>

              <p v-if="upload.status === 'rejected'" class="text-sm text-red-500">
                却下理由：{{ upload.rejection_message }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Dialog :open="!!previewPdf" @update:open="previewPdf = null">
      <DialogContent class="w-[95vw] max-w-[95vw] h-[95vh] max-h-[95vh] p-0 flex flex-col">
        <DialogHeader class="px-4 py-3 border-b">
          <DialogTitle>PDFプレビュー</DialogTitle>
        </DialogHeader>
        <div class="flex-1 overflow-hidden">
          <iframe v-if="previewPdf" :src="previewPdf" class="w-full h-full border-0" />
        </div>
        <DialogFooter class="px-4 py-3 border-t">
          <Button variant="outline" @click="previewPdf = null">閉じる</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { ArrowLeft, FileText, Calendar } from 'lucide-vue-next'

import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter
} from '@/components/ui/dialog'

const props = usePage()
const member = props.props.member
const cycle = ref(props.props.cycle)
const uploads = props.props.uploads

// thumbnail_url・role_name・credit_conference_name はコントローラ側（show()）で
// 計算済みの値をそのまま使う（クライアント側での復元ロジックは削除）
const uploadList = ref(uploads)

const previewPdf = ref(null)

// 委員長画面は閲覧専用のため、集計はあくまで表示用（判定ボタンなどの操作は一切設けない）
const isConferenceParticipation = (u) =>
  u.credit_category?.name === '学術集会'
  && u.credit_conference_name === '日本腎臓リハビリテーション学会'
  && u.role_name === '参加'

const totalCount = computed(() => uploadList.value.length)
const reviewedCount = computed(() =>
  uploadList.value.filter(u => u.status === 'approved' || u.status === 'rejected').length
)

const approvedUploads = computed(() => uploadList.value.filter(u => u.status === 'approved'))
const totalPoints = computed(() => approvedUploads.value.reduce((sum, u) => sum + (u.points ?? 0), 0))
const conferenceCount = computed(() => approvedUploads.value.filter(isConferenceParticipation).length)

const appliedAtLabel = computed(() => {
  if (!cycle.value.updated_at) return '-'
  return cycle.value.updated_at.split('T')[0]
})

const statusLabel = (status) => {
  const map = { pending: '未審査', approved: '承認済み', rejected: '差し戻し' }
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
  previewPdf.value = route('admin.chief.view', { id })
}

const backToIndex = () => {
  router.get(route('admin.chief.index'))
}
</script>
