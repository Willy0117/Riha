<template>
  <AppLayout :title="t('pdf_upload')">
    <template #header>{{ t('pdf_upload') }}</template>

    <div class="mx-auto py-6 space-y-6">

      <!-- PDFアップロードフォーム -->
      <div class="p-4 border rounded">
        <div class="flex items-center gap-4 mb-4">

          <h2 class="text-lg font-semibold text-gray-800">
            {{ t('upload_pdf') }}
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
        <form @submit.prevent="submit" class="space-y-4">
          <!-- ファイルドラッグ&ドロップ -->
          <div
            class="border-2 border-dashed border-gray-300 p-4 rounded cursor-pointer text-center bg-[#ddd5bc]"
            @dragover.prevent
            @drop.prevent="onFileDrop"
          >
            <p>{{ t('drag_drop_pdf') }}</p>
            <input type="file" @change="onFileChange" class="hidden" ref="fileInput" />
            <button type="button" class="btn-secondary mt-2" @click="$refs.fileInput.click()">
              {{ t('select_file') }}
            </button>
            <p v-if="form.file">{{ form.file.name }}</p>
            <InputError :message="form.errors?.file" />
          </div>

          <!-- カテゴリ -->
          <div>
            <label class="block mb-1">{{ t('category') }}</label>
            <select v-model.number="form.credit_category_id" class="input-field">
              <option value="" disabled>{{ t('select_category') }}</option>
              <option v-for="c in props.creditCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <!-- conference -->
          <div v-if="filteredConferences.length > 0">
            <label class="block mb-1">{{ t('conference') }}</label>
            <select v-model.number="form.credit_conference_id" class="input-field">
              <option value="" disabled>{{ t('select_conference') }}</option>
              <option v-for="conf in filteredConferences" :key="conf.id" :value="conf.id">{{ conf.name }}</option>
            </select>
          </div>

          <!-- 学術集会のみ session -->
          <div v-if="selectedCategoryIsAcademic">
            <label class="block mb-1">{{ t('session') }}</label>
            <TextInput v-model="form.session" type="text" placeholder="第14回" />
          </div>

          <!-- role -->
          <div v-if="filteredRoles.length > 0">
            <label class="block mb-1">{{ t('role') }}</label>
            <select v-model="form.role_id" class="input-field">
              <option value="" disabled>{{ t('select_role') }}</option>
              <option v-for="r in filteredRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>

          <!-- ポイント表示 -->
          <div v-if="selectedRole">
            <label class="block mb-1">{{ t('points') }}</label>
            <input type="text" class="input w-full" :value="selectedRole.points" readonly />
          </div>

          <div class="flex flex-col items-end gap-2">

            <PrimaryButton
              type="submit"
              :disabled="!isWithinPeriod"
              class="
                transition
                disabled:opacity-50
                disabled:cursor-not-allowed
              "
            >
              {{ t('upload') }}
            </PrimaryButton>

            <p
              v-if="!isWithinPeriod"
              class="text-xs text-gray-400"
            >
              申請期間外のためアップロードできません
            </p>

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
import { Eye, FileText, CalendarDays } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const page = usePage()

const props = defineProps({
  member: Object,
  cycle: Object,
  uploads: { type: Array, required: true },
  creditCategories: { type: Array, required: true },
  conferences: { type: Array, required: true },
  roles: { type: Array, required: true }
})

const form = useForm({
  file: null,
  credit_category_id: '',
  credit_conference_id: '',
  role_id: '',
  session: ''
})

const isWithinPeriod = computed(() => {
  if (!props.cycle) return false

  const now = new Date()

  return (
    now >= new Date(props.cycle.start_date) &&
    now <= new Date(props.cycle.end_date)
  )
})

const fileInput = ref(null)

const filteredConferences = computed(() =>
  props.conferences.filter(c => c.credit_category_id == form.credit_category_id)
)

const filteredRoles = computed(() =>
  props.roles.filter(r =>
    r.credit_category_id == form.credit_category_id &&
    r.credit_conference_id == form.credit_conference_id
  )
);

const selectedRole = computed(() =>
  filteredRoles.value.find(r => r.id == form.role_id) || null
)

const selectedCategoryIsAcademic = computed(() => {
  const cat = props.creditCategories.find(c => c.id == form.credit_category_id)
  return cat ? cat.name === '学術集会' : false
})

function onFileChange(e) {
  form.file = e.target.files[0]
}

function onFileDrop(e) {
  const files = e.dataTransfer.files
  if (files.length > 0) form.file = files[0]
}
watch(() => form.credit_category_id, (v) => {
  console.log("選択中 category_id:", v, typeof v);
});
watch(() => form.credit_conference_id, (v) => {
  console.log("選択中 conference_id:", v, typeof v);
});

const previewPdf = ref(null)

const openPdf = (pdfPath) => {
  if (!pdfPath) return
  previewPdf.value = pdfPath
}

function submit() {
  console.log(form)
  if (!form.file) {
    form.errors.file = 'ファイルを選択してください'
    return
  }

  form.post('/pdf-uploads')
}

</script>
<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>

