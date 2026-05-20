<!-- OfficerForm.vue -->
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
    title: '',
    start_date: '',
    end_date: '',
  })
}
</script>

<template>
  <div class="space-y-6">

    <div
      v-for="(officer, index) in form"
      :key="index"
      class="border rounded-lg p-4 space-y-4"
    >

      <div>
        <InputLabel value="役職名" />

        <TextInput
          v-model="officer.title"
          class="w-full"
        />

        <InputError
          :message="errors?.[`officers.${index}.title`]"
        />
      </div>

      <div>
        <InputLabel value="開始日" />

        <TextInput
          v-model="officer.start_date"
          type="date"
          class="w-full"
        />

        <InputError
          :message="errors?.[`officers.${index}.start_date`]"
        />
      </div>

      <div>
        <InputLabel value="終了日" />

        <TextInput
          v-model="officer.end_date"
          type="date"
          class="w-full"
        />

        <InputError
          :message="errors?.[`officers.${index}.end_date`]"
        />
      </div>

    </div>

    <button
      type="button"
      @click="addRow"
    >
      ＋ 役職追加
    </button>

  </div>
</template>