<script setup>
import { ref, onMounted, watch, nextTick, computed, toRef, reactive } from 'vue'
import { Link, router, useForm,usePage } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';

import axios from 'axios'
import { useZipcode } from '@/composables/useZipcode'

import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  modelValue: Object,
  errors: Object,
  type: String,
  prefix: String,
})

const emit = defineEmits(['update:modelValue'])

const defaults = {
  postal_code: '',
  prefecture: '',
  address1: '',
  address2: '',
  tel: '',
  mobile: '',
  fax: ''
}

const form = reactive({
  ...defaults,
  ...props.modelValue,
})

watch(form, () => {
  emit('update:modelValue', form)
}, { deep: true })

const normalizePhone = (value) => {
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

/**
 */
const onPhoneInput = (field, e) => {
  form[field] = normalizePhone(e.target.value)
}


const normalizeKana = (value) => {
  if (!value) return ''

  // ひらがな → カタカナ
  value = value.replace(/[\u3041-\u3096]/g, s =>
    String.fromCharCode(s.charCodeAt(0) + 0x60)
  )

  // 全角カタカナ・長音・全角スペースのみ
  return value.replace(/[^\u30A0-\u30FFー　]/g, '')
}

//〒番号関係
const candidates = ref([])
/*
 * 郵便番号正規化
 * ・全角数字 → 半角
 * ・全角ハイフン → 半角
 * ・数字とハイフン以外を除去
 */
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
/**
 * 住所候補選択
 * @param {Object} candidate
 * @param {String} field  formのキー名
 */
function selectCandidate(candidate, field) {
  if (!candidate || !field) return
  if (!(field in form)) return

  form[field] = candidate.label
  candidates.value = []
}

const onAddressZipInput = (e) => {
  form.postal_code = normalizeZip(e.target.value)
}

useZipcode(
  toRef(form, 'postal_code'),
  {
    prefecture: toRef(form, 'prefecture'),
    address1: toRef(form, 'address1'),
    address2: toRef(form, 'address2'),
  }
)



</script>

<template>
  <div class="space-y-8">
    <div>
      <h3 class="text-lg font-semibold mb-4">{{ type === 'home' ? '自宅' : '所属先' }}</h3>
      <div class="grid grid-cols-6 gap-4">

        <div class="col-span-2">
          <InputLabel value="郵便番号" />
          <TextInput v-model="form.postal_code" class="input-field" />
          <InputError :message="errors?.[`${prefix}.postal_code`]" />
        </div>

        <div class="col-span-2">
          <InputLabel value="都道府県" />
          <TextInput v-model="form.prefecture" class="input-field" />
          <InputError :message="errors?.[`${prefix}.prefecture`]" />
        </div>

        <div class="col-span-6">
          <InputLabel value="住所" />
          <TextInput v-model="form.address1" class="input-field" />
          <InputError :message="errors?.[`${prefix}.address1`]" />
        </div>

        <div class="col-span-6">
          <InputLabel value="建物名など" />
          <TextInput v-model="form.address2" class="input-field" />
          <InputError :message="errors?.[`${prefix}.address2`]" />
        </div>

        <div class="col-span-2">
          <InputLabel value="電話番号" />
          <TextInput v-model="form.tel" class="input-field" />
          <InputError :message="errors?.[`${prefix}.tel`]" />
        </div>

        <div class="col-span-2">
          <InputLabel value="携帯" />
          <TextInput v-model="form.mobile" class="input-field" />
          <InputError :message="errors?.[`${prefix}.mobile`]" />
        </div>

        <div class="col-span-2">
          <InputLabel value="FAX" />
          <TextInput v-model="form.fax" class="input-field" />
          <InputError :message="errors?.[`${prefix}.fax`]" />
        </div>

      </div>
    </div>


  </div>
</template>