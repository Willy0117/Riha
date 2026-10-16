<template>
  <AppLayout title="受験申込">
    <template #header>指導士資格認定試験 受験申込</template>
    <div class="max-w-none mx-auto p-8 flex flex-col gap-6 font-sans text-[#1a1a2e]">

      <!-- 不合格・受験資格なし通知（×で閉じられる） -->
      <div
        v-if="showRejectNotice && exam?.status === 'reject' && exam?.reason"
        class="relative bg-red-50 border border-red-500 rounded-xl p-4 pr-10"
      >
        <button type="button" class="absolute top-3 right-3 text-red-400 hover:text-red-600" @click="showRejectNotice = false">
          <X class="w-4 h-4" />
        </button>
        <p class="text-sm font-semibold text-red-700 mb-1">今回の申請は受験資格が認められませんでした</p>
        <p class="text-sm text-red-600 whitespace-pre-wrap">{{ exam.reason }}</p>
      </div>
      <div
        v-if="showFailNotice && exam?.exam_result === 'fail'"
        class="relative bg-red-50 border border-red-500 rounded-xl p-4 pr-10"
      >
        <button type="button" class="absolute top-3 right-3 text-red-400 hover:text-red-600" @click="showFailNotice = false">
          <X class="w-4 h-4" />
        </button>
        <p class="text-sm font-semibold text-red-700 mb-1">試験の結果、今回は不合格でした</p>
      </div>

      <!-- 既に指導士資格を持っている場合：新規申請の対象外 -->
      <div v-if="eligibility.already_instructor" class="bg-blue-50 border border-blue-200 rounded-xl p-6">
        <p class="text-sm font-semibold text-blue-800 mb-1">既に指導士資格をお持ちです</p>
        <p class="text-sm text-blue-700">この画面は新規に指導士資格を申請する方向けのため、表示できません。</p>
      </div>

      <template v-else>

      <!-- (a)2年以上 (b)年会費 募集回 ステータス：横並び7列（各2列・2列・2列・1列） -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-3">

          <!-- (a) 入会から2年度以上継続（2列） -->
          <div class="md:col-span-2 relative border rounded-lg px-4 py-2.5" :class="tenureCondition?.met ? 'border-emerald-300' : 'border-gray-300'">
            <p class="text-sm text-gray-500 mb-1">(a) 入会から2年度以上継続</p>
            <p class="text-sm text-gray-700 mb-1.5">
              入会年度：<span class="font-semibold">{{ tenureCondition?.joined_fiscal_year ? `${tenureCondition.joined_fiscal_year}年度` : '不明' }}</span>
              <span v-if="tenureCondition?.years_since_joined !== null" class="text-gray-400">（{{ tenureCondition.years_since_joined }}年度経過）</span>
            </p>
            <span :class="badgeClasses(tenureCondition?.met ? 'met' : 'reject')">
              <CheckCircle2 v-if="tenureCondition?.met" class="w-3.5 h-3.5" />
              <AlertCircle v-else class="w-3.5 h-3.5" />
              {{ tenureCondition?.met ? '要件を満たしています' : '2年度未満' }}
            </span>
          </div>

          <!-- (b) 年会費の完納（2列） -->
          <div class="md:col-span-2 relative border rounded-lg px-4 py-2.5" :class="feeCondition?.met ? 'border-emerald-300' : 'border-gray-300'">
            <p class="text-sm text-gray-500 mb-1">(b) 年会費の完納</p>
            <span :class="badgeClasses(feeCondition?.met ? 'met' : 'pending')">
              <CheckCircle2 v-if="feeCondition?.met" class="w-3.5 h-3.5" />
              <Clock v-else class="w-3.5 h-3.5" />
              年会費：{{ feeCondition?.met ? '完納' : '未納あり' }}
            </span>
            <button type="button" class="block text-xs text-blue-600 hover:underline mt-1.5" @click="showFeeDialog = true">
              年会費納入状況を見る
            </button>
          </div>

          <!-- 募集回＋申請受付期間（2列） -->
          <div class="md:col-span-2 border border-gray-300 rounded-lg px-4 py-2.5">
            <p class="flex items-center gap-1.5 text-sm text-gray-500 mb-1">
              <Calendar class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" /> 募集回
            </p>
            <p class="text-sm font-semibold text-gray-700">
              <template v-if="currentCycle">第{{ currentCycle.exam_round }}回</template>
              <template v-else>募集なし</template>
            </p>
            <p v-if="currentCycle" class="text-base font-bold text-orange-500 mt-1">
              受付：{{ dayjs(currentCycle.application_start).format('YYYY-MM-DD') }}
              〜{{ currentCycle.application_end ? dayjs(currentCycle.application_end).format('YYYY-MM-DD') : '未定' }}
            </p>
          </div>

          <!-- ステータス（1列） -->
          <div class="md:col-span-1 border border-gray-300 rounded-lg px-4 py-2.5">
            <p class="flex items-center gap-1.5 text-sm text-gray-500 mb-1">ステータス</p>
            <span v-if="exam" :class="[badgeClasses(examStatusTone), 'text-sm px-2.5 py-1']">
              {{ examStatusLabel }}
            </span>
            <span v-else class="text-sm text-gray-400">未申請</span>
          </div>
        </div>

        <p v-if="eligibility.is_withdrawn" class="flex items-center gap-1.5 text-sm text-red-600 font-semibold mt-3">
          <AlertCircle class="w-3.5 h-3.5 shrink-0" /> 退会済みのため申請できません
        </p>
      </div>

      <!-- 申請条件（①）を満たさない場合は、④申請フェーズ以下を丸ごと非表示にする -->
      <template v-if="exam && eligibility.all_ok">

      <!-- ④ 申請フェーズ ステッパー -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <p class="text-sm font-semibold text-gray-500 mb-5 text-center">④ 申請フェーズ（現在の進捗状況）</p>
        <div class="flex items-center">
          <template v-for="(phase, idx) in phases" :key="phase">
            <div class="flex flex-col items-center flex-1">
              <div :class="phaseCircleClass(idx)">
                <CheckCircle2 v-if="idx < currentPhaseIndex" class="w-4 h-4" />
                <span v-else>{{ idx + 1 }}</span>
              </div>
              <span :class="['text-sm mt-2 text-center whitespace-nowrap', idx === currentPhaseIndex ? 'font-bold text-gray-900' : 'text-gray-500']">
                {{ phase }}
              </span>
            </div>
            <div v-if="idx < phases.length - 1" class="flex-1 h-px bg-gray-200 -mt-6"></div>
          </template>
        </div>
      </div>

      <!-- ⑤ (c)(d) 提出要件 ＋ 受験申込ボタン、横並び -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <p class="text-sm font-semibold text-gray-500 mb-3">⑤ 提出要件・受験申込</p>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

          <!-- (c) 主演者・座長経験の有無（クリックで即保存） -->
          <div class="lg:col-span-2 border border-gray-200 rounded-xl p-4">
            <p class="font-semibold text-gray-900 text-base mb-1">(c) 主演者・座長経験</p>
            <p class="text-sm text-gray-500 mb-3">本学会学術集会での主演者・座長経験（ランチョンセミナー等も含む）はありますか？</p>
            <div class="grid grid-cols-2 gap-2">
              <button type="button"
                class="rounded-lg px-3 py-2 text-sm font-semibold border transition"
                :class="exam.route === 'A' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50'"
                @click="setRoute('A')">
                はい
              </button>
              <button type="button"
                class="rounded-lg px-3 py-2 text-sm font-semibold border transition"
                :class="exam.route === 'B' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50'"
                @click="setRoute('B')">
                いいえ
              </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">
              {{ exam.route === 'A' ? '様式2・様式3-1を提出します' : '様式3-2・自験例報告書を提出します' }}
            </p>
          </div>

          <!-- (d) ルートAの証明書類 / ルートBの自験例報告書 -->
          <div class="lg:col-span-2 relative border border-emerald-300 rounded-xl p-4">
            <div v-if="checklist.documentsAndCases" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center shadow">
              <Check class="w-3.5 h-3.5 text-white" stroke-width="3" />
            </div>
            <template v-if="exam.route === 'A'">
              <p class="font-semibold text-gray-900 text-base mb-1 pr-4">(d) 証明書類の提出（様式2・様式3-1）</p>
              <p class="text-sm text-gray-500 mb-3">主演者・座長経験の証明書と推薦状を提出してください。</p>
            </template>
            <template v-else>
              <p class="font-semibold text-gray-900 text-base mb-1 pr-4">(d) 自験例報告書の提出（様式3-2・様式4・様式5）</p>
              <p class="text-sm text-gray-500 mb-3">推薦状と、10症例のうち2症例を選んだ詳細報告を提出してください。</p>
            </template>
            <div class="flex items-center gap-3">
              <div class="flex items-baseline gap-0.5 flex-shrink-0">
                <span class="text-lg font-bold text-blue-600">{{ completedDocCount }}</span>
                <span class="text-sm text-gray-500">/ {{ requiredDocCount }} 件</span>
              </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">
              主演者・座長経験が「あり」の場合は自験例報告書は不要です。
            </p>
          </div>

          <!-- 受験申込ボタン -->
          <div class="lg:col-span-1 border border-gray-200 rounded-xl p-4 flex flex-col justify-center">
            <Button
              v-if="!submitBadgeState"
              :disabled="missing.length > 0"
              :class="[
                'w-full whitespace-nowrap',
                missing.length === 0
                  ? 'bg-blue-600 hover:bg-blue-700 text-white'
                  : 'text-gray-400 bg-gray-100 border border-gray-200'
              ]"
              @click="submitApplication"
            >
              受験申込を行う
            </Button>
            <p v-if="!submitBadgeState && missing.length > 0" class="text-xs text-red-500 mt-1">
              {{ missing.join('、') }}が未完了です
            </p>
            <span v-else-if="submitBadgeState" :class="badgeClasses(submitBadgeState.tone)">
              <CheckCircle2 v-if="submitBadgeState.tone === 'met'" class="w-3.5 h-3.5" />
              <Clock v-else class="w-3.5 h-3.5" />
              {{ submitBadgeState.label }}
            </span>
          </div>
        </div>
      </div>

      <!-- 添付書類 -->
      <div class="flex items-center gap-3">
        <h2 class="text-lg font-extrabold text-[#1e3a6e] italic">添付書類</h2>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-0 overflow-hidden">
        <table class="w-full border-collapse text-[13px]">
          <thead>
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">書類名</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">ステータス</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="doc in requiredDocuments" :key="doc.type">
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">
                <div class="flex items-center gap-2">
                  <FileText class="w-4 h-4 text-gray-400 flex-shrink-0" />
                  <p class="font-medium">{{ doc.label }}</p>
                </div>
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">
                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
                  :class="documentByType(doc.type) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-50 text-gray-500 border-gray-300'">
                  {{ documentByType(doc.type) ? '提出済み' : '未提出' }}
                </span>
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">
                <div class="flex items-center gap-3">
                  <a v-if="documentByType(doc.type)" :href="`/exam-documents/${documentByType(doc.type).id}/view`" target="_blank"
                    class="text-xs text-gray-500 hover:text-sky-700 transition flex items-center gap-1">
                    <FileText class="w-4 h-4" /> 確認
                  </a>
                  <button type="button" class="text-xs text-indigo-600 hover:text-indigo-700 transition flex items-center gap-1"
                    @click="openUploadDialog(doc.type, doc.label)">
                    <Upload class="w-4 h-4" /> {{ documentByType(doc.type) ? '差し替え' : 'アップロード' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 自験例報告書（ルートBのみ） -->
      <template v-if="exam.route === 'B'">
        <div class="flex items-center gap-3">
          <h2 class="text-lg font-extrabold text-[#1e3a6e] italic">自験例報告書（様式4）10症例</h2>
          <span :class="badgeClasses(detailedCount === 2 ? 'met' : 'pending')">
            様式5対象：{{ detailedCount }} / 2 症例選択中
          </span>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
            <div v-for="cr in exam.case_reports" :key="cr.id"
              class="relative border rounded-lg p-3 text-left"
              :class="cr.is_detailed ? 'border-purple-300 bg-purple-50/40' : 'border-gray-200'">
              <div v-if="isCaseReportFilled(cr)" class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center shadow">
                <Check class="w-3 h-3 text-white" stroke-width="3" />
              </div>
              <button type="button" class="w-full text-left" @click="openCaseReportDialog(cr)">
                <div class="flex items-center gap-1">
                  <span class="text-sm font-semibold">No.{{ cr.case_no }}</span>
                  <span v-if="cr.is_detailed" class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-600 text-white font-semibold shrink-0">様式5</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">{{ isCaseReportFilled(cr) ? '入力済み' : '未入力' }}</p>
              </button>
              <label class="flex items-center gap-1.5 mt-2 text-[11px] cursor-pointer"
                :class="cr.is_detailed ? 'text-purple-700 font-semibold' : 'text-gray-400'">
                <input type="checkbox" :checked="cr.is_detailed"
                  :disabled="!cr.is_detailed && detailedCount >= 2"
                  @change="toggleDetailed(cr)" />
                様式5にする
              </label>
            </div>
          </div>
          <p class="text-xs text-gray-400 mt-3">10症例のうち2症例を選んで、様式5（詳細）を記載してください。</p>
        </div>
      </template>

      </template>

      <!-- 過去の申請履歴 -->
      <template v-if="pastExams.length > 0">
        <div class="flex items-center gap-3">
          <h2 class="text-lg font-extrabold text-[#1e3a6e] italic">過去の申請履歴</h2>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-0 overflow-hidden">
          <table class="w-full border-collapse text-[13px]">
            <thead>
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">回</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">申請日</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">受験資格</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">試験結果</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="app in pastExams" :key="app.id">
                <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">第{{ app.exam_round }}回</td>
                <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">{{ app.applied_at ? dayjs(app.applied_at).format('YYYY-MM-DD') : '-' }}</td>
                <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">{{ statusLabels[app.status] }}</td>
                <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">{{ examResultLabels[app.exam_result] }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      </template>

    </div>

    <!-- 年会費納入状況ダイアログ -->
    <Dialog :open="showFeeDialog" @update:open="showFeeDialog = false">
      <DialogContent class="sm:max-w-lg bg-white">
        <DialogHeader>
          <DialogTitle>年会費詳細</DialogTitle>
        </DialogHeader>

        <table class="w-full text-base">
          <thead>
            <tr class="border-b text-gray-500">
              <th class="py-2 text-left">年度</th>
              <th class="py-2 text-right">年会費</th>
              <th class="py-2 text-right">納入額</th>
              <th class="py-2 text-center">状態</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="fee in fees" :key="fee.id" class="border-b">
              <td class="py-2">{{ fee.fiscal_year }}年度</td>
              <td class="py-2 text-right">{{ fee.annual_fee.toLocaleString() }}円</td>
              <td class="py-2 text-right">{{ fee.payment_amount.toLocaleString() }}円</td>
              <td class="py-2 text-center">
                <span :class="[
                  'px-2 py-0.5 rounded-full text-sm font-medium',
                  fee.status === 'paid' ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-500'
                ]">
                  {{ fee.status === 'paid' ? '納入済' : '未納' }}
                </span>
              </td>
            </tr>
            <tr v-if="!fees || fees.length === 0">
              <td colspan="4" class="py-6 text-center text-gray-400">年会費の請求履歴がありません</td>
            </tr>
          </tbody>
        </table>

        <DialogFooter>
          <Button variant="outline" size="sm" @click="showFeeDialog = false">閉じる</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 書類アップロード ダイアログ（共有・使い回し） -->
    <Dialog :open="uploadDialogOpen" @update:open="uploadDialogOpen = false">
      <DialogContent class="sm:max-w-lg bg-white">
        <DialogHeader>
          <DialogTitle>{{ uploadTarget.label }}</DialogTitle>
          <DialogDescription>PDFファイルをドラッグ＆ドロップ、またはクリックして選択してください。</DialogDescription>
        </DialogHeader>

        <div
          class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition"
          @dragover.prevent
          @drop.prevent="onFileDrop"
          @click="fileInput?.click()"
        >
          <FileUp class="w-8 h-8 text-gray-400 mx-auto mb-2" />
          <p class="text-base text-gray-500">ファイルをドラッグ＆ドロップ、またはクリックして選択</p>
          <p class="text-sm text-gray-400 mt-1">PDF・JPEG・PNG（最大 10MB）</p>
          <p v-if="selectedFile" class="text-sm text-indigo-600 mt-2 font-medium">{{ selectedFile.name }}</p>
          <input type="file" accept="application/pdf,image/png,image/jpeg" class="hidden" ref="fileInput" @change="onFileChange" />
        </div>

        <DialogFooter>
          <SecondaryButton @click="uploadDialogOpen = false">キャンセル</SecondaryButton>
          <Button class="bg-indigo-600 hover:bg-indigo-700 text-white ms-3" :disabled="!selectedFile || uploading" @click="confirmUpload">
            <Save class="w-4 h-4 mr-2" /> アップロード
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 自験例報告書 編集ダイアログ（共有・使い回し） -->
    <Dialog :open="caseReportDialogOpen" @update:open="caseReportDialogOpen = false">
      <DialogContent class="sm:max-w-2xl bg-white max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle v-if="activeCaseReport">自験例 No.{{ activeCaseReport.case_no }}</DialogTitle>
        </DialogHeader>
        <div v-if="activeCaseReport">
          <CaseReportForm v-model="caseReportForm" :number="activeCaseReport.case_no" :is-detailed="activeCaseReport.is_detailed" />
        </div>
        <DialogFooter>
          <SecondaryButton @click="caseReportDialogOpen = false">キャンセル</SecondaryButton>
          <Button class="bg-indigo-600 hover:bg-indigo-700 text-white ms-3" @click="saveCaseReport">
            <Save class="w-4 h-4 mr-2" /> 保存する
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import CaseReportForm from '@/Components/Exams/CaseReportForm.vue'
import {
  Calendar, Clock, CheckCircle2, Check, AlertCircle, FileText, Upload, FileUp, Save, X,
} from 'lucide-vue-next'

const props = defineProps({
  member: Object,
  currentCycle: Object,
  eligibility: Object,
  exam: Object,
  pastExams: Array,
  fees: Array,
})

const exam = computed(() => props.exam)

const tenureCondition = computed(() => props.eligibility?.conditions?.find(c => c.key === 'tenure'))
const feeCondition = computed(() => props.eligibility?.conditions?.find(c => c.key === 'fee'))
const showFeeDialog = ref(false)

const statusLabels = {
  draft: '未申請', submitted: '提出済み（アサイン待ち）', pending: '書類審査中',
  approved: '受験資格あり', reject: '受験資格なし', certified: '指導士認定済み',
}
const examResultLabels = { pending: '未受験・未登録', pass: '合格', fail: '不合格' }

const examStatusLabel = computed(() => statusLabels[props.exam?.status] ?? '未申請')
const examStatusTone = computed(() => {
  switch (props.exam?.status) {
    case 'approved':
    case 'certified': return 'met'
    case 'reject': return 'reject'
    case 'pending':
    case 'submitted': return 'pending'
    default: return 'neutral'
  }
})

// ---- フェーズ ステッパー ----
const phases = ['申請', '書類審査', '委員長判定', '受験料納付', '試験結果', '指導士認定']
const squarePhaseIndexes = [1, 5]
const isSquarePhase = (idx) => squarePhaseIndexes.includes(idx)

const currentPhaseIndex = computed(() => {
  const app = props.exam
  if (!app) return 0
  if (app.status === 'certified') return 5
  if (app.exam_result === 'pass' || app.exam_result === 'fail') return 4
  if (app.status === 'approved') return 3
  if (app.status === 'reject') return 2
  if (app.status === 'pending') return 1
  return 0
})

const isRejectedPhase = computed(() => props.exam?.status === 'reject' || props.exam?.exam_result === 'fail')

const phaseCircleClass = (idx) => {
  const shape = isSquarePhase(idx) ? 'rounded-md' : 'rounded-full'
  const base = `w-9 h-9 ${shape} flex items-center justify-center text-base font-bold flex-shrink-0`
  if (idx < currentPhaseIndex.value) return `${base} bg-emerald-500 text-white`
  if (idx === currentPhaseIndex.value && isRejectedPhase.value) return `${base} bg-red-500 text-white`
  if (idx === currentPhaseIndex.value) return `${base} bg-blue-600 text-white`
  return `${base} bg-gray-300 text-white`
}

const badgeClasses = (tone) => [
  'inline-flex items-center gap-1 text-sm font-semibold px-2.5 py-1 rounded-full border whitespace-nowrap',
  {
    met:     'bg-emerald-50 text-emerald-700 border-emerald-200',
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    reject:  'bg-red-50 text-red-600 border-red-200',
    neutral: 'bg-gray-50 text-gray-400 border-gray-200',
  }[tone]
]

const showRejectNotice = ref(true)
const showFailNotice = ref(true)

// ---- ① 提出要件チェック ----
const isCaseReportFilled = (cr) => {
  const basicOk = cr.facility && cr.diagnosis
  const detailOk = !cr.is_detailed || (cr.patient_profile && cr.discussion)
  return basicOk && detailOk
}

const requiredDocuments = computed(() => {
  if (!props.exam) return []
  return props.exam.route === 'A'
    ? [
        { type: 'certificate', label: '様式2：主演者・座長経験証明書' },
        { type: 'recome1', label: '様式3-1：推薦状' },
      ]
    : [
        { type: 'recome2', label: '様式3-2：推薦状' },
      ]
})

const documentByType = (type) => props.exam?.documents?.find(d => d.type === type) ?? null

const requiredDocCount = computed(() => {
  if (!props.exam) return 0
  return props.exam.route === 'A' ? 2 : 11 // 様式3-2 + 症例10件
})

const completedDocCount = computed(() => {
  if (!props.exam) return 0
  const types = (props.exam.documents ?? []).map(d => d.type)
  if (props.exam.route === 'A') {
    return (types.includes('certificate') ? 1 : 0) + (types.includes('recome1') ? 1 : 0)
  }
  const recomeDone = types.includes('recome2') ? 1 : 0
  const casesDone = (props.exam.case_reports ?? []).filter(isCaseReportFilled).length
  return recomeDone + casesDone
})

const checklist = computed(() => {
  const app = props.exam
  if (!app) return { documentsAndCases: false }
  const documentsAndCases = completedDocCount.value >= requiredDocCount.value
  return { documentsAndCases }
})

const missing = computed(() => {
  const m = []
  if (!checklist.value.documentsAndCases) {
    m.push(props.exam?.route === 'A' ? '証明書類' : '自験例報告書')
  }
  if (props.exam?.route === 'B') {
    const detailedOk = (props.exam.case_reports ?? []).filter(cr => cr.is_detailed).length === 2
    if (!detailedOk) m.push('様式5対象2症例の選択')
  }
  return m
})

const submitBadgeState = computed(() => {
  switch (props.exam?.status) {
    case 'submitted': return { label: '提出済み（アサイン待ち）', tone: 'met' }
    case 'pending':   return { label: '審査中', tone: 'met' }
    case 'approved':  return { label: '受験資格あり', tone: 'met' }
    case 'reject':    return { label: '受験資格なし', tone: 'reject' }
    case 'certified': return { label: '指導士認定済み', tone: 'met' }
    default:          return null // draft → ボタン表示
  }
})

const setRoute = (routeValue) => {
  router.post(route('exams.updateRoute', props.exam.id), { route: routeValue }, { preserveScroll: true })
}

// ---- 添付書類ダイアログ ----
const uploadDialogOpen = ref(false)
const uploadTarget = reactive({ type: '', label: '' })
const selectedFile = ref(null)
const uploading = ref(false)
const fileInput = ref(null)

const openUploadDialog = (type, label) => {
  uploadTarget.type = type
  uploadTarget.label = label
  selectedFile.value = null
  uploadDialogOpen.value = true
}

const onFileChange = (e) => {
  selectedFile.value = e.target.files[0] ?? null
}

const onFileDrop = (e) => {
  selectedFile.value = e.dataTransfer.files[0] ?? null
}

const confirmUpload = () => {
  if (!selectedFile.value) return
  uploading.value = true
  router.post(route('exams.uploadDocument', props.exam.id), {
    type: uploadTarget.type,
    file: selectedFile.value,
  }, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => { uploading.value = false },
    onSuccess: () => { uploadDialogOpen.value = false },
  })
}

