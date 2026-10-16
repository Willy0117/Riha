<template>
  <AppLayout>
    <template #header>申請審査</template>

    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <Link :href="route('admin.examReviewer.index')" class="text-sm text-gray-500 hover:underline flex items-center gap-1">
        <ArrowLeft class="w-3.5 h-3.5" /> 一覧に戻る
      </Link>

      <div v-if="exam.reviewer_judgment === 're_review'" class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <p class="font-semibold mb-1">委員長から差し戻されています</p>
        <p class="whitespace-pre-wrap">{{ exam.chief_feedback }}</p>
      </div>

      <!-- 申請者情報 -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-lg font-bold text-gray-900">{{ exam.last_name }} {{ exam.first_name }}</h2>
        <p class="text-sm text-gray-500 mt-1">
          会員番号：{{ member?.code ?? exam.member_code_snapshot ?? '-' }}
          ／ ルート：<span class="font-semibold">{{ exam.route }}</span>
        </p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5 text-sm">
          <div><p class="text-gray-400 text-xs mb-0.5">職種</p><p>{{ exam.occupation ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">勤務先</p><p>{{ exam.workplace ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">所属部科名</p><p>{{ exam.department ?? '-' }}</p></div>
          <div><p class="text-gray-400 text-xs mb-0.5">役職名</p><p>{{ exam.position ?? '-' }}</p></div>
        </div>
      </div>

      <!-- 提出書類 -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-3">提出書類</h3>
        <div v-if="documents.length === 0" class="text-sm text-gray-400">提出書類はありません</div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <a v-for="doc in documents" :key="doc.id" :href="route('admin.examReviewer.view', doc.id)" target="_blank"
            class="border rounded-lg p-3 flex items-center gap-2 hover:bg-gray-50 text-sm">
            <FileText class="w-4 h-4 text-gray-400 shrink-0" />
            {{ doc.type_label }}
          </a>
        </div>
      </div>

      <!-- 自験例報告書 -->
      <div v-if="exam.route === 'B'" class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-900 mb-3">自験例報告書（{{ caseReports.length }}/10件）</h3>
        <div class="space-y-2">
          <div v-for="cr in caseReports" :key="cr.id" class="border rounded-lg"
            :class="cr.chief_flagged ? 'border-amber-400 ring-1 ring-amber-200' : ''">
            <button type="button" class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium text-left"
              @click="toggleCaseReport(cr.id)">
              <span>
                No.{{ cr.case_no }}
                <span v-if="cr.is_detailed" class="ml-2 text-xs px-1.5 py-0.5 rounded-full bg-purple-50 text-purple-700">様式5対象</span>
                <span v-if="cr.chief_flagged" class="ml-2 text-xs px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">委員長指摘</span>
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

      <!-- 判定 -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
        <h3 class="font-semibold text-gray-900">合否判定</h3>
        <p class="text-sm text-gray-500">全ての書類を確認したうえで、受験資格の有無を判定してください。</p>
        <Textarea v-model="judgeForm.message" placeholder="委員長への任意メッセージ（あれば）" rows="3" />
        <div class="flex gap-3">
          <Button type="button" class="bg-emerald-600 hover:bg-emerald-700" @click="submitJudge('pass')">受験資格あり</Button>
          <Button type="button" variant="destructive" @click="submitJudge('fail')">受験資格なし</Button>
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

const judgeForm = reactive({ message: '' })

const submitJudge = (judgment) => {
  const label = judgment === 'pass' ? '受験資格あり' : '受験資格なし'
  if (!confirm(`「${label}」として判定を確定します。よろしいですか？`)) return

  router.post(route('admin.examReviewer.judge', props.exam.id), {
    judgment,
    message: judgeForm.message,
  })
}
</script>
