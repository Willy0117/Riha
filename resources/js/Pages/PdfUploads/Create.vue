<template>
  <AppLayout :title="$t('pdf_upload')">
    <template #header>{{ $t('pdf_upload') }}</template>

    <div class="mx-auto py-6 space-y-6">
        <div class="flex items-center gap-4 mb-4">

          <h2 class="text-lg font-semibold text-gray-800">
            {{ t('instructors.update') }}
          </h2>

          <div class="flex items-center gap-2 text-sm text-gray-400">

            <CalendarDays class="w-4 h-4" />

            <span>
              {{ props.cycle?.start_date }}
            </span>

            <span class="text-gray-300">—</span>

            <span>
              {{ props.cycle?.end_date }}
            </span>

          </div>

        </div>

      <div class="p-4 border rounded w-full">
        <form @submit.prevent="submit" class="space-y-4">
          <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
            <!-- 承認済 -->
            <div>
              <div class="text-xs text-gray-400">
                {{ t('instructors.approved') }}
              </div>
              <div class="text-3xl font-semibold text-green-600 mt-1">
                {{ props?.approvedTotal }}
              </div>
            </div>

            <!-- 申請中 -->
            <div>
              <div class="text-xs text-gray-400">
                {{ t('instructors.pending') }}
              </div>
              <div class="text-2xl font-semibold text-amber-500 mt-1">
                {{ props?.pendingTotal }}
              </div>
            </div>

  <!-- 合計（弱めで残すなら） -->
            <div>
              <div class="text-xs text-gray-400">
                {{ t('instructors.total') }}
              </div>
              <div class="text-2xl font-semibold text-gray-800 mt-1">
                {{ props?.total }}
              </div>
            </div>

            <!-- 参加回数（主役寄り） -->
            <div>
              <div class="text-xs text-gray-400">
                {{ t('instructors.conference_count') }}
              </div>
              <div class="text-3xl font-light text-gray-900 mt-1">
                {{ props?.conference_count }}
              </div>
            </div>

            <!-- 年会費 -->
            <div>
              <div class="text-xs text-gray-400">
                {{ t('instructors.isFeeOk') }}
              </div>
              <div
                class="text-3xl font-light mt-1"
                :class="props?.isFeeOk ? 'text-sky-600' : 'text-gray-300'"
              >
                {{ props?.isFeeOk ? '済' : '未' }}
              </div>
            </div>

          </div>
          <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <InputLabel :value="t('members.code')" />
                <TextInput v-model="form.code" type="text" class="input-field" />
                <InputError :message="form.errors?.code" />
              </div>
              <div class="sm:col-span-2">
                <InputLabel :value="t('name')" />
                <div class="grid grid-cols-2 gap-2 mt-1">
                  <div>
                    <TextInput v-model="form.last_name" class="input-field" />
                    <InputError :message="form.errors?.last_name" />
                  </div>
                  <div>
                    <TextInput v-model="form.first_name" class="input-field" />
                    <InputError :message="form.errors?.first_name" />
                  </div>
                </div>
              </div>
              <div>
                <InputLabel :value="t('instructors.code')" />
                <TextInput v-model="form.instructor_code" type="text" class="input-field" />
                <InputError :message="form.errors?.instructor_code" />
              </div>

              <div>
                <InputLabel :value="t('exams.email')" /> 
                <TextInput v-model="form.email" type="email" class="input-field" />
                <InputError :message="form.errors?.email" />
              </div>

            </div>
          </div>   
          <div class="flex justify-between items-center mt-4">

            <!-- キャンセル -->
            <SecondaryButton
              @click="updateStatus('no_update')"
            >
              {{ t('instructors.cancel') }}
            </SecondaryButton>

            <!-- 送信 -->
            <PrimaryButton
              v-if="props.isFeeOk"
              @click="updateStatus('pending')"
            >
              {{ t('instructors.send') }}
            </PrimaryButton>

          </div>
        </form>
      </div>

      <!-- アップロード一覧 -->
