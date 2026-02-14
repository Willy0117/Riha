<template>
  <AppLayout>
    <template #header>{{ t('members.edit') }}</template>

    <div class="max-w-5xl mx-auto bg-white p-8 rounded shadow">
      <h2 class="text-2xl font-bold mb-6">{{ t('registers.members') }}</h2>

      <form @submit.prevent="submitForm" class="space-y-8">
        <div class="mt-4">
          <InputLabel :value="t('registers.applicant')" class="mb-2" />

          <div class="flex flex-col sm:flex-row gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                value="corporation"
                v-model="form.type"
                class="text-blue-600 focus:ring-blue-500"
              />
              <span>{{ t('registers.corporation') }}</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                value="sole"
                v-model="form.type"
                class="text-blue-600 focus:ring-blue-500"
              />
              <span>{{ t('registers.sole') }}</span>
            </label>
          </div>

          <p v-if="errors.type" class="text-red-500 text-sm mt-1">
            {{ errors.business_type }}
          </p>
        </div>
        <!-- 2カラム -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!--  ここから会社情報　-->

          <!-- 左カラム：会社情報 -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold mb-2">{{ t('registers.organization') }}</h3>

            <div>
              <InputLabel :value="t('registers.company_kana')" />

              <TextInput
                v-model="companyKana"
                :class="{
                  'border-red-500': getError('company_kana'),
                  'border-gray-300': !getError('company_kana')
                }"
                class="w-full"
              />
            </div>
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
                    v-model="form.company_name"
                    :class="{
                      'border-red-500': getError('company_name'),
                      'border-gray-300': !getError('company_name')
                    }"
                    class="flex-1"
                    placeholder="〇〇商事"
                  />
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
              <InputLabel value="代表者名（フリガナ）" />
              <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                  <TextInput v-model="form.rep_last_kana"
                    :class="{
                      'border-red-500': getError('rep_last_kana'),
                      'border-gray-300': !getError('rep_last_kana')
                    }"
                   class="w-full" :placeholder="t('registers.last_name_kana')" />
                </div>  
                <div class="flex-1">
                  <TextInput v-model="form.rep_first_kana"
                    :class="{
                      'border-red-500': getError('rep_first_kana'),
                      'border-gray-300': !getError('rep_first_kana')
                    }"
                    class="w-full" :placeholder="t('registers.first_name_kana')" />
                </div>
              </div>

            </div>
            <div>
             <InputLabel value="代表者名" />
              <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                  <TextInput v-model="form.rep_last_name"
                    :class="{
                      'border-red-500': getError('rep_last_name'),
                      'border-gray-300': !getError('rep_last_name')
                    }"
                    class="w-full" :placeholder="t('registers.last_name')" />
                </div>
                <div class="flex-1">  
                  <TextInput v-model="form.rep_first_name"
                    :class="{
                      'border-red-500': getError('rep_first_name'),
                      'border-gray-300': !getError('rep_first_name')
                    }"
                    class="w-full" :placeholder="t('registers.first_name')" />
                </div>
              </div>
            </div>
          </div>
        </div>
