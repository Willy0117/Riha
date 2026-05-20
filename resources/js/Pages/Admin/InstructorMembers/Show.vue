<template>
  <AppLayout :title="`${member.name} - ${t('instructor_details')}`">
    <template #header>{{ member.name }}</template>
    <div class="p-6 space-y-4">
      <button
        @click="backToIndex"
        class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
      >
        ← {{ t('back') }}
      </button>
      <!-- 会員情報 -->
      <div class="border rounded p-4">
        <div><strong>{{ t('name') }}:</strong> {{ member.name }}</div>
        <div><strong>{{ t('status') }}:</strong> {{ t(`cycle_status.${cycle.status}`) }}</div>
        <div><strong>{{ t('instructors.update_period') }}:</strong> {{ cycle.start_date }} - {{ cycle.end_date }}</div>
        <div><strong>{{ t('instructors.total_points') }}:</strong> {{ cycle.total_points }}</div>
        <div><strong>{{ t('instructors.conference_count') }}:</strong> {{ cycle.conference_count }}</div>
      </div>

      <!-- アップロード一覧 -->
<div>
  <h2 class="text-xl font-bold text-gray-800 mb-4">
    {{ t('uploaded_files') }}
  </h2>

  <div
    v-if="uploads.length === 0"
    class="bg-gray-50 border border-dashed border-gray-300 rounded-2xl p-10 text-center text-gray-500"
  >
    {{ t('no_uploads') }}
  </div>

  <div
    v-else
    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-6"
  >

    <div
      v-for="upload in uploads"
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
          @click="openPreview(upload.id)"
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
import { ref } from 'vue'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import DialogModal from '@/Components/DialogModal.vue';

import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// props の取り出し方法が間違っていた
const props = usePage()
console.log(props);

const member = props.props.member
const uploads = props.props.uploads
const filters = props.props.filters

// update cycle が無い場合に備えて
const cycle = member.update_cycles?.[0] || {
  start_date: '-',
  end_date: '-',
  total_points: 0,
  conference_count: 0
}

// Vue で扱いやすく成形
const uploadList = uploads.map(u => ({
  ...u,
  credit_conference_name: u.credit_conference?.name || '',
  category_name: u.credit_category?.name || '',
  role_name: u.credit_role?.role || ''
}))

function backToIndex() {
  console.log(props.filters?.page ?? 1)
  router.get(route('admin.instructorMembers.index'), {
    search: props.filters?.search ?? '',
    page: props.filters?.page ?? 1,
  })
}

const previewPdf = ref(null)

const openPreview = (id) => {
  console.log(id)
  previewPdf.value = `/admin/pdf-uploads/${id}/view`
}

</script>
