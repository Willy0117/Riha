<template>
  <AppLayout :title="`${member.name} - 審査`">
    <template #header>{{ member.name }} の審査</template>

    <div class="p-6 space-y-6">
      <button
        @click="backToIndex"
        class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700"
      >
        <ArrowLeft class="w-4 h-4" />
        一覧に戻る
      </button>

      <Card>
        <CardContent class="p-4 space-y-4">
          <div class="grid grid-cols-2 md:grid-cols-7 gap-4 text-sm">
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
              <p class="font-semibold">{{ totalPoints }} / {{ requiredPoints }} 単位</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">学術集会参加</p>
              <p class="font-semibold">{{ conferenceCount }} / {{ requiredConferenceCount }} 回</p>
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
          </div>

          <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
            <Button
              class="bg-green-600 hover:bg-green-700 text-white font-bold disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="!canPass"
              @click="handleJudge('pass')"
            >
              <CheckCircle2 class="w-4 h-4 mr-2" />
              合格
            </Button>
            <Button
              variant="outline"
              class="text-red-600 border-red-300 hover:bg-red-50 font-bold disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="!canFail"
              @click="handleJudge('fail')"
            >
              <XCircle class="w-4 h-4 mr-2" />
              不合格
            </Button>
          </div>
        </CardContent>
      </Card>

      <div>
        <h2 class="text-xl font-bold text-gray-800 mb-4">提出書類</h2>

        <div
          v-if="uploadList.length === 0"
          class="bg-gray-50 border border-dashed border-gray-300 rounded-2xl p-10 text-center text-gray-500"
        >
          提出書類がありません
        </div>

        <div v-else class="space-y-6">
          <!-- 未審査（要対応）を先に表示 -->
          <div v-if="pendingUploads.length > 0" class="space-y-4">
            <p class="text-sm font-semibold text-amber-600">未審査（{{ pendingUploads.length }}件）</p>
            <div
              v-for="upload in pendingUploads"
              :key="upload.id"
              class="flex gap-6 p-6 bg-white rounded-xl border border-gray-200"
            >
              <div class="w-48 flex-none">
                <img
                  v-if="upload.thumbnail_url"
                  :src="upload.thumbnail_url"
                  class="w-full h-56 object-contain rounded-lg cursor-pointer"
                  @click="openPreview(upload.id)"
                />
                <div
                  v-else
                  class="w-full h-56 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm cursor-pointer"
                  @click="openPreview(upload.id)"
                >
                  <FileText class="w-8 h-8" />
                </div>
              </div>

              <div class="flex-1 flex flex-col gap-4">
                <div class="flex items-start justify-between">
                  <div>
                    <h3 class="text-xl font-bold text-indigo-900">{{ sessionLabel(upload.session) || upload.credit_conference_name }}</h3>
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
                  <span class="text-xs px-2 py-1 rounded-full font-medium bg-yellow-50 text-yellow-600 border border-yellow-200">
                    {{ statusLabel(upload.status) }}
                  </span>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                  <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-600">差し戻し理由の選択</label>
                    <select v-model="rejectReasons[upload.id]" class="input-field text-sm">
                      <option value="" disabled>理由を選択してください</option>
                      <option value="unclear">書類が不鮮明</option>
                      <option value="wrong">書類の種類が違う</option>
                      <option value="missing">必要情報が不足</option>
                      <option value="other">その他</option>
                    </select>
                  </div>
                  <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-600">詳細コメント</label>
                    <textarea
                      v-model="rejectComments[upload.id]"
                      class="input-field text-sm resize-none w-full"
                      rows="3"
                      placeholder="具体的な不備内容を入力してください..."
                    />
                  </div>
                </div>

                <div class="flex gap-3">
                  <Button
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold"
                    @click="handleApprove(upload.id)"
                  >
                    <CheckCircle2 class="w-4 h-4 mr-2" />
                    この書類を承認する
                  </Button>
                  <Button
                    variant="outline"
                    class="flex-1 text-red-600 border-red-300 hover:bg-red-50 font-bold"
                    @click="handleReject(upload)"
                  >
                    <XCircle class="w-4 h-4 mr-2" />
                    差し戻す
                  </Button>
                </div>
              </div>
            </div>
          </div>

          <!-- 審査済み（承認/差し戻し）は下部に表示 -->
          <div v-if="reviewedUploads.length > 0" class="space-y-4">
            <p class="text-sm font-semibold text-gray-400">審査済み（{{ reviewedUploads.length }}件）</p>
            <div
              v-for="upload in reviewedUploads"
              :key="upload.id"
              class="flex gap-6 p-6 bg-white rounded-xl border border-gray-200"
            >
              <div class="w-48 flex-none">
                <img
                  v-if="upload.thumbnail_url"
                  :src="upload.thumbnail_url"
                  class="w-full h-56 object-contain rounded-lg cursor-pointer"
                  @click="openPreview(upload.id)"
                />
                <div
                  v-else
                  class="w-full h-56 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-sm cursor-pointer"
                  @click="openPreview(upload.id)"
                >
                  <FileText class="w-8 h-8" />
                </div>
              </div>

              <div class="flex-1 flex flex-col gap-4">
                <div class="flex items-start justify-between">
                  <div>
                    <h3 class="text-xl font-bold text-indigo-900">{{ sessionLabel(upload.session) || upload.credit_conference_name }}</h3>
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
                      'bg-green-50 text-green-600 border border-green-200': upload.status === 'approved',
                      'bg-red-50 text-red-600 border border-red-200': upload.status === 'rejected',
                    }"
                  >
                    {{ statusLabel(upload.status) }}
                  </span>
                </div>

                <div v-if="upload.status === 'approved'" class="bg-green-50 rounded-xl p-4 flex items-center gap-2 text-green-700">
                  <CheckCircle2 class="w-5 h-5" />
                  <span class="font-semibold">この書類は承認済みです。</span>
                </div>

                <div v-else class="bg-red-50 rounded-xl p-4 space-y-1">
                  <div class="flex items-center gap-2 text-red-600 font-semibold">
                    <XCircle class="w-5 h-5" />
                    差し戻し
                  </div>
                  <p class="text-sm text-red-500">{{ upload.rejection_message }}</p>
                </div>
              </div>
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
import {
  ArrowLeft, FileText, Calendar, CheckCircle2, XCircle
} from 'lucide-vue-next'

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

