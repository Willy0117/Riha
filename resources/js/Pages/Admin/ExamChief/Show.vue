<template>
  <AppLayout>
    <template #header>委員長承認</template>

    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <Link :href="route('admin.examChief.index')" class="text-sm text-gray-500 hover:underline flex items-center gap-1">
        <ArrowLeft class="w-3.5 h-3.5" /> 一覧に戻る
      </Link>

      <!-- 申請者情報・審査員判定 -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-bold text-gray-900">{{ exam.last_name }} {{ exam.first_name }}</h2>
            <p class="text-sm text-gray-500 mt-1">
              会員番号：{{ member?.code ?? exam.member_code_snapshot ?? '-' }}
              ／ ルート：<span class="font-semibold">{{ exam.route }}</span>
              ／ 担当審査員：{{ exam.reviewer_admin?.name ?? '-' }}
            </p>
          </div>
          <span class="inline-block px-3 py-1.5 rounded-full text-sm font-semibold"
            :class="exam.reviewer_judgment === 'pass' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
            審査員判定：{{ exam.reviewer_judgment === 'pass' ? '合格相当' : '不合格相当' }}
          </span>
        </div>
        <div v-if="exam.reviewer_response_message" class="mt-4 bg-gray-50 rounded-lg p-3 text-sm">
          <p class="text-xs text-gray-400 mb-1">審査員からのメッセージ</p>
          {{ exam.reviewer_response_message }}
        </div>
      </div>

      <!-- 提出書類 -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-3">提出書類</h3>
        <div v-if="documents.length === 0" class="text-sm text-gray-400">提出書類はありません</div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <a v-for="doc in documents" :key="doc.id" :href="route('admin.examChief.view', doc.id)" target="_blank"
            class="border rounded-lg p-3 flex items-center gap-2 hover:bg-gray-50 text-sm">
            <FileText class="w-4 h-4 text-gray-400 shrink-0" />
            {{ doc.type_label }}
          </a>
        </div>
      </div>

      <!-- 自験例報告書（差し戻し対象をチェックできる） -->
      <div v-if="exam.route === 'B'" class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-1">自験例報告書（{{ caseReports.length }}/10件）</h3>
        <p class="text-xs text-gray-400 mb-3">差し戻したい症例があれば、チェックを付けてください。</p>
        <div class="space-y-2">
          <div v-for="cr in caseReports" :key="cr.id" class="border rounded-lg">
            <div class="w-full flex items-center gap-3 px-4 py-2.5">
              <input type="checkbox" :value="cr.id" v-model="flaggedIds" />
              <button type="button" class="flex-1 flex items-center justify-between text-sm font-medium text-left"
                @click="toggleCaseReport(cr.id)">
                <span>
                  No.{{ cr.case_no }}
                  <span v-if="cr.is_detailed" class="ml-2 text-xs px-1.5 py-0.5 rounded-full bg-purple-50 text-purple-700">様式5対象</span>
                </span>
                <ChevronDown class="w-4 h-4 transition-transform" :class="openCaseReportIds.includes(cr.id) ? 'rotate-180' : ''" />
              </button>
            </div>
            <div v-if="openCaseReportIds.includes(cr.id)" class="px-4 pb-4 space-y-2 text-sm border-t pt-3">
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div><p class="text-gray-400 text-xs">施設名</p><p>{{ cr.facility ?? '-' }}</p></div>
                <div><p class="text-gray-400 text-xs">年齢</p><p>{{ cr.age ?? '-' }}</p></div>
                <div><p class="text-gray-400 text-xs">性別</p><p>{{ genderLabel(cr.gender) }}</p></div>
                <div><p class="text-gray-400 text-xs">外来／入院</p><p>{{ cr.visit_type === 'outpatient' ? '外来' : cr.visit_type === 'inpatient' ? '入院' : '-' }}</p></div>
              </div>
              <div><p class="text-gray-400 text-xs">診断名</p><p class="whitespace-pre-wrap">{{ cr.diagnosis ?? '-' }}</p></div>
              <div><p class="text-gray-400 text-xs">現病歴（治療内容・経過）</p><p class="whitespace-pre-wrap">{{ cr.current_history ?? '-' }}</p></div>
              <div><p class="text-gray-400 text-xs">既往歴</p><p class="whitespace-pre-wrap">{{ cr.past_history ?? '-' }}</p></div>
              <div><p class="text-gray-400 text-xs">リハビリテーション：目標・実施プログラム</p><p class="whitespace-pre-wrap">{{ cr.rehab ?? '-' }}</p></div>
              <div><p class="text-gray-400 text-xs">今後の課題と方針</p><p class="whitespace-pre-wrap">{{ cr.future_plan ?? '-' }}</p></div>
              <template v-if="cr.is_detailed">
                <div class="pt-2 mt-2 border-t">
                  <p class="text-xs font-semibold text-purple-700 mb-2">様式5：詳細</p>
                  <div><p class="text-gray-400 text-xs">患者プロフィール</p><p class="whitespace-pre-wrap">{{ cr.patient_profile ?? '-' }}</p></div>
                  <div class="mt-2"><p class="text-gray-400 text-xs">検査所見・評価</p><p class="whitespace-pre-wrap">{{ cr.examination_findings ?? '-' }}</p></div>
                  <div class="mt-2"><p class="text-gray-400 text-xs">リハビリテーション：目標・実施プログラム・患者教育等</p><p class="whitespace-pre-wrap">{{ cr.rehab_and_patient_education ?? '-' }}</p></div>
                  <div class="mt-2"><p class="text-gray-400 text-xs">考察：今後の課題と方針等</p><p class="whitespace-pre-wrap">{{ cr.discussion ?? '-' }}</p></div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- 最終承認 / 差し戻し -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-900">最終判定</h3>

        <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
          「承認する」を押すと、審査員判定（{{ exam.reviewer_judgment === 'pass' ? '合格相当' : '不合格相当' }}）に応じて
          自動的に「{{ exam.reviewer_judgment === 'pass' ? '受験資格あり' : '受験資格なし' }}」が確定します。
        </div>
        <Button type="button" class="bg-emerald-600 hover:bg-emerald-700" @click="submitApprove">
          承認する
        </Button>

        <div class="pt-4 border-t space-y-2">
          <p class="text-sm font-medium text-gray-700">審査員に差し戻す場合</p>
          <Textarea v-model="sendBackForm.reason" placeholder="差し戻し理由（必須）" rows="3" />
          <Button type="button" variant="outline" class="border-amber-400 text-amber-700 hover:bg-amber-50"
            :disabled="!sendBackForm.reason || flaggedIds.length === 0" @click="submitSendBack">
            審査員に差し戻す（指摘{{ flaggedIds.length }}件）
          </Button>
          <p v-if="flaggedIds.length === 0" class="text-xs text-gray-400">差し戻すには、上の症例報告から指摘対象を1件以上選択してください。</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { ArrowLeft, FileText, ChevronDown } from 'lucide-vue-next'

const props = defineProps({
  exam: Object,
  member: Object,
  documents: Array,
  caseReports: Array,
})

const openCaseReportIds = ref([])
const toggleCaseReport = (id) => {
  const idx = openCaseReportIds.value.indexOf(id)
  if (idx >= 0) openCaseReportIds.value.splice(idx, 1)
  else openCaseReportIds.value.push(id)
}

const genderLabel = (g) => ({ male: '男性', female: '女性', other: 'その他' }[g] ?? '-')

const flaggedIds = ref([])
const sendBackForm = reactive({ reason: '' })

const submitApprove = () => {
  if (!confirm('この判定で承認します。よろしいですか？')) return
  router.post(route('admin.examChief.review', props.exam.id))
}

const submitSendBack = () => {
  if (!confirm(`${flaggedIds.value.length}件を指摘して、審査員に差し戻します。よろしいですか？`)) return
  router.post(route('admin.examChief.sendBackToReviewer', props.exam.id), {
    reason: sendBackForm.reason,
    flagged_case_report_ids: flaggedIds.value,
  })
}
</script>
