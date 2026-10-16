<template>
  <AppLayout>
    <template #header>新規指導士申請 詳細</template>

    <div class="p-6 max-w-5xl mx-auto space-y-6">
      <Link :href="route('admin.examOffice.index', filters)" class="text-sm text-gray-500 hover:underline flex items-center gap-1">
        <ArrowLeft class="w-3.5 h-3.5" /> 一覧に戻る
      </Link>

      <!-- ステータス・基本情報 -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-bold text-gray-900">{{ exam.last_name }} {{ exam.first_name }}</h2>
            <p class="text-sm text-gray-500 mt-1">
              会員番号：{{ member?.code ?? exam.member_code_snapshot ?? '-' }}
              ／ 第{{ exam.exam_round }}回
              ／ ルート：<span class="font-semibold">{{ exam.route }}</span>（{{ exam.route_label }}）
            </p>
          </div>
          <span class="inline-block px-3 py-1.5 rounded-full text-sm font-semibold" :class="statusBadgeClass(exam.status)">
            {{ exam.status_label }}
          </span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5 text-sm">
          <div><p class="text-gray-400 text-xs mb-0.5">生年月日</p><p>{{ exam.birthdate ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">性別</p><p>{{ genderLabel(exam.gender) }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">職種</p><p>{{ exam.occupation ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">勤務先</p><p>{{ exam.workplace ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">所属部科名</p><p>{{ exam.department ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">役職名</p><p>{{ exam.position ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">メール</p><p>{{ exam.email ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">電話番号</p><p>{{ exam.tel ?? '-' }}</p></div>
        </div>
      </div>

      <!-- 審査履歴 -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
        <h3 class="font-semibold text-gray-900">審査状況</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-gray-400 text-xs mb-0.5">担当審査員</p>
            <p>{{ exam.reviewer_admin?.name ?? '未アサイン' }}</p>
          </div>
          <div>
            <p class="text-gray-400 text-xs mb-0.5">審査員判定</p>
            <p>{{ judgmentLabel(exam.reviewer_judgment) }}</p>
          </div>
        </div>
        <div v-if="exam.reviewer_response_message" class="bg-gray-50 rounded-lg p-3 text-sm">
          <p class="text-xs text-gray-400 mb-1">審査員→委員長メッセージ</p>
          {{ exam.reviewer_response_message }}
        </div>
        <div v-if="exam.chief_feedback" class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm">
          <p class="text-xs text-amber-600 mb-1">委員長からの差し戻し理由</p>
          {{ exam.chief_feedback }}
        </div>
        <div v-if="exam.reason" class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm">
          <p class="text-xs text-red-600 mb-1">不合格理由</p>
          {{ exam.reason }}
        </div>
      </div>

      <!-- 提出書類（証明書・推薦状） -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-3">提出書類</h3>
        <div v-if="documents.length === 0" class="text-sm text-gray-400">提出書類はありません</div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <a v-for="doc in documents" :key="doc.id" :href="route('admin.examOffice.view', doc.id)" target="_blank"
            class="border rounded-lg p-3 flex items-center gap-2 hover:bg-gray-50 text-sm">
            <FileText class="w-4 h-4 text-gray-400 shrink-0" />
            {{ doc.type_label }}
          </a>
        </div>
      </div>

      <!-- 自験例報告書（ルートBのみ） -->
      <div v-if="exam.route === 'B'" class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-3">自験例報告書（{{ caseReports.length }}/10件）</h3>
        <div class="space-y-2">
          <div v-for="cr in caseReports" :key="cr.id" class="border rounded-lg">
            <button type="button" class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium text-left"
              @click="toggleCaseReport(cr.id)">
              <span>
                No.{{ cr.case_no }}
                <span v-if="cr.is_detailed" class="ml-2 text-xs px-1.5 py-0.5 rounded-full bg-purple-50 text-purple-700">様式5対象</span>
              </span>
              <ChevronDown class="w-4 h-4 transition-transform" :class="openCaseReportIds.includes(cr.id) ? 'rotate-180' : ''" />
            </button>
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

      <!-- 試験結果登録（status='approved'かつ未登録のときのみ） -->
      <div v-if="exam.status === 'approved' && exam.exam_result === 'pending'"
        class="bg-blue-50 border border-blue-200 rounded-xl p-6 space-y-3">
        <h3 class="font-semibold text-blue-800">試験結果の登録</h3>
        <p class="text-sm text-blue-700">CBT試験本番（学会システム外で実施）の結果を登録してください。</p>
        <div class="flex gap-3">
          <Button type="button" class="bg-emerald-600 hover:bg-emerald-700" @click="submitExamResult('pass')">合格</Button>
          <Button type="button" variant="destructive" @click="submitExamResult('fail')">不合格</Button>
        </div>
      </div>
      <div v-else-if="exam.status === 'approved'" class="bg-white border border-gray-200 rounded-xl p-4 text-sm">
        <span class="text-gray-500">試験結果：</span>
        <span class="font-semibold" :class="exam.exam_result === 'pass' ? 'text-emerald-700' : 'text-red-700'">
          {{ exam.exam_result_label }}
        </span>
        <span class="text-xs text-gray-400 ml-2">
          （{{ dayjs(exam.exam_result_recorded_at).format('YYYY-MM-DD') }} 登録）
        </span>
      </div>

      <!-- 認定期間の設定（受験資格あり・試験合格の両方を満たすときのみ） -->
      <div v-if="exam.status === 'approved' && exam.exam_result === 'pass'"
        class="bg-emerald-50 border border-emerald-200 rounded-xl p-6 space-y-3">
        <h3 class="font-semibold text-emerald-800">新規認定期間の設定</h3>
        <p class="text-sm text-emerald-700">
          第{{ exam.exam_round }}回の認定期間で、指導士として登録します。受験料の入金確認が済んでいることを確認してください。
        </p>
        <div v-if="exam.instructor_cycle" class="bg-white rounded-lg border border-emerald-200 px-4 py-3 text-sm">
          <span class="text-gray-500">認定期間：</span>
          <span class="font-semibold text-gray-900">
            {{ dayjs(exam.instructor_cycle.start_date).format('YYYY-MM-DD') }}
            〜
            {{ dayjs(exam.instructor_cycle.end_date).format('YYYY-MM-DD') }}
          </span>
          <span class="text-xs text-gray-400 ml-2">（第{{ exam.exam_round }}回 合格者共通）</span>
        </div>
        <div v-else class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
          第{{ exam.exam_round }}回の認定期間が instructor_cycles に未登録です。先に「指導士回数マスター」画面で登録してください。
        </div>
        <div v-if="existingCycle" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">
          この会員には既に指導士サイクルが存在します。重複登録になっていないかご確認ください。
        </div>
        <Button type="button" :disabled="!exam.instructor_cycle || certifying" @click="submitCertify">
          この期間で認定期間を設定する
        </Button>
      </div>

      <div v-else-if="exam.status === 'certified'" class="bg-teal-50 border border-teal-200 rounded-xl p-6 text-sm text-teal-800">
        {{ dayjs(exam.certified_at).format('YYYY-MM-DD') }} に認定期間を設定済みです。
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { ArrowLeft, FileText, ChevronDown } from 'lucide-vue-next'

const props = defineProps({
  exam: Object,
  member: Object,
  documents: Array,
  caseReports: Array,
  existingCycle: Boolean,
  filters: Object,
})

const openCaseReportIds = ref([])
const toggleCaseReport = (id) => {
  const idx = openCaseReportIds.value.indexOf(id)
  if (idx >= 0) openCaseReportIds.value.splice(idx, 1)
  else openCaseReportIds.value.push(id)
}

const genderLabel = (g) => ({ male: '男性', female: '女性', other: 'その他' }[g] ?? '-')
const judgmentLabel = (j) => ({
  unreviewed: '未審査', pass: '合格', fail: '不合格', re_review: '再審査中',
}[j] ?? j)

const statusBadgeClass = (status) => {
  switch (status) {
    case 'draft':     return 'bg-gray-100 text-gray-600'
    case 'submitted': return 'bg-amber-50 text-amber-700'
    case 'pending':   return 'bg-blue-50 text-blue-700'
    case 'approved':  return 'bg-emerald-50 text-emerald-700'
    case 'reject':    return 'bg-red-50 text-red-700'
    case 'certified': return 'bg-teal-100 text-teal-800'
    default:          return 'bg-gray-100 text-gray-600'
  }
}

const certifying = ref(false)

const submitExamResult = (result) => {
  const label = result === 'pass' ? '合格' : '不合格'
  if (!confirm(`試験結果を「${label}」として登録します。よろしいですか？`)) return

  router.post(route('admin.examOffice.recordExamResult', props.exam.id), {
    exam_result: result,
  })
}

const submitCertify = () => {
  if (!confirm(`第${props.exam.exam_round}回の認定期間で、指導士として登録します。よろしいですか？`)) return

  certifying.value = true
  router.post(route('admin.examOffice.certify', props.exam.id), {}, {
    onFinish: () => { certifying.value = false },
  })
}
</script>
