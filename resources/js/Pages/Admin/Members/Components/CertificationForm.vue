<!-- CertificationForm.vue -->
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
    qualification_name: '',
    acquired_year: '',
    renewal_year: '',
  })
}
</script>

<template>
  <div class="space-y-6">

    <div
      v-for="(certification, index) in form"
      :key="index"
      class="border rounded-lg p-4 space-y-4"
    >

      <div>
        <InputLabel value="資格名" />

        <TextInput
          v-model="certification.qualification_name"
          class="w-full"
        />

        <InputError
          :message="errors?.[`certifications.${index}.qualification_name`]"
        />
      </div>

      <div>
        <InputLabel value="取得年" />

        <TextInput
          v-model="certification.acquired_year"
          class="w-full"
        />

        <InputError
          :message="errors?.[`certifications.${index}.acquired_year`]"
        />
      </div>

      <div>
        <InputLabel value="更新予定年" />

        <TextInput
          v-model="certification.renewal_year"
          class="w-full"
        />

        <InputError
          :message="errors?.[`certifications.${index}.renewal_year`]"
        />
      </div>

    </div>

    <button
      type="button"
      @click="addRow"
    >
      ＋ 資格追加
    </button>

  </div>
</template>