<div>
  <h2 class="text-xl font-bold text-gray-800 mb-4">
    {{ t('uploaded_files') }}
  </h2>

  <div
    v-if="props.uploads.length === 0"
    class="bg-gray-50 border border-dashed border-gray-300 rounded-2xl p-10 text-center text-gray-500"
  >
    {{ t('no_uploads') }}
  </div>

  <div
    v-else
    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-6"
  >

    <div
      v-for="upload in props.uploads"
      :key="upload.id"
      class="group flex flex-col gap-3 p-4"
    >

      <!-- サムネイル（軽く） -->
      <div
        v-if="upload.thumbnail_url"
        class="w-full h-28 bg-gray-50 rounded-lg border border-gray-100 overflow-hidden"
      >
        <img
          :src="upload.thumbnail_url"
          class="w-full h-full object-contain p-2"
        />
      </div>

      <div
        v-else
        class="w-full h-28 bg-gray-50 rounded-lg border border-dashed border-gray-200 flex items-center justify-center text-gray-300 text-xs"
      >
        {{ t('no_thumbnail') }}
      </div>

      <!-- タイトル -->
      <div class="text-sm text-gray-900 leading-snug">
        {{ upload.credit_conference_name }}
      </div>

      <!-- バッジ（Nuxt UI風：かなり薄く） -->
      <div class="flex gap-2 flex-wrap">

        <span class="text-xs text-sky-600 bg-sky-50 px-2 py-0.5 rounded">
          {{ upload.role_name }}
        </span>

        <span class="text-xs text-gray-600 bg-gray-100 px-2 py-0.5 rounded">
          {{ upload.credit_category_name }}
        </span>

      </div>

      <!-- 情報（フラット） -->
      <div class="text-xs text-gray-500">
        {{ upload.session || '-' }}
      </div>

      <div class="text-sm text-gray-900">
        <span class="text-gray-400 text-xs">
          {{ t('instructors.point') }}
        </span>
        <span class="font-medium text-sky-700 ml-1">
          {{ upload.points || '-' }}
        </span>
      </div>

      <!-- アクション（軽い1列） -->
      <div class="flex items-center justify-between mt-1">

        <!-- PDF -->
        <button
          @click="previewPdf = `/pdf-uploads/${upload.id}/view`"
          class="text-xs text-gray-500 hover:text-sky-700 transition flex items-center gap-1"
        >
          <FileText class="w-4 h-4" />
          PDFを見る
        </button>

        <!-- ステータス -->
        <span
          class="text-xs"
          :class="{
            'text-amber-500': upload.status === 'pending',
            'text-green-600': upload.status === 'approved',
            'text-red-500': upload.status === 'rejected'
          }"
        >
          {{ t(upload.status) }}
        </span>

      </div>

      <!-- 却下理由（控えめ表示） -->
      <div
        v-if="upload.status === 'rejected'"
        class="text-xs text-red-500 mt-1"
      >
        {{ upload.rejection_message }}
      </div>

    </div>

  </div>
</div>

    </div>
    <div>
      <DialogModal
        :show="!!previewPdf"
        maxWidth="7xl"
        @close="previewPdf = null"
      >
        <template #title>
          {{ t('PDFpreview') }}
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
            {{ t('closed') }}
          </SecondaryButton>
        </template>
      </DialogModal>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import DialogModal from '@/Components/DialogModal.vue';
import { useForm, Link, router, usePage } from '@inertiajs/vue3'

import { ref, computed , watch } from 'vue'
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Eye, FileText } from 'lucide-vue-next'

import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const page = usePage()

const props = defineProps({
  uploads: { type: Array, required: true },
  member: Object,
  cycle: Object,
  approvedTotal: Number,
  pendingTotal: Number,
  total: Number,
  totalFee: Number,
  totalPaid: Number,
  isFeeOk: Boolean,
  conference_count: Number,

})

const form = useForm({
  last_name: props.member?.last_name ?? '',
  first_name: props.member?.first_name ?? '',
  email: props.member?.email ?? '',
  code: props.member?.code ?? '',
  instructor_code: props.cycle?.instructor_no ?? '',
})


const previewPdf = ref(null)

const openPdf = (pdfPath) => {
  console.log('PDF PATH:', pdfPath)
  if (!pdfPath) return

  // 例：フルパス化
  previewPdf.value = pdfPath
}

const updateStatus = (status) => {

  router.post('/instructor-update-cycles/status', {
    id: props.cycle.id,
    status,
  })
}

</script>
<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>