const rejectReasons = ref({})
const rejectComments = ref({})
const previewPdf = ref(null)

const requiredPoints = cycle.value.required_points ?? 50
const requiredConferenceCount = cycle.value.required_conference_count ?? 2

// サーバー側（ReviewerController@show / judge）と同じ判定条件をそのまま踏襲
const isConferenceParticipation = (u) =>
  u.credit_category?.name === '学術集会'
  && u.credit_conference_name === '日本腎臓リハビリテーション学会'
  && u.role_name === '参加'

// 承認/差し戻しボタンをクリックするたびに、これらは自動で再計算される
const approvedUploads = computed(() => uploadList.value.filter(u => u.status === 'approved'))
const possibleUploads = computed(() => uploadList.value.filter(u => u.status !== 'rejected'))

const totalPoints = computed(() => approvedUploads.value.reduce((sum, u) => sum + (u.points ?? 0), 0))
const conferenceCount = computed(() => approvedUploads.value.filter(isConferenceParticipation).length)

// 残り全部承認された場合に到達しうる最大値
const maxPossiblePoints = computed(() => possibleUploads.value.reduce((sum, u) => sum + (u.points ?? 0), 0))
const maxPossibleConferenceCount = computed(() => possibleUploads.value.filter(isConferenceParticipation).length)

const canPass = computed(() =>
  totalPoints.value >= requiredPoints && conferenceCount.value >= requiredConferenceCount
)
const canFail = computed(() =>
  maxPossiblePoints.value < requiredPoints || maxPossibleConferenceCount.value < requiredConferenceCount
)

const totalCount = computed(() => uploadList.value.length)
const reviewedCount = computed(() =>
  uploadList.value.filter(u => u.status === 'approved' || u.status === 'rejected').length
)

// 未審査（要対応）を上、審査済み（承認/差し戻し）を下に分けて表示する
const pendingUploads = computed(() => uploadList.value.filter(u => u.status === 'pending'))
const reviewedUploads = computed(() => uploadList.value.filter(u => u.status !== 'pending'))

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

const appliedAtLabel = computed(() => {
  if (!cycle.value.updated_at) return '-'
  return cycle.value.updated_at.split('T')[0]
})

const openPreview = (id) => {
  previewPdf.value = route('admin.reviewer.view', { id })
}

const handleApprove = (id) => {
  if (!confirm('この書類を承認しますか？')) return
  router.post(
    route('admin.reviewer.approve', { id }),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        const upload = uploadList.value.find(u => u.id === id)
        if (upload) upload.status = 'approved'
        // totalPoints・conferenceCount・canPass・canFail は computed のため自動で再計算される
      }
    }
  )
}

const handleReject = (upload) => {
  const reason = rejectReasons.value[upload.id]
  const comment = rejectComments.value[upload.id] ?? ''

  if (!reason) {
    alert('差し戻し理由を選択してください')
    return
  }
  if (!confirm('この書類を差し戻しますか？')) return

  router.post(
    route('admin.reviewer.reject', { id: upload.id }),
    { rejection_message: comment || reason },
    {
      preserveScroll: true,
      onSuccess: () => {
        const target = uploadList.value.find(u => u.id === upload.id)
        if (target) {
          target.status = 'rejected'
          target.rejection_message = comment || reason
        }
      }
    }
  )
}

const handleJudge = (judgment) => {
  if (judgment === 'pass' && !canPass.value) return
  if (judgment === 'fail' && !canFail.value) return

  const label = judgment === 'pass' ? '合格' : '不合格'
  if (!confirm(`この申請を「${label}」と判定しますか？`)) return

  router.post(
    route('admin.reviewer.judge', { cycle: cycle.value.id }),
    { judgment },
    {
      preserveScroll: true,
      onSuccess: () => {
        cycle.value.reviewer_judgment = judgment
      }
    }
  )
}

const backToIndex = () => {
  router.get(route('admin.reviewer.index'))
}
</script>

<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>
