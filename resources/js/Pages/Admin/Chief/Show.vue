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

      <!-- [今回追加] 審査員からのメッセージ（不合格時：申請者への却下理由／合格・差し戻し再提出時：委員長宛メッセージ） -->
      <div v-if="cycle.reviewer_judgment === 'fail' && cycle.reason" class="bg-red-50 border border-red-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-red-700 mb-1">審査員による不合格理由（申請者へ表示されます）</p>
        <p class="text-sm text-red-700 whitespace-pre-wrap">{{ cycle.reason }}</p>
      </div>
      <div v-if="cycle.reviewer_response_message" class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-blue-700 mb-1">審査員から委員長へのメッセージ</p>
        <p class="text-sm text-blue-700 whitespace-pre-wrap">{{ cycle.reviewer_response_message }}</p>
      </div>

      <!-- 審査員への差し戻し -->
      <div class="bg-white rounded-xl border border-orange-200 p-6 space-y-3">
        <h2 class="text-lg font-bold text-orange-700">審査員への差し戻し</h2>
        <p class="text-xs text-gray-500">
          下の書類一覧でチェックした資料が「指摘対象」として審査員に伝わります（差し戻すには1件以上の選択が必須です）。
        </p>
        <div v-if="flaggedIds.length > 0" class="flex flex-wrap gap-2">
          <span
            v-for="id in flaggedIds"
            :key="id"
            class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded-full font-medium"
          >
            資料{{ flaggedIndexLabel(id) }}
          </span>
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">差し戻し理由（必須）</label>
          <textarea
            v-model="sendBackReason"
            class="w-full border rounded-lg p-2 text-sm"
            rows="3"
            placeholder="審査員に伝える差し戻し理由を入力してください"
          ></textarea>
        </div>
        <div class="flex justify-end">
          <Button
            class="bg-orange-600 hover:bg-orange-700 text-white font-bold"
            :disabled="!sendBackReason || flaggedIds.length === 0"
            @click="submitSendBack"
          >
            この内容で差し戻す
          </Button>
        </div>
      </div>

      <div>
        <h2 class="text-xl font-bold text-gray-800 mb-4">提出書類（閲覧専用・差し戻し時は指摘対象を選択できます）</h2>

        <div
          v-if="uploadList.length === 0"
          class="bg-gray-50 border border-dashed border-gray-300 rounded-2xl p-10 text-center text-gray-500"
        >
          提出書類がありません
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(upload, index) in uploadList"
            :key="upload.id"
            class="flex gap-4 p-3 bg-white rounded-xl border transition"
            :class="flaggedIds.includes(upload.id) ? 'border-orange-300 bg-orange-50/40' : 'border-gray-200'"
          >
            <div class="flex-none flex flex-col items-center gap-2 pt-1">
              <input
                type="checkbox"
                :checked="flaggedIds.includes(upload.id)"
                @change="toggleFlag(upload.id)"
                class="w-4 h-4"
              />
              <span class="text-xs font-semibold text-gray-400">資料{{ index + 1 }}</span>
            </div>

            <div class="w-32 flex-none">
              <div
                class="w-full h-32 bg-gray-50 border border-gray-200 rounded-lg flex flex-col items-center justify-center text-gray-400 cursor-pointer hover:bg-gray-100 transition"
                @click="openPreview(upload)"
              >
                <FileText class="w-10 h-10" />
                <span class="text-[10px] mt-1">{{ upload.is_image ? '画像' : 'PDF' }}</span>
              </div>
            </div>

            <div class="flex-1 flex flex-col gap-2">
              <div class="flex items-start justify-between">
                <div>
                  <h3 class="text-lg font-bold text-indigo-900">
                    資料{{ index + 1 }}：{{ sessionLabel(upload.session) || upload.credit_conference_name }}
                  </h3>
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

    <Dialog :open="!!previewUpload" @update:open="previewUpload = null">
      <DialogContent class="w-[95vw] max-w-[95vw] h-[95vh] max-h-[95vh] p-0 flex flex-col">
        <DialogHeader class="px-4 py-3 border-b">
          <DialogTitle>プレビュー</DialogTitle>
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
          <Button variant="outline" @click="previewUpload = null">閉じる</Button>
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

const previewUpload = ref(null)

// ---- 審査員への差し戻し（指摘対象の選択・理由入力） ----
const flaggedIds = ref([])
const sendBackReason = ref('')

const toggleFlag = (id) => {
  if (flaggedIds.value.includes(id)) {
    flaggedIds.value = flaggedIds.value.filter(i => i !== id)
  } else {
    flaggedIds.value.push(id)
  }
}

const flaggedIndexLabel = (id) => {
  const idx = uploadList.value.findIndex(u => u.id === id)
  return idx === -1 ? '?' : idx + 1
}

const submitSendBack = () => {
  if (!sendBackReason.value) return
  if (flaggedIds.value.length === 0) {
    alert('差し戻す資料を1件以上選択してください。')
    return
  }
  if (!confirm(`${member.name}の申請を審査員に差し戻しますか？`)) return

  router.post(
    route('admin.chief.sendBack', cycle.value.id),
    { reason: sendBackReason.value, flagged_upload_ids: flaggedIds.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        sendBackReason.value = ''
        flaggedIds.value = []
      },
    }
  )
}

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
  const map = { unreviewed: '未判定', pass: '合格', fail: '不合格', re_review: '差し戻し' }
  return map[judgment] ?? '未判定'
}

const sessionLabel = (session) => {
  return session ? `第${session}回` : ''
}

const openPreview = (upload) => {
  previewUpload.value = upload
}

const previewFileUrl = computed(() =>
  previewUpload.value ? route('admin.chief.view', { id: previewUpload.value.id }) : null
)

const backToIndex = () => {
  router.get(route('admin.chief.index'))
}
</script>
