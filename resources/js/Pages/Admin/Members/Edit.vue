<template>
  <AppLayout>

    <template #header>
      {{ props.member ? t('members.edit') : t('members.create') }}
    </template>

    <form @submit.prevent="submitForm" class="w-full">

      <TabGroup
        v-model="activeTab"
        :tabs="tabs"
        v-slot="{ activeTab: currentTab }"
      >

        <!-- 基本情報 -->
        <div v-if="currentTab === 'basic'">
          <MemberForm
            v-model="form.member"
            :errors="form.errors"
          />
        </div>

        <!-- 自宅 -->
        <div v-if="currentTab === 'address'">
          <AddressForm
            v-model="form.home"
            :errors="form.errors"
            prefix="home"
          />
        </div>

        <!-- 所属先 -->
        <div v-if="currentTab === 'affiliation'">
          <AddressForm
            v-model="form.affiliation"
            :errors="form.errors"
            prefix="affiliation"
          />
        </div>

        <!-- 郵送先 -->
        <div v-if="currentTab === 'mailing'">
          <AddressForm
            v-model="form.mailing"
            :errors="form.errors"
            prefix="mailing"
          />
        </div>

        <!-- 学歴 -->
        <div v-if="currentTab === 'education'">
          <EducationForm
            v-model="form.educations"
            :errors="form.errors"
          />
        </div>

        <!-- 学位 -->
        <div v-if="currentTab === 'degree'">
          <DegreeForm
            v-model="form.degrees"
            :errors="form.errors"
          />
        </div>

        <!-- 資格 -->
        <div v-if="currentTab === 'certification'">
          <CertificationForm
            v-model="form.certifications"
            :errors="form.errors"
          />
        </div>

        <!-- 役職歴 -->
        <div v-if="currentTab === 'officer'">
          <OfficerForm
            v-model="form.officers"
            :errors="form.errors"
          />
        </div>

        <!-- 委員歴 -->
        <div v-if="currentTab === 'committee'">
          <CommitteeForm
            v-model="form.committees"
            :errors="form.errors"
          />
        </div>

        <!-- 会費 -->
        <div v-if="currentTab === 'payment'">
          <PaymentForm
            v-model="form.payments"
            :errors="form.errors"
          />
        </div>

        <!-- 備考 -->
        <div v-if="currentTab === 'remark'">
          <RemarkForm
            v-model="form.remarks"
            :errors="form.errors"
          />
        </div>

      </TabGroup>

      <!-- ボタン -->
      <div class="flex justify-between items-center mt-6">

        <SecondaryButton type="button">
          <Link :href="route('admin.members.index')">
            {{ t('members.cancel') }}
          </Link>
        </SecondaryButton>

        <PrimaryButton
          type="submit"
        >
          {{ props.member ? t('update') : t('create') }}
        </PrimaryButton>

      </div>

    </form>

  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

import { useI18n } from 'vue-i18n'

import AppLayout from '@/Layouts/Admin/AppLayout.vue'

import TabGroup from '@/Components/TabGroup.vue'

import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

import MemberForm from './Components/MemberForm.vue'
import AddressForm from './Components/AddressForm.vue'

import EducationForm from './Components/EducationForm.vue'
import DegreeForm from './Components/DegreeForm.vue'
import CertificationForm from './Components/CertificationForm.vue'
import OfficerForm from './Components/OfficerForm.vue'
import CommitteeForm from './Components/CommitteeForm.vue'
import PaymentForm from './Components/PaymentForm.vue'
import RemarkForm from './Components/RemarkForm.vue'

const { t } = useI18n()

const page = usePage()

const props = defineProps({
  member: Object,
})

const activeTab = ref('basic')

