<!-- CommitteeForm.vue -->
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
    committee_name: '',
    start_date: '',
    end_date: '',
  })
}
</script>

<template>
  <div class="space-y-6">

    <div
      v-for="(committee, index) in form"
      :key="index"
      class="border rounded-lg p-4 space-y-4"
    >

      <div>
        <InputLabel value="委員名" />

        <TextInput
          v-model="committee.committee_name"
          class="w-full"
        />

        <InputError
          :message="errors?.[`committees.${index}.committee_name`]"
        />
      </div>

      <div>
        <InputLabel value="開始日" />

        <TextInput
          v-model="committee.start_date"
          type="date"
          class="w-full"
        />

        <InputError
          :message="errors?.[`committees.${index}.start_date`]"
        />
      </div>

      <div>
        <InputLabel value="終了日" />

        <TextInput
          v-model="committee.end_date"
          type="date"
          class="w-full"
        />

        <InputError
          :message="errors?.[`committees.${index}.end_date`]"
        />
      </div>

    </div>

    <button
      type="button"
      @click="addRow"
    >
      ＋ 委員追加
    </button>

  </div>
</template>