<!--  ここまでが会社情報　-->
        <h3></h3>
        <div class="p-4 bg-blue-50 border-l-4 border-blue-400 rounded shadow-sm mb-4">
          <h3 class="text-lg font-semibold text-blue-800">{{ t('registers.corp') }}</h3>
        </div>        
        <div>
          <InputLabel :value="t('registers.zip_code')" />
          <TextInput
            v-model="form.corp.postal_code"
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
          <InputError :message="errors?.corp?.postal_code" />

          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
              <InputLabel :value="t('registers.address1')" />
              <TextInput v-model="form.corp.address1"
                :class="{
                  'border-red-500': getError('corp.address1'),
                  'border-gray-300': !getError('corp.address1')
                }"
                class="w-full"
              />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address2')" />
              <TextInput v-model="form.corp.address2"
                :class="{
                  'border-red-500': getError('corp.address2'),
                  'border-gray-300': !getError('corp.address2')
                }"
                class="w-full" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address3')" />
              <TextInput v-model="form.corp.address3" class="w-full" />
            </div>
          </div>
          <div class="mb-4 grid grid-cols-4 gap-x-1 gap-y-3 items-start">
            <div>
              <InputLabel :value="t('registers.tel')" />
              <TextInput
                v-model="form.corp.tel"
                :class="{
                  'border-red-500': getError('corp.tel'),
                  'border-gray-300': !getError('corp.tel')
                }"
                maxlength="12"
                @input="e => onPhoneInput('corp', 'tel', e)"
                placeholder="03-1234-5678"
              />
              <p v-if="form.corp.tel && form.corp.tel.length !== 12"
                class="text-xs text-red-500 mt-1">
                電話番号は 03-1234-5678 の形式で入力してください
              </p>
            </div>

            <div>
              <InputLabel :value="t('registers.fax')" />
              <TextInput
                v-model="form.corp.fax"
                maxlength="12"
                @input="e => onPhoneInput('corp', 'fax', e)"
                placeholder="03-1234-5678"
              />
              <p v-if="form.corp.fax && form.corp.fax.length !== 12"
                class="text-xs text-red-500 mt-1">
                FAX番号は 03-1234-5678 の形式で入力してください
              </p>
            </div>
            <div>
              <InputLabel :value="t('registers.mobile')" />
              <TextInput
                v-model="form.corp.mobile"
                class="w-full"
                placeholder="090-xxxx-xxxx"
                maxlength="13"
                @input="e => onPhoneInput('corp', 'mobile', e)"
              />
              <p v-if="form.corp.mobile && form.corp.mobile.length !== 13"
                class="text-xs text-red-500 mt-1">
                携帯電話は 090-1234-5678 の形式で入力してください
              </p>
            </div>
            <div>
              <InputLabel>
                {{ t('registers.email') }}
                <span v-if="form.is_agent" class="text-red-500 ml-1">*</span>
              </InputLabel>

              <TextInput
                v-model="form.corp.email"
                class="w-full"
              />

              <p
                v-if="form.errors['corp.email']"
                class="text-red-500 text-sm mt-1"
              >
                {{ form.errors['corp.email'] }}
              </p>
            </div>
          </div>
        </div>
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
          <!-- 肩書き -->
          <div class="flex-1">
            <div>
              <InputLabel :value="t('registers.position')" />
              <TextInput v-model="form.corp.position" class="w-full" />
            </div>
            <p v-if="form.corp.mobile && form.corp.mobile.length !== 13"
                class="text-xs text-red-500 mt-1">
                携帯電話は 090-1234-5678 の形式で入力してください
            </p>
          </div>
          <!-- 氏名 -->
          <div class="flex-[2]">
            <div>
              <InputLabel :value="t('registers.staff')" />
              <div class="flex gap-2">
                <TextInput v-model="form.corp.last_name"
                  :class="{
                    'border-red-500': getError('corp.last_name'),
                    'border-gray-300': !getError('corp.last_name')
                    }"
                    class="w-full" />
                <TextInput v-model="form.corp.first_name"
                  :class="{
                    'border-red-500': getError('corp.first_name'),
                    'border-gray-300': !getError('corp.first_name')
                  }"
                  class="w-full" />
              </div>
            </div>
          </div>
        </div>
        <div class="p-4 bg-green-50 border-l-4 border-green-400 rounded shadow-sm mb-4">
          <h3 class="text-lg font-semibold text-blue-800">{{ t('registers.mail') }}</h3>
        </div>                
        <!--     -->                  
        <div class="mb-4">
          <label class="flex items-center gap-2">
            <input
              type="checkbox"
              v-model="form.same_as_corp"
              class="rounded border-gray-300 text-indigo-600 shadow-sm"
            />
            <span class="text-sm text-gray-700">
              郵送先は現住所と同じ
            </span>
          </label>
        </div>
        <!-- チェックが入ると入力不可 -->
        <!-- ここから郵送先-->         
       <div
          :class="{
            'pointer-events-none opacity-50': form.same_as_corp
          }"
        > 
          <div>
            <InputLabel :value="t('registers.zip_code')" />
            <TextInput
              v-model="form.mail.postal_code"
              placeholder="000-0000"
              maxlength="8"
              @input="onPostZipInput"
              @keydown.enter.prevent
            />
            <!-- 候補が2件以上ある場合は選択させる -->
            <ul v-if="candidates.length > 1" class="border rounded bg-white">
              <li
                v-for="candidate in candidates"
                :key="candidate.label"
                class="p-2 hover:bg-gray-100 cursor-pointer"
                @click="selectCandidate(candidate, 'mail.address1')"
              >
                {{ candidate.label }}
              </li>
            </ul>
            <InputError :message="errors.mail?.post_zip" />
          </div>
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
              <InputLabel :value="t('registers.address1')" />
              <TextInput v-model="form.mail.address1" class="w-full" placeholder="○○県△△市xx区" />
              <InputError :message="errors?.mail?.address1" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address2')" />
              <TextInput v-model="form.mail.address2" class="w-full" placeholder="○○丁目○○番地" />
              <InputError :message="errors?.mail?.address2" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address3')" />
              <TextInput v-model="form.mail.address3" class="w-full" placeholder="xxxビル○○F" />
              <InputError :message="errors?.mail?.address3" />
            </div>
          </div>            
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div>
              <InputLabel :value="t('registers.tel')" />
              <TextInput
                v-model="form.mail.tel"
                maxlength="12"
                @input="e => onPhoneInput('mail', 'tel', e)"
                placeholder="03-1234-5678"
              />
              <InputError :message="errors.tel" />
            </div>
            <p v-if="form.mail.tel && form.mail.tel.length !== 12"
              class="text-xs text-red-500 mt-1">
              電話番号は 03-1234-5678 の形式で入力してください
            </p>

            <div>
              <InputLabel :value="t('registers.fax')" />
              <TextInput
                v-model="form.mail.fax"
                maxlength="12"
                @input="e => onPhoneInput('mail', 'fax', e)"
                placeholder="03-1234-5678"
              />
              <InputError :message="errors?.mail?.fax" />
            </div>
            <p v-if="form.mail.fax && form.mail.fax.length !== 12"
              class="text-xs text-red-500 mt-1">
              FAX番号は 03-1234-5678 の形式で入力してください
            </p>
            <div>
              <InputLabel :value="t('registers.mobile')" />
              <TextInput
                v-model="form.mail.mobile"
                class="w-full"
                placeholder="090-xxxx-xxxx"
                maxlength="13"
                @input="e => onPhoneInput('mail', 'mobile', e)"
              />
              <InputError :message="errors?.mail?.mobile" />
            </div>
            <p v-if="form.mail.mobile && form.mail.mobile.length !== 13"
              class="text-xs text-red-500 mt-1">
              携帯電話は 090-1234-5678 の形式で入力してください
            </p>
          </div>            
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <!-- 肩書き -->
            <div class="flex-1">
              <div>
                <InputLabel :value="t('registers.position')" />
                <TextInput v-model="form.mail.position" class="w-full" />
              </div>
              <InputError :message="errors?.mail?.position" />
            </div>

            <!-- 氏名 -->
            <div class="flex-1">
              <div>
                <InputLabel :value="t('registers.staff')" />
                <div class="flex gap-2">
                  <TextInput v-model="form.mail.last_name" class="w-full" />
                  <TextInput v-model="form.mail.first_name" class="w-full" />
                </div>
              </div>
              <InputError :message="errors?.mail?.staff" />
            </div>
          </div>
        </div>
        <!-- ここから代理人-->
        <div v-if="form.is_agent">

        <div class="p-4 bg-orange-50 border-l-4 border-orange-400 rounded shadow-sm mb-4">
          <h3 class="text-lg font-semibold text-blue-800">{{ t('registers.agent') }}</h3>
        </div>                

        <div class="">
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
          <div class="flex-1">
            <InputLabel :value="t('registers.zip_code')" />
            <TextInput
              v-model="form.agent.postal_code"
              placeholder="000-0000"
              maxlength="8"
              @input="onAgentZipInput"
              @keydown.enter.prevent
            />
            <!-- 候補が2件以上ある場合は選択させる -->
            <ul v-if="candidates.length > 1" class="border rounded bg-white">
              <li
                v-for="candidate in candidates"
                :key="candidate.label"
                class="p-2 hover:bg-gray-100 cursor-pointer"
                @click="selectCandidate(candidate, 'agent.address1')"
              >
                {{ candidate.label }}
              </li>
            </ul>
          </div>
          <div class="flex-[2]">
               <!-- 会社名 -->
              <InputLabel :value="t('registers.company_name')" /> 
              <TextInput
                v-model="form.agent.company_name"
                :class="{
                  'border-red-500': getError('agent.company_name'),
                  'border-gray-300': !getError('agent.company_name')
                }"
                class="w-full"
                placeholder="〇〇商事"
              />
          </div>
          </div>
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
              <InputLabel :value="t('registers.address1')" />
              <TextInput v-model="form.agent.address1" class="w-full" placeholder="○○県△△市xx区" />
              <InputError :message="errors?.agent?.address1" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address2')" />
              <TextInput v-model="form.agent.address2" class="w-full" placeholder="○○丁目○○番地" />
              <InputError :message="errors?.agent?.address2" />
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.address3')" />
              <TextInput v-model="form.agent.address3" class="w-full" placeholder="xxxビル○○F" />
              <InputError :message="errors?.agent?.address3" />
            </div>
          </div>            
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1">
              <InputLabel :value="t('registers.tel')" />
              <TextInput
                v-model="form.agent.tel"
                maxlength="12"
                @input="e => onPhoneInput('agent', 'tel', e)"
                placeholder="03-1234-5678"
              />
              <InputError :message="errors.tel" />
              <p v-if="form.agent.tel && form.agent.tel.length !== 12"
                class="text-xs text-red-500 mt-1">
                電話番号は 03-1234-5678 の形式で入力してください
              </p>
            </div>

            <div class="flex-1">
              <InputLabel :value="t('registers.fax')" />
              <TextInput
                v-model="form.agent.fax"
                maxlength="12"
                @input="e => onPhoneInput('agent', 'fax', e)"
                placeholder="03-1234-5678"
              />
              <InputError :message="errors?.agent?.fax" />
              <p v-if="form.agent.fax && form.agent.fax.length !== 12"
                class="text-xs text-red-500 mt-1">
                FAX番号は 03-1234-5678 の形式で入力してください
              </p>
            </div>
            <div class="flex-1">
              <InputLabel :value="t('registers.mobile')" />
              <TextInput
                v-model="form.agent.mobile"
                class="w-full"
                placeholder="090-xxxx-xxxx"
                maxlength="13"
                @input="e => onPhoneInput('agent', 'mobile', e)"
              />
              <InputError :message="errors?.agent?.mobile" />
              <p v-if="form.agent.mobile && form.agent.mobile.length !== 13"
                class="text-xs text-red-500 mt-1">
                携帯電話は 090-1234-5678 の形式で入力してください
              </p>
            </div>
          </div>            
          <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
            <!-- 肩書き -->
            <div class="flex-1">
              <div>
                <InputLabel :value="t('registers.position')" />
                <TextInput v-model="form.agent.position" class="w-full" />
              </div>
              <InputError :message="errors?.agent?.position" />
            </div>

            <!-- 氏名 -->
            <div class="flex-1">
              <div>
                <InputLabel :value="t('registers.staff')" />
                <div class="flex gap-2">
                  <TextInput v-model="form.agent.last_name" class="w-full" />
                  <TextInput v-model="form.agent.first_name" class="w-full" />
                </div>
              </div>
              <InputError :message="errors?.agent?.staff" />
            </div>
          </div>
        </div>
      </div>
      <!-- ここまでが代理人-->
      <section class="bg-white rounded shadow p-4">
          <h2 class="font-bold mb-2">提出書類</h2>
          <div class="flex gap-4">
              <div v-for="d in form.documents" :key="d.type + d.path">
              <img
                  v-if="d.thumbnail_path"
                  :src="d.thumbnail_path"
                  class="w-24 cursor-pointer"
                  @click="openPdf(d.path)"
              />
              </div>
          </div>
      </section>  
        <!-- 右カラム -->
        <div class="6">
          <PrimaryButton
            type="button"
            class="ml-auto bg-blue-600 hover:bg-blue-700"
            @click="submitForm"
          >
          {{ t('update') }}
          </PrimaryButton>
        </div>
        <!--
        <PrimaryButton class="mt-6" type="submit">
          データ送信
        </PrimaryButton>

        <button
          type="button"
          class="bg-blue-600 text-white px-4 py-2 rounded"
          @click="submitPDF"
        >
          PDF作成
        </button>
        -->
      </form>

    </div>
    <div>
      <DialogModal
        :show="!!previewPdf"
        maxWidth="7xl"
        @close="previewPdf = null"
      >
        <template #title>
          PDF プレビュー
        </template>

        <template #content>
          <div class="w-[90vw] h-[80vh]">
            <iframe
              v-if="previewPdf"
              :src="previewPdf"
              class="w-full h-full border"
            />
          </div>
        </template>

        <template #footer>
          <SecondaryButton @click="previewPdf = null">
            閉じる
          </SecondaryButton>
        </template>
      </DialogModal>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch, nextTick, computed, toRef } from 'vue'