// ---- 自験例報告書 ----
const detailedCount = computed(() => (props.exam?.case_reports ?? []).filter(cr => cr.is_detailed).length)

const toggleDetailed = (cr) => {
  router.post(route('exams.toggleDetailed', [props.exam.id, cr.id]), {}, {
    preserveScroll: true,
    onError: (errors) => { alert(errors.message ?? '選択できませんでした。') },
  })
}

const caseReportDialogOpen = ref(false)
const activeCaseReport = ref(null)
const caseReportForm = reactive({
  facility: '', age: '', gender: '', visit_type: '', diagnosis: '',
  current_history: '', past_history: '', rehab: '', future_plan: '',
  patient_profile: '', examination_findings: '', rehab_and_patient_education: '', discussion: '',
})

const openCaseReportDialog = (cr) => {
  activeCaseReport.value = cr
  Object.assign(caseReportForm, {
    facility: cr.facility ?? '', age: cr.age ?? '', gender: cr.gender ?? '', visit_type: cr.visit_type ?? '',
    diagnosis: cr.diagnosis ?? '', current_history: cr.current_history ?? '', past_history: cr.past_history ?? '',
    rehab: cr.rehab ?? '', future_plan: cr.future_plan ?? '',
    patient_profile: cr.patient_profile ?? '', examination_findings: cr.examination_findings ?? '',
    rehab_and_patient_education: cr.rehab_and_patient_education ?? '', discussion: cr.discussion ?? '',
  })
  caseReportDialogOpen.value = true
}

const saveCaseReport = () => {
  router.put(route('exams.updateCaseReport', [props.exam.id, activeCaseReport.value.id]), caseReportForm, {
    preserveScroll: true,
    onSuccess: () => { caseReportDialogOpen.value = false },
  })
}

// ---- 提出 ----
const submitApplication = () => {
  if (!confirm('受験申込を提出します。よろしいですか？')) return
  router.post(route('exams.submit', props.exam.id))
}
</script>

<style scoped>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>
