<!-- DegreeForm.vue -->
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
    degree_name: '',
    acquired_at: '',
  })
}
</script>

<template>
  <div class="space-y-6">

    <div
      v-for="(degree, index) in form"
      :key="index"
      class="border rounded-lg p-4 space-y-4"
    >

      <div>
        <InputLabel value="学位名" />

        <TextInput
          v-model="degree.degree_name"
          class="w-full"
        />

        <InputError
          :message="errors?.[`degrees.${index}.degree_name`]"
        />
      </div>

      <div>
        <InputLabel value="取得年月" />

        <TextInput
          v-model="degree.acquired_at"
          type="month"
          class="w-full"
        />

        <InputError
          :message="errors?.[`degrees.${index}.acquired_at`]"
        />
      </div>

    </div>

    <button
      type="button"
      @click="addRow"
    >
      ＋ 学位追加
    </button>

  </div>
</template>