import { Link, router, useForm,usePage } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';

import AppLayout from '@/Layouts/Admin/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Autocomplete from '@/Components/Autocomplete.vue'
import RegisterStep from '@/Components/RegisterStep.vue'    
import axios from 'axios'
import { useZipcode } from '@/composables/useZipcode'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const page = usePage()

watch(
  () => page.props.errors,
  e => {
    console.log('Laravel errors:', e)
  },
  { deep: true }
)

console.log(page.props) // ← ここで form が見える

const form = useForm({
  type: page.props.form?.type ?? 'corporation',
  company_kana: page.props.form?.company_kana ?? '',
  rep_last_kana: page.props.form?.rep_last_kana ?? '',
  rep_first_kana: page.props.form?.rep_first_kana ?? '',
  company_type_prefix: page.props.form?.company_type_prefix ?? '',
  company_name: page.props.form?.company_name ?? '',
  company_type_suffix: page.props.form?.company_type_suffix ?? '',
  rep_last_name: page.props.form?.rep_last_name ?? '',
  rep_first_name: page.props.form?.rep_first_name ?? '',
  same_as_corp: Boolean(Number(page.props.form?.same_as_corp)),
  is_agent: page.props.form?.is_agent,

  corp: {
    type: 1,
    postal_code: page.props.form?.corp?.postal_code ?? '',
    address1: page.props.form?.corp?.address1 ?? '',
    address2: page.props.form?.corp?.address2 ?? '',
    address3: page.props.form?.corp?.address3 ?? '',
    tel: page.props.form?.corp?.tel ?? '',
    fax: page.props.form?.corp?.fax ?? '',
    mobile: page.props.form?.mail?.mobile ?? '',
    email: page.props.form?.corp?.email ?? '',
    position: page.props.form?.corp?.position ?? '',
    last_name: page.props.form?.corp?.last_name ?? '',
    first_name: page.props.form?.corp?.first_name ?? '',
  },

  mail: {
    type: 2,
    postal_code: page.props.form?.mail?.postal_code ?? '',
    address1: page.props.form?.mail?.address1 ?? '',
    address2: page.props.form?.mail?.address2 ?? '',
    address3: page.props.form?.mail?.address3 ?? '',
    tel: page.props.form?.mail?.tel ?? '',
    fax: page.props.form?.mail?.fax ?? '',
    mobile: page.props.form?.mail?.mobile ?? '',
    email: page.props.form?.mail?.email ?? '',
    position: page.props.form?.mail?.position ?? '',
    last_name: page.props.form?.mail?.last_name ?? '',
    first_name: page.props.form?.mail?.first_name ?? '',
  },

  agent: {
    type: 4,
    company_name: page.props.form?.agent?.company_name ?? '',
    postal_code: page.props.form?.agent?.postal_code ?? '',
    address1: page.props.form?.agent?.address1 ?? '',
    address2: page.props.form?.agent?.address2 ?? '',
    address3: page.props.form?.agent?.address3 ?? '',
    tel: page.props.form?.agent?.tel ?? '',
    fax: page.props.form?.agent?.fax ?? '',
    mobile: page.props.form?.agent?.mobile ?? '',
    position: page.props.form?.agent?.position ?? '',
    last_name: page.props.form?.agent?.last_name ?? '',
    first_name: page.props.form?.agent?.first_name ?? '',
  },
  documents: page.props.form?.documents,

});

