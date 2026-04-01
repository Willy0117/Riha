<template>
  <AppLayout>
    <template #header>
      {{ organization ? t('edit_organization') : t('create_organization') }}
    </template>

    <div class="p-6 bg-white rounded shadow">
      <form @submit.prevent="submitForm" class="space-y-8">
        <!-- 2カラム -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!--  ここから会社情報　-->

          <!-- 左カラム：会社情報 -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold mb-2">{{ t('registers.organization') }}</h3>

              <div>
              <InputLabel :value="t('registers.company_name')" /> 

              <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                  <!-- 前 -->
                  <select
                    v-model="form.company_type_prefix"
                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
                  >
                    <option v-for="type in companyTypes" :key="type.value" :value="type.value">
                      {{ type.label }}
                    </option>
                  </select>

                </div>
                <div class="flex-1">
                  <!-- 会社名 -->
                  <TextInput
                    v-model="form.name"
                    class="flex-1"
                    placeholder="〇〇商事"
                  />
                  <InputError :message="form.errors.name" class="mt-2" />
                </div>
                <div class="flex-1">
                <!-- 後 -->
                  <select
                    v-model="form.company_type_suffix"
                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
                  >
                    <option v-for="type in companyTypes" :key="type.value" :value="type.value">
                      {{ type.label }}
                    </option>
                  </select>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                例）株式会社〇〇商事 ／ 〇〇商事株式会社
              </p>
            </div>  
          </div>

         <!-- 右カラム：代表者/担当者 -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold mb-2">代表者</h3>
            <div>
             <InputLabel value="代表者名" />
              <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                  <TextInput v-model="form.rep_last_name"
                    class="w-full" :placeholder="t('registers.last_name')" />
                    <InputError :message="form.errors.rep_last_name" class="mt-2" />
                </div>

                <div class="flex-1">  
                  <TextInput v-model="form.rep_first_name"
                    class="w-full" :placeholder="t('registers.first_name')" />
                    <InputError :message="form.errors.rep_first_name" class="mt-2" />
                </div>
              </div>
            </div>
          </div>
        </div>
        <div>
          <InputLabel :value="t('registers.zip_code')" />
          <TextInput
            v-model="form.postal_code"
            placeholder="000-0000"
            maxlength="8"
            @input="onAddressZipInput"
            @keydown.enter.prevent
          />
          <InputError :message="form.errors.postal_code" class="mt-2" />

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
          <InputError :message="form.errors.postal_code" class="mt-2" />

          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
              <InputLabel :value="t('registers.address1')" />
              <TextInput v-model="form.address1"
                class="w-full"
              />
              <InputError :message="form.errors.address1" class="mt-2" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address2')" />
              <TextInput v-model="form.address2"
                class="w-full" />
              <InputError :message="form.errors.address2" class="mt-2" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address3')" />
              <TextInput v-model="form.address3" class="w-full" />
            </div>
          </div>

          <div class="mb-4 grid grid-cols-4 gap-x-1 gap-y-3 items-start">
            <div class="w-full">
              <InputLabel :value="t('registers.tel')" />
              <TextInput
                v-model="form.tel"
                maxlength="12"
                @input="e => onPhoneInput('corp', 'tel', e)"
                placeholder="03-1234-5678"
              />
              <p v-if="form.tel && form.tel.length !== 12"
                class="text-xs text-red-500 mt-1">
                電話番号は 03-1234-5678 の形式で入力してください
              </p>
            </div>

            <div class="w-full">
              <InputLabel :value="t('registers.fax')" />
              <TextInput
                v-model="form.fax"
                maxlength="12"
                @input="e => onPhoneInput('corp', 'fax', e)"
                placeholder="03-1234-5678"
              />
              <p v-if="form.fax && form.fax.length !== 12"
                class="text-xs text-red-500 mt-1">
                FAX番号は 03-1234-5678 の形式で入力してください
              </p>
            </div>
            <div class="w-full">
              <InputLabel :value="t('registers.mobile')" />
              <TextInput
                v-model="form.mobile"
                class="w-full"
                placeholder="090-xxxx-xxxx"
                maxlength="13"
                @input="e => onPhoneInput('corp', 'mobile', e)"
              />
              <p v-if="form.mobile && form.mobile.length !== 13"
                class="text-xs text-red-500 mt-1">
                携帯電話は 090-1234-5678 の形式で入力してください
              </p>
            </div>
            <div class="w-full">
              <InputLabel>
                {{ t('registers.email') }}
                <span v-if="form.is_agent" class="text-red-500 ml-1">*</span>
              </InputLabel>

              <TextInput
                v-model="form.email"
                class="w-full"
              />

              <p
                v-if="form.errors.email"
                class="text-red-500 text-sm mt-1"
              >
                {{ form.errors.email }}
              </p>
            </div>
          </div>
        </div>
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
          <!-- 肩書き -->
          <div class="flex-1">
            <div>
              <InputLabel :value="t('registers.position')" />
              <TextInput v-model="form.position" class="w-full" />
            </div>
            <p v-if="form.mobile && form.mobile.length !== 13"
                class="text-xs text-red-500 mt-1">
                携帯電話は 090-1234-5678 の形式で入力してください
            </p>
          </div>
          <!-- 氏名 -->
          <div class="flex-[2]">
            <div>
              <InputLabel :value="t('registers.staff')" />
              <div class="flex gap-2">
                <TextInput v-model="form.last_name"
                    class="w-full" />
                <TextInput v-model="form.first_name"
                  class="w-full" />
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-end">
          <PrimaryButton :disabled="form.processing">
            {{ organization ? t('update') : t('create') }}
          </PrimaryButton>
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
import Autocomplete from '@/Components/Autocomplete.vue'
import axios from 'axios'
import { useZipcode } from '@/composables/useZipcode'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const page = usePage()

console.log(page.props) // ← ここで form が見える

const form = useForm({
  id:page.props.organization?.organization_id ?? '',
  company_type_prefix: page.props.organization?.name_prefix ?? '株式会社',
  name: page.props.organization?.name ?? '',
  company_type_suffix: page.props.organization?.name_suffix ?? '',
  rep_last_name: page.props.organization?.first_name ?? '',
  rep_first_name: page.props.organization?.last_name ?? '',
  postal_code: page.props.organization?.postal_code ?? '',
  address1: page.props.organization?.address1 ?? '',
  address2: page.props.organization?.address2 ?? '',
  address3: page.props.organization?.address3 ?? '',
  tel: page.props.organization?.tel ?? '',
  fax: page.props.organization?.fax ?? '',
  mobile: page.props.organization?.mobile ?? '',
  email: page.props.organization?.email ?? '',
  position: page.props.organization?.position ?? '代表取締役',
  last_name: page.props.organization?.last_name ?? '',
  first_name: page.props.organization?.first_name ?? '',
});


// 送信処理
const submitForm = () => {
  console.log('送信データ:', form);
  if (form?.id) {
    form.put(route('admin.organizations.update', props.organization.id))
  } else {
    form.post(route('admin.organizations.store'))
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
 * @param {'corp'|'mail'} target
 * @param {'tel'|'fax'|'mobile'} field
 */
const onPhoneInput = (target, field, e) => {
  form[target][field] = normalizePhone(e.target.value)
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
  toRef(form, 'address1')
)

// prefixまたはsuffixが入ったらもう一方は消す
watch(
  () => form.company_type_prefix,
  (val) => {
    if (val) form.company_type_suffix = ''
  }
)

watch(
  () => form.company_type_suffix,
  (val) => {
    if (val) form.company_type_prefix = ''
  }
)


</script>

