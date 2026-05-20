<!-- RemarkForm.vue -->
<script setup>
import { reactive } from 'vue'

import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  modelValue: Object,
  errors: Object,
})

const form = reactive(props.modelValue)
</script>

<template>
  <div class="space-y-6">

    <!-- 備考 -->
    <div>
      <InputLabel value="備考" />

      <textarea
        v-model="form.memo"
        rows="5"
        class="w-full rounded border-gray-300"
      />

      <InputError
        :message="errors?.['remarks.memo']"
      />
    </div>

    <!-- 事務局メモ -->
    <div>
      <InputLabel value="事務局メモ" />

      <textarea
        v-model="form.office_note"
        rows="5"
        class="w-full rounded border-gray-300"
      />

      <InputError
        :message="errors?.['remarks.office_note']"
      />
    </div>

  </div>
</template>