const tabs = [
  { key: 'basic', label: '基本情報' },
  { key: 'address', label: '自宅' },
  { key: 'affiliation', label: '所属先' },
  { key: 'mailing', label: '郵送先' },
  { key: 'education', label: '学歴' },
  { key: 'degree', label: '学位' },
  { key: 'certification', label: '資格' },
  { key: 'officer', label: '役職歴' },
  { key: 'committee', label: '委員歴' },
  { key: 'payment', label: '会費' },
  { key: 'remark', label: '備考' },
]

const form = useForm({

  member: {
    id: props.member?.id ?? null,

    last_name: props.member?.last_name ?? '',
    first_name: props.member?.first_name ?? '',

    last_name_kana: props.member?.last_name_kana ?? '',
    first_name_kana: props.member?.first_name_kana ?? '',

    gender: props.member?.gender ?? 'male',

    birthdate: props.member?.birthdate ?? '',

    email: props.member?.email ?? '',
    personal_email: props.member?.personal_email ?? '',

    member_status: props.member?.member_status ?? '',
    member_type: props.member?.member_type ?? '',

    join_date: props.member?.join_date ?? '',
    leave_date: props.member?.leave_date ?? '',

    postal_destination: props.member?.postal_destination ?? '',
  },

  home: {
    organization_name: '',

    faculty_name: '',
    department_name: '',

    postal_code: props.member?.home?.postal_code ?? '',

    prefecture: props.member?.home?.prefecture ?? '',

    address1: props.member?.home?.address1 ?? '',
    address2: props.member?.home?.address2 ?? '',
    address3: props.member?.home?.address3 ?? '',

    tel: props.member?.home?.tel ?? '',
    fax: props.member?.home?.fax ?? '',
    mobile: props.member?.home?.mobile ?? '',
  },

  affiliation: {
    organization_name: '',

    faculty_name: '',
    department_name: '',

    postal_code: props.member?.work?.postal_code ?? '',

    prefecture: props.member?.work?.prefecture ?? '',

    address1: props.member?.work?.address1 ?? '',
    address2: props.member?.work?.address2 ?? '',
    address3: props.member?.work?.address3 ?? '',

    tel: props.member?.work?.tel ?? '',
    fax: props.member?.work?.fax ?? '',
    mobile: props.member?.work?.mobile ?? '',
  },

  mailing: {
    organization_name: '',

    faculty_name: '',
    department_name: '',

    postal_code: props.member?.mail?.postal_code ?? '',

    prefecture: props.member?.mail?.prefecture ?? '',

    address1: props.member?.mail?.address1 ?? '',
    address2: props.member?.mail?.address2 ?? '',
    address3: props.member?.mail?.address3 ?? '',

    tel: props.member?.mail?.tel ?? '',
    fax: props.member?.mail?.fax ?? '',
    mobile: props.member?.mail?.mobile ?? '',
  },

  educations: props.member?.educations ?? [
    {
      school_name: '',
      faculty_name: '',
      department_name: '',
      graduated_at: '',
    }
  ],

  degrees: props.member?.degrees ?? [
    {
      degree_name: '',
      acquired_at: '',
    }
  ],

  certifications: props.member?.certifications ?? [
    {
      qualification_name: '',
      acquired_year: '',
      renewal_year: '',
    }
  ],

  officers: props.member?.officers ?? [
    {
      title: '',
      start_date: '',
      end_date: '',
    }
  ],

  committees: props.member?.committees ?? [
    {
      committee_name: '',
      start_date: '',
      end_date: '',
    }
  ],

  payments: props.member?.payments ?? [
    {
      payment_type: '',
      amount: '',
      memo: '',
    }
  ],

  remarks: props.member?.remarks ?? {
    memo: '',
    office_note: '',
  }

})

watch(
  () => page.props.errors,
  (e) => {
    console.log('Laravel errors:', e)
  },
  { deep: true }
)

const submitForm = () => {

  console.log(form)

  if (props.member?.id) {

    form.put(
      route('admin.members.update', props.member.id)
    )

  } else {

    form.post(
      route('admin.members.store')
    )

  }
}
</script>
<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>