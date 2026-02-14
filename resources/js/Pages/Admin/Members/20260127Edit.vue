<template>
  <AppLayout>
    <template #header>
      {{ t('members.edit') }}
    </template>

    <div class="space-y-6 p-6">

      <!-- 戻るボタン -->
      <div class="mb-4">
        <Link
          :href="route('admin.member.show', { member: props.member.id, ...persistQuery() })"
          class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded"
        >
          <ArrowLeftIcon class="w-4 h-4 mr-2" />
          {{ t('back') }}
        </Link>
      </div>

      <!-- 申請者 -->
      <section class="bg-white rounded shadow p-4">
        <h2 class="font-bold mb-2">{{ t('members.applicant') }}</h2>
        <div class="mb-4 flex gap-4">
          <div class="flex-1">
            <InputLabel :value="t('members.last_name')" />
            <TextInput v-model="form.last_name" class="w-full" />
            <InputError :message="errors.last_name" />
          </div>

          <div class="flex-1">
            <InputLabel :value="t('members.first_name')" />
            <TextInput v-model="form.first_name" class="w-full" />
            <InputError :message="errors.first_name" />
        </div>
        </div>
      </section>

      <!-- 法人情報 -->
      <section class="bg-white rounded shadow p-4">
        <h2 class="font-bold mb-2">{{ t('members.organization') }}</h2>
        <!-- 会社名フォーム -->
        <div class="mb-4 flex gap-2 items-end">
          <!-- prefix -->
          <div class="flex-1">
            <select
              v-model="form.company_type_prefix"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
            >
              <option v-for="opt in companyTypes" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <InputError :message="errors['organization.name_prefix']" />
          </div>
           <!-- name -->
          <div class="flex-[2]">
            <TextInput v-model="form.organization.name" class="w-full" placeholder="会社名" />
            <InputError :message="errors['organization.name']" />
          </div>

          <!-- suffix -->
          <div class="flex-1">
            <select
              v-model="form.company_type_suffix"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
            >
              <option v-for="opt in companyTypes" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <InputError :message="errors['organization.name_suffix']" />
          </div>
        </div>

        <div class="mb-4">
          <InputLabel :value="t('members.postal_code')" />
          <TextInput v-model="form.organization.postal_code" class="w-full" />
          <InputError :message="errors['organization.postal_code']" />
        </div>

        <div class="mb-4">
          <InputLabel :value="t('members.address')" />
          <div class="flex gap-2">
            <div class="flex-1">
              <TextInput v-model="form.organization.address1" class="w-full" placeholder="住所1" />
              <InputError :message="errors['organization.address1']" />
            </div>
            <div class="flex-1">
              <TextInput v-model="form.organization.address2" class="w-full" placeholder="住所2" />
              <InputError :message="errors['organization.address2']" />
            </div>
            <div class="flex-1">
              <TextInput v-model="form.organization.address3" class="w-full" placeholder="住所3" />
              <InputError :message="errors['organization.address3']" />
            </div>
          </div>
        </div>

        <!-- 電話・FAX・携帯 -->
        <div class="mb-4 flex gap-4">
          <div class="flex-1">
            <InputLabel :value="t('members.tel')" />
            <TextInput v-model="form.organization.tel" class="w-full" placeholder="電話" />
            <InputError :message="errors['organization.tel']" />
          </div>

          <div class="flex-1">
            <InputLabel :value="t('members.fax')" />
            <TextInput v-model="form.organization.fax" class="w-full" placeholder="FAX" />
            <InputError :message="errors['organization.fax']" />
          </div>

          <div class="flex-1">
            <InputLabel :value="t('members.mobile')" />
            <TextInput v-model="form.organization.mobile" class="w-full" placeholder="携帯" />
            <InputError :message="errors['organization.mobile']" />
          </div>
        </div>
       <div class="mb-4 flex gap-4">
          <div class="flex-1">
            <InputLabel :value="t('members.email')" />
            <TextInput v-model="form.organization.email" class="w-full" />
            <InputError :message="errors['organization.email']" />
          </div>

          <div class="flex-1">
            <InputLabel :value="t('members.contact_name')" />
            <TextInput v-model="form.organization.contact_name" class="w-full" />
            <InputError :message="errors['organization.contact_name']" />
          </div>
       </div>
      </section>

      <!-- 保存ボタン -->
      <div class="mt-6">
        <button
          type="submit"
          class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded"
          @click.prevent="submitForm"
        >
          {{ t('save') }}
        </button>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, watch, computed, toRef } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Autocomplete from '@/Components/Autocomplete.vue'    
import axios from 'axios'
import { useZipcode } from '@/composables/useZipcode'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'

const { t } = useI18n()

const props = defineProps({
  member: Object,
  filters: Object,
})

const persistQuery = () => {
  return { ...props.filters }
}
const page = usePage()
const errors = page.props.errors ?? {}

// フォーム初期値
const form = reactive({
  last_name: props.member.last_name,
  first_name: props.member.first_name,
  organization: {
    company_type_prefix:props.member.organization.prefix,
    company_type_suffix:props.member.organization.suffix,
    name: props.member.organization.name,
    postal_code: props.member.organization.postal_code,
    address1: props.member.organization.address1,
    address2: props.member.organization.address2,
    address3: props.member.organization.address3,
    tel: props.member.organization.tel,
    fax: props.member.organization.fax,
    mobile: props.member.organization.mobile,
    email: props.member.organization.email,
    contact_name: props.member.organization.contact_name,
  },
  company_type_prefix: props.member.organization?.prefix ?? '', // ←ここ
  company_type_suffix: props.member.organization?.suffix ?? '', // ←ここ
})

console.log(props.member)

// 送信
const submit = () => {
  router.put(
    route('admin.member.update', { member: props.member.id, ...persistQuery() }),
    form
  )
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

const onMobileInput = (e) => {
  form.mobile = normalizePhone(e.target.value)
}

const onTelInput = (e) => {
  form.tel = normalizePhone(e.target.value)
}

const onFaxInput = (e) => {
  form.fax = normalizePhone(e.target.value)
}


const normalizeFurigana = (value) => {
  if (!value) return ''

  // ひらがな → カタカナ
  value = value.replace(/[\u3041-\u3096]/g, s =>
    String.fromCharCode(s.charCodeAt(0) + 0x60)
  )

  // 全角カタカナ・長音・全角スペースのみ
  return value.replace(/[^\u30A0-\u30FFー　]/g, '')
}

const companyFurigana = computed({
  get: () => form.company_furigana,
  set: (value) => {
    form.company_furigana = normalizeFurigana(value)
  },
})
const companyTypes = [
  { label: 'なし', value: '' },
  { label: '株式会社', value: '株式会社' },
  { label: '有限会社', value: '有限会社' },
  { label: '合同会社', value: '合同会社' },
]

watch(() => form.company_type_prefix, () => {
  if (form.company_type_prefix) {
    form.company_type_suffix = ''
  }
})

watch(() => form.company_type_suffix, () => {
  if (form.company_type_suffix) {
    form.company_type_prefix = ''
  }
})

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
  form.address_zip = normalizeZip(e.target.value)
}

const onPostZipInput = (e) => {
  form.post_zip = normalizeZip(e.target.value)
}

useZipcode(
  toRef(form, 'post_zip'),
  toRef(form, 'post_address1')
)

useZipcode(
  toRef(form, 'address_zip'),
  toRef(form, 'address1')
)

</script>
