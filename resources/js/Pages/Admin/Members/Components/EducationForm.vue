<!-- EducationForm.vue -->
<script setup>
import { reactive } from 'vue'

import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  modelValue: Array,
  errors: Object,
})

const form = reactive(props.modelValue)

const addRow = () => {
  form.push({
    school_name: '',
    faculty_name: '',
    department_name: '',
    graduated_at: '',
  })
}

const removeRow = (index) => {
  form.splice(index, 1)
}
</script>

<template>
  <div class="space-y-6">

    <div
      v-for="(education, index) in form"
      :key="index"
      class="border rounded-lg p-4 space-y-4"
    >

      <div>
        <InputLabel value="学校名" />

        <TextInput
          v-model="education.school_name"
          class="w-full"
        />

        <InputError
          :message="errors?.[`educations.${index}.school_name`]"
        />
      </div>

      <div>
        <InputLabel value="学部・所属名" />

        <TextInput
          v-model="education.faculty_name"
          class="w-full"
        />

        <InputError
          :message="errors?.[`educations.${index}.faculty_name`]"
        />
      </div>

      <div>
        <InputLabel value="学科・部署名" />

        <TextInput
          v-model="education.department_name"
          class="w-full"
        />

        <InputError
          :message="errors?.[`educations.${index}.department_name`]"
        />
      </div>

      <div>
        <InputLabel value="卒業年月" />

        <TextInput
          v-model="education.graduated_at"
          type="month"
          class="w-full"
        />

        <InputError
          :message="errors?.[`educations.${index}.graduated_at`]"
        />
      </div>

      <button
        type="button"
        class="text-red-500"
        @click="removeRow(index)"
      >
        削除
      </button>

    </div>

    <button
      type="button"
      @click="addRow"
    >
      ＋ 学歴追加
    </button>

  </div>
</template>