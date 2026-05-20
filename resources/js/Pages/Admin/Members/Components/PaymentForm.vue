<!-- PaymentForm.vue -->
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
    payment_type: '',
    amount: '',
    quantity: 1,
    stop_from: '',
    stop_to: '',
    memo: '',
  })
}

const removeRow = (index) => {
  form.splice(index, 1)
}
</script>

<template>
  <div class="space-y-6">

    <div
      v-for="(payment, index) in form"
      :key="index"
      class="border rounded-lg p-4 space-y-4"
    >

      <!-- 種別 -->
      <div>
        <InputLabel value="種別" />

        <select
          v-model="payment.payment_type"
          class="w-full rounded border-gray-300"
        >
          <option value="">選択してください</option>
          <option value="annual_fee">会費</option>
          <option value="exam_fee">受験料</option>
          <option value="renewal_fee">更新料</option>
          <option value="seminar_fee">講習会参加費</option>
        </select>

        <InputError
          :message="errors?.[`payments.${index}.payment_type`]"
        />
      </div>

      <!-- 金額 -->
      <div>
        <InputLabel value="金額" />

        <TextInput
          v-model="payment.amount"
          type="number"
          class="w-full"
        />

        <InputError
          :message="errors?.[`payments.${index}.amount`]"
        />
      </div>

      <!-- 数量 -->
      <div>
        <InputLabel value="数量" />

        <TextInput
          v-model="payment.quantity"
          type="number"
          class="w-full"
        />

        <InputError
          :message="errors?.[`payments.${index}.quantity`]"
        />
      </div>

      <!-- 停止期間 -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
          <InputLabel value="発送停止開始日" />

          <TextInput
            v-model="payment.stop_from"
            type="date"
            class="w-full"
          />

          <InputError
            :message="errors?.[`payments.${index}.stop_from`]"
          />
        </div>

        <div>
          <InputLabel value="発送停止終了日" />

          <TextInput
            v-model="payment.stop_to"
            type="date"
            class="w-full"
          />

          <InputError
            :message="errors?.[`payments.${index}.stop_to`]"
          />
        </div>

      </div>

      <!-- メモ -->
      <div>
        <InputLabel value="備考" />

        <textarea
          v-model="payment.memo"
          rows="3"
          class="w-full rounded border-gray-300"
        />

        <InputError
          :message="errors?.[`payments.${index}.memo`]"
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
      ＋ 会費追加
    </button>

  </div>
</template>