<template>
  <div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
          <InputLabel :value="t('registers.zip_code')" required />
          <TextInput
            v-model="local.postal_code"
            placeholder="000-0000"
            maxlength="8"
            @input="onAddressZipInput"
            @blur="form.clearErrors('local.postal_code')"
            @keydown.enter.prevent
          />
          <InputError :message="form.errors['local.postal_code']" />
        </div>

      <!-- 郵便番号 -->
      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">郵便番号</label>
        <input
          v-model="local.postal_code"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="000-0000"
        />
      </div>

      <!-- 都道府県 -->
      <div class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">都道府県</label>
        <input
          v-model="local.address1"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="東京都"
        />
      </div>

      <!-- 市区町村 -->
      <div class="col-span-2 space-y-1.5">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">市区町村</label>
        <input
          v-model="local.address2"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="千代田区"
        />
      </div>

      <!-- 番地・建物名 -->
      <div class="col-span-2 space-y-1.5">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">番地・建物名</label>
        <input
          v-model="local.address3"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="千代田1-1 ○○ビル101"
        />
      </div>

      <!-- 宛名（任意） -->
      <div v-if="showName" class="col-span-2 space-y-1.5">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">宛名</label>
        <input
          v-model="local.name"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="○○病院 経理部"
        />
      </div>

      <!-- 電話番号（任意） -->
      <div v-if="showTel" class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">電話番号</label>
        <input
          v-model="local.tel"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="03-0000-0000"
        />
      </div>

      <!-- FAX（任意） -->
      <div v-if="showFax" class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">FAX</label>
        <input
          v-model="local.fax"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="03-0000-0000"
        />
      </div>

      <!-- メール（任意） -->
      <div v-if="showEmail" class="col-span-2 space-y-1.5">
        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">メールアドレス</label>
        <input
          type="email"
          v-model="local.email"
          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="example@example.com"
        />
      </div>

    </div>
  </div>
</template>

<script setup>
import { reactive, watch, toRef, ref, onMounted, nextTick, computed } from 'vue'

import { useZipcode } from '@/composables/useZipcode'
import { useForm } from '@inertiajs/vue3'
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';


const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({
      name: '', postal_code: '', address1: '', address2: '', address3: '',
      tel: '', fax: '', email: '',
    }),
  },
  showTel:   { type: Boolean, default: false },
  showFax:   { type: Boolean, default: false },
  showEmail: { type: Boolean, default: false },
  showName:  { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const local = useForm({
  name: props.modelValue?.name ?? '',
  postal_code: props.modelValue?.postal_code ?? '',
  address1: props.modelValue?.address1 ?? '',
  address2: props.modelValue?.address2 ?? '',
  address3: props.modelValue?.address3 ?? '',
  tel: props.modelValue?.tel ?? '',
  fax: props.modelValue?.fax ?? '',
  email: props.modelValue?.email ?? '',
})

useZipcode(
  toRef(local, 'postal_code'),
  {
    prefecture: toRef(local, 'address1'),
    address1:   toRef(local, 'address2'),
    address2:   toRef(local, 'address3'),
  }
)

const normalizeZip = (value) => {
  if (!value) return ''

  // 全角数字 → 半角
  value = value.replace(/[０-９]/g, s =>
    String.fromCharCode(s.charCodeAt(0) - 0xFEE0)
  )

  // 全角ハイフン → 半角
  value = value.replace(/[ー－―]/g, '-')

  // 数字とハイフン以外を除去
  return value.replace(/[^0-9-]/g, '')
}

const onAddressZipInput = (e) => {
  local.postal_code = normalizeZip(e.target.value)
}


// 親に変更を通知
watch(local, (val) => {
  emit('update:modelValue', { ...val })
}, { deep: true })

// 親からの変更を反映
watch(() => props.modelValue, (val) => {
  Object.assign(local, val)
}, { deep: true })
</script>