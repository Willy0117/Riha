<template>
  <AppLayout :title="`${member.name} - 審査`">
    <template #header>{{ member.name }} の審査</template>

    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <button
          @click="backToIndex"
          class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700"
        >
          <ArrowLeft class="w-4 h-4" />
          一覧に戻る
        </button>
        <div class="flex gap-3">
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
            :title="!allReviewed ? '全ての書類の審査が完了すると押せます' : ''"
            @click="handleJudge('fail')"
          >
            <XCircle class="w-4 h-4 mr-2" />
            不合格
          </Button>
        </div>
      </div>

      <div class="sticky top-0 z-20">
        <Card class="shadow-md bg-white">
          <CardContent class="p-3 space-y-2">
            <div class="grid grid-cols-2 md:grid-cols-7 gap-3 text-sm">
              <div>
                <p class="text-xs text-gray-500">氏名</p>
                <p class="font-semibold leading-tight">{{ member.name }}</p>
                <p class="text-xs text-gray-400">会員番号：{{ member.code }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">申込日時</p>
                <p class="font-semibold leading-tight">{{ appliedAtLabel }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">認定・更新期間</p>
                <p class="font-semibold leading-tight">{{ cycle.start_date?.split('T')[0] }} - {{ cycle.end_date?.split('T')[0] }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">審査数 / 総審査</p>
                <p class="font-semibold leading-tight">{{ reviewedCount }} / {{ totalCount }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">合計単位</p>
                <p class="font-semibold leading-tight">{{ totalPoints }} / {{ requiredPoints }} 単位</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">学術集会参加</p>
                <p class="font-semibold leading-tight">{{ conferenceCount }} / {{ requiredConferenceCount }} 回</p>
              </div>
              <div v-if="cycle.reviewer_judgment === 're_review'">
                <p class="text-xs text-gray-500">審査員による判定</p>
                <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium bg-orange-50 text-orange-600 border border-orange-200">
                  {{ judgmentLabel(cycle.reviewer_judgment) }}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div>
        <!-- 委員長からの差し戻し理由（re_reviewのときのみ表示） -->
        <div
          v-if="cycle.reviewer_judgment === 're_review' && cycle.chief_feedback"
          class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-4"
        >
          <p class="text-sm font-semibold text-orange-700 mb-1">委員長から差し戻されました</p>
          <p class="text-sm text-orange-700 whitespace-pre-wrap">{{ cycle.chief_feedback }}</p>
        </div>

        <!-- [今回修正] 却下理由（申請者へのメッセージ）と委員長へのメッセージを完全に分離した、独立2つの入力欄 -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 mb-4">
          <label class="text-sm font-semibold text-gray-700 mb-2 block">
            申請者へのメッセージ（却下理由）
            <span class="text-xs font-normal text-gray-400">（不合格の場合は必須・申請者本人に却下理由として表示されます）</span>
          </label>
          <textarea
            v-model="rejectionReason"
            class="input-field text-sm resize-none w-full"
            rows="3"
            placeholder="不合格の場合は、申請者に伝わる却下理由を入力してください（合格の場合は入力不要です）"
          />
        </div>

        <!-- [今回追加] 委員長へのメッセージ（差し戻し案件のときだけ表示・完全に別の入力欄） -->
        <div v-if="cycle.reviewer_judgment === 're_review'" class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
          <label class="text-sm font-semibold text-blue-700 mb-2 block">
            委員長へのメッセージ
            <span class="text-xs font-normal text-blue-400">（任意・合格として再提出する際に委員長へ伝えたい内容があれば入力してください）</span>
          </label>
          <textarea
            v-model="chiefMessage"
            class="input-field text-sm resize-none w-full"
            rows="3"
            placeholder="委員長への申し送り事項があれば入力してください"
          />
        </div>

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
              class="flex gap-6 p-6 bg-white rounded-xl border transition"
              :class="upload.chief_flagged ? 'border-orange-300 ring-1 ring-orange-200' : 'border-gray-200'"
            >
              <div class="w-32 flex-none">
                <div
                  class="w-full h-32 bg-gray-50 border border-gray-200 rounded-lg flex flex-col items-center justify-center text-gray-400 cursor-pointer hover:bg-gray-100 transition"
                  @click="openPreview(upload)"
                >
                  <FileText class="w-10 h-10" />
                  <span class="text-[10px] mt-1">{{ upload.is_image ? '画像' : 'PDF' }}</span>
                </div>
              </div>

              <div class="flex-1 flex flex-col gap-4">
                <div class="flex items-start justify-between">
                  <div>
                    <span
                      v-if="upload.chief_flagged"
                      class="inline-block text-[11px] font-semibold text-orange-700 bg-orange-100 px-2 py-0.5 rounded-full mb-1"
                    >
                      委員長からの指摘対象
                    </span>
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
              <div class="w-32 flex-none">
                <div
                  class="w-full h-32 bg-gray-50 border border-gray-200 rounded-lg flex flex-col items-center justify-center text-gray-400 cursor-pointer hover:bg-gray-100 transition"
                  @click="openPreview(upload)"
                >
                  <FileText class="w-10 h-10" />
                  <span class="text-[10px] mt-1">{{ upload.is_image ? '画像' : 'PDF' }}</span>
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

    <!-- [今回変更] previewUpload（現在表示中の書類）を保持し、◁▷で前後に移動できるようにする。
         右側に情報パネル（学会名・区分・種別・承認/差し戻し操作）を追加した左右分割レイアウト。 -->
    <Dialog :open="!!previewUpload" @update:open="previewUpload = null">
      <DialogContent class="w-[95vw] max-w-[95vw] h-[95vh] max-h-[95vh] p-0 flex flex-col">
        <DialogHeader class="px-4 py-3 border-b flex-row items-center justify-between">
          <DialogTitle>
            {{ previewUpload ? (sessionLabel(previewUpload.session) || previewUpload.credit_conference_name) : 'プレビュー' }}
          </DialogTitle>
        </DialogHeader>

        <div class="flex-1 flex overflow-hidden">
          <!-- 左：プレビュー -->
          <div class="flex-1 relative flex items-center justify-center bg-gray-50 overflow-hidden">
            <button
              v-if="uploadList.length > 1"
              class="absolute left-2 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/90 border border-gray-200 shadow flex items-center justify-center hover:bg-white"
              @click="showAdjacentPreview(-1)"
            >
              <ChevronLeft class="w-6 h-6 text-gray-600" />
            </button>

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

            <button
              v-if="uploadList.length > 1"
              class="absolute right-2 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/90 border border-gray-200 shadow flex items-center justify-center hover:bg-white"
              @click="showAdjacentPreview(1)"
            >
              <ChevronRight class="w-6 h-6 text-gray-600" />
            </button>
          </div>

          <!-- [今回追加] 右：情報＋操作パネル -->
          <div v-if="previewUpload" class="w-80 flex-none border-l border-gray-200 overflow-y-auto p-4 space-y-4">
            <div>
              <span
                v-if="previewUpload.chief_flagged"
                class="inline-block text-[11px] font-semibold text-orange-700 bg-orange-100 px-2 py-0.5 rounded-full mb-1"
              >
                委員長からの指摘対象
              </span>
              <h3 class="text-lg font-bold text-indigo-900">
                {{ sessionLabel(previewUpload.session) || previewUpload.credit_conference_name }}
              </h3>
              <p class="text-sm text-gray-700 mt-1">
                【{{ previewUpload.credit_conference_name }}】{{ previewUpload.role_name }}
              </p>
              <div class="flex items-center gap-3 mt-2">
                <span class="flex items-center gap-1 text-xs text-gray-500">
                  <Calendar class="w-3.5 h-3.5" />
                  {{ previewUpload.issued_date?.split('T')[0] }}
                </span>
                <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                  +{{ previewUpload.points }} 単位
                </span>
              </div>
              <span
                class="inline-block mt-2 text-xs px-2 py-1 rounded-full font-medium"
                :class="{
                  'bg-yellow-50 text-yellow-600 border border-yellow-200': previewUpload.status === 'pending',
                  'bg-green-50 text-green-600 border border-green-200': previewUpload.status === 'approved',
                  'bg-red-50 text-red-600 border border-red-200': previewUpload.status === 'rejected',
                }"
              >
                {{ statusLabel(previewUpload.status) }}
              </span>
            </div>

            <!-- 未審査：承認/差し戻し操作 -->
            <div v-if="previewUpload.status === 'pending'" class="space-y-3">
              <div class="bg-gray-50 rounded-xl p-3 space-y-3">
                <div class="space-y-1">
                  <label class="text-xs font-medium text-gray-600">差し戻し理由の選択</label>
                  <select v-model="rejectReasons[previewUpload.id]" class="input-field text-sm">
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
                    v-model="rejectComments[previewUpload.id]"
                    class="input-field text-sm resize-none w-full"
                    rows="3"
                    placeholder="具体的な不備内容を入力してください..."
                  />
                </div>
              </div>

              <div class="flex flex-col gap-2">
                <Button
                  class="w-full bg-green-600 hover:bg-green-700 text-white font-bold"
                  @click="handleApprove(previewUpload.id)"
                >
                  <CheckCircle2 class="w-4 h-4 mr-2" />
                  この書類を承認する
                </Button>
                <Button
                  variant="outline"
                  class="w-full text-red-600 border-red-300 hover:bg-red-50 font-bold"
                  @click="handleReject(previewUpload)"
                >
                  <XCircle class="w-4 h-4 mr-2" />
                  差し戻す
                </Button>
              </div>
            </div>

            <!-- 審査済み：結果表示 -->
            <div v-else>
              <div v-if="previewUpload.status === 'approved'" class="bg-green-50 rounded-xl p-3 flex items-center gap-2 text-green-700">
                <CheckCircle2 class="w-5 h-5" />
                <span class="font-semibold text-sm">この書類は承認済みです。</span>
              </div>
              <div v-else class="bg-red-50 rounded-xl p-3 space-y-1">
                <div class="flex items-center gap-2 text-red-600 font-semibold text-sm">
                  <XCircle class="w-5 h-5" />
                  差し戻し
                </div>
                <p class="text-sm text-red-500">{{ previewUpload.rejection_message }}</p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter class="px-4 py-3 border-t">
          <Button variant="outline" @click="previewUpload = null">閉じる</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
  ArrowLeft, FileText, Calendar, CheckCircle2, XCircle, ChevronLeft, ChevronRight
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
// [今回変更] previewPdf（URL文字列のみ）ではなく、現在プレビュー中のupload全体を保持する
const previewUpload = ref(null)
// [今回修正] 却下理由（申請者宛）と委員長宛メッセージを完全に分離した、独立2つの変数
const rejectionReason = ref('')
const chiefMessage = ref('')

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

// [今回追加] 全ての書類の審査が完了しているか（pendingが1件も無いか）
const allReviewed = computed(() => uploadList.value.every(u => u.status !== 'pending'))

const canPass = computed(() =>
  totalPoints.value >= requiredPoints && conferenceCount.value >= requiredConferenceCount
)
// [今回変更] 基準未達が確定しているだけでなく、全書類の審査が完了していることも必須にする
const canFail = computed(() =>
  allReviewed.value &&
  (maxPossiblePoints.value < requiredPoints || maxPossibleConferenceCount.value < requiredConferenceCount)
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
  const map = { unreviewed: '未判定', pass: '合格', fail: '不合格', re_review: '差し戻し' }
  return map[judgment] ?? '未判定'
}

const sessionLabel = (session) => {
  return session ? `第${session}回` : ''
}

const appliedAtLabel = computed(() => {
  if (!cycle.value.updated_at) return '-'
  return cycle.value.updated_at.split('T')[0]
})

// [今回変更] クリックされたupload自体を渡す（idではなくオブジェクト）
const openPreview = (upload) => {
  previewUpload.value = upload
}

// プレビュー中の書類の実ファイルURL（PDF/画像とも同じエンドポイントでOK）
const previewFileUrl = computed(() =>
  previewUpload.value ? route('admin.reviewer.view', { id: previewUpload.value.id }) : null
)

// [今回追加] ◁▷ボタンで、uploadList内の前後の書類に移動する
const showAdjacentPreview = (direction) => {
  if (!previewUpload.value) return
  const currentIndex = uploadList.value.findIndex(u => u.id === previewUpload.value.id)
  if (currentIndex === -1) return

  const nextIndex = (currentIndex + direction + uploadList.value.length) % uploadList.value.length
  previewUpload.value = uploadList.value[nextIndex]
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

// [今回追加] 差し戻し理由（プルダウンの値）→ 表示用の日本語ラベル
const rejectReasonLabels = {
  unclear: '書類が不鮮明',
  wrong: '書類の種類が違う',
  missing: '必要情報が不足',
  other: 'その他',
}

const handleReject = (upload) => {
  const reasonKey = rejectReasons.value[upload.id]
  const comment = rejectComments.value[upload.id] ?? ''

  if (!reasonKey) {
    alert('差し戻し理由を選択してください')
    return
  }
  if (!confirm('この書類を差し戻しますか？')) return

  // [今回修正] 英語キーのまま保存されないよう日本語ラベルに変換し、
  // コメントがあってもプルダウンの理由が消えないよう両方を連結して保存する
  const reasonLabel = rejectReasonLabels[reasonKey] ?? reasonKey
  const rejectionMessage = comment ? `${reasonLabel}：${comment}` : reasonLabel

  router.post(
    route('admin.reviewer.reject', { id: upload.id }),
    { rejection_message: rejectionMessage },
    {
      preserveScroll: true,
      onSuccess: () => {
        const target = uploadList.value.find(u => u.id === upload.id)
        if (target) {
          target.status = 'rejected'
          target.rejection_message = rejectionMessage
        }
      }
    }
  )
}

const handleJudge = (judgment) => {
  if (judgment === 'pass' && !canPass.value) return
  if (judgment === 'fail' && !canFail.value) return

  if (judgment === 'fail' && !rejectionReason.value.trim()) {
    alert('不合格の場合は理由の入力が必須です。')
    return
  }

  const label = judgment === 'pass' ? '合格' : '不合格'
  const confirmMessage = judgment === 'fail'
    ? 'この申請を「不合格」とします。よろしいですか？'
    : `この申請を「${label}」と判定しますか？`
  if (!confirm(confirmMessage)) return

  // [今回修正] 不合格時は申請者への却下理由、合格時は委員長へのメッセージを、
  // それぞれ独立した入力欄からサーバーへ送る
  const message = judgment === 'fail' ? rejectionReason.value : chiefMessage.value

  router.post(
    route('admin.reviewer.judge', { cycle: cycle.value.id }),
    { judgment, message },
    {
      preserveScroll: true,
      onSuccess: () => {
        cycle.value.reviewer_judgment = judgment
        rejectionReason.value = ''
        chiefMessage.value = ''
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