// エラー
//const errors = page.props.errors || {}
const errors = ref({})

function getError(key) {
  // ① Laravel array
  if (errors.value?.[key]) {
    const v = errors.value[key]
    return Array.isArray(v) ? v[0] : v
  }

  // ② フロント独自ネスト
  const keys = key.split('.')
  const nestedError = keys.reduce(
    (acc, k) => (acc ? acc[k] : undefined),
    errors.value
  )
  if (nestedError) return nestedError

  // ③ Inertia props（string）
  const pe = page.props.errors?.[key]
  return Array.isArray(pe) ? pe[0] : pe
}



const validateRequired = () => {
  errors.value = {}

  const isYuucho = form.bank_code === '9900'

  // ===== フラット必須 =====
  const requiredFlat = [
    'company_kana',
    'rep_last_kana',
    'rep_first_kana',
    'company_name',
    'rep_last_name',
    'rep_first_name',
    'bank_type',
    'bank_name',
    'account_type',
    'account_no',
    'account_kana',
    'account_name',
  ]

  requiredFlat.forEach(key => {
    if (!form[key] || form[key].toString().trim() === '') {
      console.log(key)
      errors.value[key] = '必須項目です'
    }
  })

  // ===== corp 必須（address3/ fax / mobile/ email 除外）=====
  if (form.corp) {
    Object.entries(form.corp).forEach(([key, value]) => {
      // address3 / fax / mobile / email は除外
      if (['address3', 'fax', 'mobile', 'email'].includes(key)) return

      if (value === null || value === undefined || value === '') {
        // errors.value.corp を作る
        if (!errors.value.corp) errors.value.corp = {}
        errors.value.corp[key] = '必須項目です'
      }
    })
  }

  return Object.keys(errors.value).length === 0
}

