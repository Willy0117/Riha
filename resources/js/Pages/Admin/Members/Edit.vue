<template>
  <AppLayout>
    <template #header>{{ t('members.edit') }}</template>
    <div class="max-w-5xl mx-auto bg-white p-8 rounded shadow">
      <form @submit.prevent="submitForm" class="space-y-8" @keydown.enter="focusNext">
        <!-- 2カラム -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- 左カラム：会社情報 -->
          <div class="space-y-2">
            <div class="sm:items-start">
              <InputLabel :value="t('registers.company_name')" /> 

              <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start">
                  <!-- 会社名 -->
                  <TextInput
                    v-model="form.company_name"
                    class="flex-1"
                    placeholder="〇〇株式会社"
                  />
                  <InputError :message="form.errors?.company_name" />
              </div>
            </div>

          </div>
         <!-- 右カラム：代表者/担当者 -->
        </div>
<!--  ここまでが会社情報　-->
        <div>
          <InputLabel :value="t('registers.zip_code')" />
          <TextInput
            v-model="form.postal_code"
            placeholder="000-0000"
            maxlength="8"
            @input="onAddressZipInput"
            @keydown.enter.prevent
          />

          <ul v-if="candidates.length > 1" class="border rounded bg-white">
            <li
              v-for="candidate in candidates"
              :key="candidate.label"
              class="p-2 hover:bg-gray-100 cursor-pointer"
              @click="selectCandidate(candidate, 'corp.address1')"
            >
              {{ candidate.label }}
            </li>
          </ul>
          <InputError :message="form.errors.postal_code" />
        </div>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start">
            <div class="flex-1">
              <InputLabel :value="t('registers.address1')" />
              <TextInput v-model="form.address1"
                class="w-full"
              />
              <InputError :message="form.errors.address1" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address2')" />
              <TextInput v-model="form.address2"
                class="w-full" />
              <InputError :message="form.errors.address2" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address3')" />
              <TextInput v-model="form.address3" class="w-full" />
            </div>
        </div>
        <div class="mb-4 grid grid-cols-4 gap-x-1 gap-y-3 sm:flex-row sm:items-start">
            <div>
              <InputLabel :value="t('registers.tel')" />
              <TextInput
                v-model="form.tel"
                maxlength="20"
                @input="e => onPhoneInput('corp', 'tel', e)"
                placeholder="03-1234-5678"
              />
              <InputError :message="form.errors.tel" />
              <p v-if="form.tel"
                class="text-xs text-gray-500 mt-1">
                電話番号は 03-1234-5678 の形式で入力してください
              </p>
            </div>

            <div>
              <InputLabel :value="t('registers.fax')" />
              <TextInput
                v-model="form.fax"
                maxlength="20"
                @input="e => onPhoneInput('corp', 'fax', e)"
                placeholder="03-1234-5678"
              />
              <p v-if="form.fax" class="text-xs text-gray-500 mt-1">
                FAX番号は 03-1234-5678 の形式で入力してください
              </p>
            </div>
            <div>
              <InputLabel :value="t('registers.mobile')" />
              <TextInput
                v-model="form.mobile"
                class="w-full"
                placeholder="090-xxxx-xxxx"
                maxlength="20"
                @input="e => onPhoneInput('corp', 'mobile', e)"
              />
              <p v-if="form.mobile"
                class="text-xs text-gray-500 mt-1">
                携帯電話は 090-1234-5678 の形式で入力してください
              </p>
            </div>
            <div v-if="form.is_agent">
              <InputLabel>
                {{ t('registers.email') }}
              </InputLabel>

              <TextInput v-model="form.email" class="w-full" />
              <InputError :value="form.errors.email" />
            </div>
        </div>
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start">
          <!-- 肩書き -->
          <div class="flex-1">
              <InputLabel :value="t('registers.position')" class="h-5" />
              <TextInput v-model="form.position" class="w-full" />
          </div>
          <!-- 氏名 -->
          <div class="flex-1">
              <InputLabel value="代表者名" class="h-5" />
              <TextInput v-model="form.last_name"
                    class="w-full" />
              <InputError :message="form.errors.last_name" />
          </div>          
          <div class="flex-1">
              <InputLabel value="　" class="h-5" />
              <TextInput v-model="form.first_name"
                  class="w-full" />
              <InputError :message="form.errors.first_name" />
          </div>
        </div>        

      <div class="6">
        <PrimaryButton
          type="button" class="ml-auto"
          @click="submitForm"
        >
        {{ t('save') }}
        </PrimaryButton>

        <SecondaryButton type="button">
            <Link :href="route('admin.members.index')">
            {{ t('members.cancel') }}
            </Link>
        </SecondaryButton>
      </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch, nextTick, computed, toRef } from 'vue'
import { Link, router, useForm,usePage } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';

import AppLayout from '@/Layouts/Admin/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Autocomplete from '@/Components/Autocomplete.vue'
import RegisterStep from '@/Components/RegisterStep.vue'    
import axios from 'axios'
import { useZipcode } from '@/composables/useZipcode'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const page = usePage()
const bankInput = ref(null)

watch(
  () => page.props.errors,
  e => {
    console.log('Laravel errors:', e)
  },
  { deep: true }
)

console.log(page.props) // ← ここで form が見える
const history_certificate_name =
  page.props.files?.history_certificate?.name ?? null

const mail_address_certificate_name =
  page.props.files?.mail_address_certificate?.name ?? null


const form = useForm({
  id: page.props.form?.id ?? '',
  company_name: page.props.form?.company_name ?? '',
  last_name: page.props.form?.last_name ?? '',
  first_name: page.props.form?.first_name ?? '',

  postal_code: page.props.form?.postal_code ?? '',
  address1: page.props.form?.address1 ?? '',
  address2: page.props.form?.address2 ?? '',
  address3: page.props.form?.address3 ?? '',
  tel: page.props.form?.tel ?? '',
  fax: page.props.form?.fax ?? '',
  mobile: page.props.form?.mail?.mobile ?? '',
  email: page.props.form?.email ?? '',
  position: page.props.form?.position ?? '代表取締役',

});

const errors = ref({})

// 送信処理
const submitForm = () => {
  console.log('送信データ:', form);
  if (form.id) {
    form.put(route('admin.members.update', form.id), {
      onError: (e) => console.log(e)
    })
  } else {
    form.post(route('admin.members.store'), {
      onError: (e) => console.log(e)
    })
  }
}

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
  form.corp.postal_code = normalizeZip(e.target.value)
}

const onPostZipInput = (e) => {
  form.mail.postal_code = normalizeZip(e.target.value)
}

const onAgentZipInput = (e) => {
  form.agent.postal_code = normalizeZip(e.target.value)
}

useZipcode(
  toRef(form, 'postal_code'),
  toRef(form, 'address1')
)

const focusNext = (e) => {

  if (e.isComposing) return

  const form = e.target.form
  const index = Array.prototype.indexOf.call(form, e.target)

  if (index > -1) {
    e.preventDefault()
    form.elements[index + 1]?.focus()
  }
}
</script>
