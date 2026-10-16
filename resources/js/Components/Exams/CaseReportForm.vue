<template>
  <div class="border rounded-lg p-4 space-y-3">
    <div class="flex items-center justify-between">
      <p class="font-semibold text-sm">
        自験例 No.{{ number }}
        <span v-if="isDetailed" class="ml-2 text-xs px-1.5 py-0.5 rounded-full bg-purple-50 text-purple-700">
          様式5対象（詳細も入力してください）
        </span>
      </p>
    </div>

    <div>
      <InputLabel value="施設名" />
      <TextInput v-model="form.facility" class="w-full" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div>
        <InputLabel value="年齢" />
        <TextInput v-model="form.age" type="number" class="w-full" />
      </div>
      <div>
        <InputLabel value="性別" />
        <select v-model="form.gender" class="input-field">
          <option value="male">男性</option>
          <option value="female">女性</option>
          <option value="other">その他</option>
        </select>
      </div>
      <div>
        <InputLabel value="外来／入院" />
        <select v-model="form.visit_type" class="input-field">
          <option value="outpatient">外来</option>
          <option value="inpatient">入院</option>
        </select>
      </div>
    </div>

    <div>
      <InputLabel value="診断名" />
      <TextInput v-model="form.diagnosis" class="w-full" />
    </div>
    <div>
      <InputLabel value="現病歴：治療内容・経過" />
      <textarea v-model="form.current_history" rows="2" class="input-field" />
    </div>
    <div>
      <InputLabel value="既往歴" />
      <textarea v-model="form.past_history" rows="2" class="input-field" />
    </div>
    <div>
      <InputLabel value="リハビリテーション：目標・実施プログラム" />
      <textarea v-model="form.rehab" rows="2" class="input-field" />
    </div>
    <div>
      <InputLabel value="今後の課題と方針" />
      <textarea v-model="form.future_plan" rows="2" class="input-field" />
    </div>

    <!-- 様式5：No.1・No.2のみ -->
    <div v-if="isDetailed" class="pt-3 mt-3 border-t space-y-3">
      <p class="text-xs font-semibold text-purple-700">様式5：詳細</p>
      <div>
        <InputLabel value="患者プロフィール：年齢・性別・体格等" />
        <textarea v-model="form.patient_profile" rows="2" class="input-field" />
      </div>
      <div>
        <InputLabel value="検査所見・評価" />
        <textarea v-model="form.examination_findings" rows="2" class="input-field" />
      </div>
      <div>
        <InputLabel value="リハビリテーション：目標・実施プログラム・患者教育等" />
        <textarea v-model="form.rehab_and_patient_education" rows="2" class="input-field" />
      </div>
      <div>
        <InputLabel value="考察：今後の課題と方針等" />
        <textarea v-model="form.discussion" rows="2" class="input-field" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
  modelValue: Object,
  number: Number,
  isDetailed: Boolean,
})

const emit = defineEmits(['update:modelValue'])

const form = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})
</script>
<style scoped>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>