// 送信処理
const submitForm = () => {
  if (!validateRequired()) return

  // そのまま form を送信
  form.post(route('members.register.complete', { token: page.props.token }), {
    preserveScroll: true,
    onError: (errors) => {
      console.log('Validation errors:', errors)
    },
    onSuccess: () => {
      console.log('Submission succeeded')
    }
  })
}
const previewPdf = ref(null)

const openPdf = (pdfPath) => {
  console.log('PDF PATH:', pdfPath)
  if (!pdfPath) return

  // 例：フルパス化
  previewPdf.value = pdfPath

  // 例：ここで loading true
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

const companyKana = computed({
  get: () => form.company_kana,
  set: (value) => {
    form.company_kana = normalizeKana(value)
  },
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
  form.corp.postal_code = normalizeZip(e.target.value)
}

const onPostZipInput = (e) => {
  form.mail.postal_code = normalizeZip(e.target.value)
}

const onAgentZipInput = (e) => {
  form.agent.postal_code = normalizeZip(e.target.value)
}

useZipcode(
  toRef(form.corp, 'postal_code'),
  toRef(form.corp, 'address1')
)

useZipcode(
  toRef(form.mail, 'postal_code'),
  toRef(form.mail, 'address1')
)

useZipcode(
  toRef(form.agent, 'postal_code'),
  toRef(form.agent, 'address1')
)

const companyTypes = [
  { label: 'なし', value: '', kana: '' },
  { label: '株式会社', value: '株式会社', kana: 'カ' },
  { label: '有限会社', value: '有限会社', kana: 'ユ' },
  { label: '合名会社', value: '合名会社', kana: 'メ' },
  { label: '合資会社', value: '合資会社', kana: 'シ' },
  { label: '合同会社', value: '合同会社', kana: 'ゴ' },
]

const buildAccountKana = () => {
  const base = form.company_name_kana ?? ''

  // prefix（前）
  if (form.company_type_prefix) {
    const type = companyTypes.find(
      t => t.value === form.company_type_prefix
    )
    return type?.kana
      ? `${type.kana})${base}`
      : base
  }

  // suffix（後ろ）
  if (form.company_type_suffix) {
    const type = companyTypes.find(
      t => t.value === form.company_type_suffix
    )
    return type?.kana
      ? `${base}(${type.kana}`
      : base
  }

  return base
}

watch(
  () => [
    form.company_type_prefix,
    form.company_type_suffix,
    form.company_name_kana,
  ],
  () => {
    form.account_kana = buildAccountKana()
  }
)
// 郵送先が同じならコピーする
watch(
  () => form.same_as_corp,
  (checked) => {
    if (!checked) return

    form.mail = {
      ...form.corp,
    }
  }
)
// 会社情報から口座名カナ、口座名を自動設定する
const getCompanyTypeKana = (prefix) => {
  const type = companyTypes.find(t => t.value === prefix)
  return type?.kana ?? ''
}
// prefix 用
const prefixKana = getCompanyTypeKana(form.company_type_prefix)

// suffix 用（同じ関数をそのまま使う）
const suffixKana = getCompanyTypeKana(form.company_type_suffix)

watch(
  [
    () => form.company_type_prefix,
    () => form.company_kana,
    () => form.company_name,
    () => form.company_type_suffix,
    () => form.corp.position,
    () => form.rep_last_name,
    () => form.rep_first_name,
  ],
  () => {
    // ===== 口座名義（フリガナ） =====
    // ===== 口座名義（フリガナ） =====
    const prefixKana = getCompanyTypeKana(form.company_type_prefix)
    const suffixKana = getCompanyTypeKana(form.company_type_suffix)

    if (prefixKana) {
      // 株式会社○○ → カ）○○ / 有限会社○○ → ユ）○○
      form.account_kana = `${prefixKana}）${form.company_kana ?? ''}`
    } else if (suffixKana) {
      // ○○株式会社 → ○○（カ / ○○有限会社 → ○○（ユ
      form.account_kana = `${form.company_kana ?? ''}（${suffixKana}`
    } else {
      form.account_kana = form.company_kana ?? ''
    }

    form.account_kana = form.account_kana.replace(/\s+/g, '')


    // ===== 口座名義（漢字） =====
    form.account_name = (
      `${form.company_type_prefix ?? ''}` +
      `${form.company_name ?? ''}` +
      `${form.company_type_suffix ?? ''}` + 
      `${form.corp.position ?? ''}` +
      `${form.rep_last_name ?? ''}` +
      `${form.rep_first_name ?? ''}`
    ).replace(/\s+/g, '')
  },
  { immediate: true }
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
// 会社情報の代表者名が入力されたら,corp.にも入れる
watch(
  () => [form.rep_last_name, form.rep_first_name],
  ([last, first]) => {
    if (last) {
      form.corp.last_name = last
    }
    if (first) {
      form.corp.first_name = first
    }
  },
  { immediate: true }
)


//銀行コードまたは支店コードを半角数字化
const toHalfWidthNumber = (value) => {
  if (!value) return ''
  return value
    .replace(/[０-９]/g, (s) =>
      String.fromCharCode(s.charCodeAt(0) - 0xFEE0)
    )
    .replace(/[^0-9]/g, '')
}

watch(() => form.bank_code, (val) => {
  form.bank_code = toHalfWidthNumber(val)
})

watch(() => form.branch_code, (val) => {
  form.branch_code = toHalfWidthNumber(val)
})


</script>
