<template>
  <AppLayout :title="t('pdf-uploads.list')">
    <template #header>{{ t('pdf-uploads.list') }}</template>

    <div class="p-6 space-y-6">

      <!-- 検索・フィルター -->
      <div class="grid grid-cols-7 gap-4 text-sm">
        <div class="col-span-1">
          <InputLabel :value="t('pdf-uploads.category')" />
          <select v-model="form.category_id" class="input w-full border rounded px-2 py-1">
            <option value="">{{ t('all') }}</option>
            <option
                v-for="category in props.categories"
                :key="category.id"
                :value="category.id"
            >
                {{ category.name }}
            </option>
          </select>
        </div>
        <div class="col-span-2">
          <InputLabel :value="t('pdf-uploads.conference')" />
          <select v-model="form.conference_id" class="input w-full border rounded px-2 py-1">
            <option value="">{{ t('all') }}</option>
            <option
                v-for="conference in props.conferences"
                :key="conference.id"
                :value="conference.id"
            >
                {{ conference.name }}
            </option>
          </select>
        </div>
        <div class="col-span-1">
          <InputLabel :value="t('pdf-uploads.role')" />
          <select v-model="form.role_id" class="input w-full border rounded px-2 py-1">
            <option value="">{{ t('all') }}</option>
            <option
                v-for="role in filteredRoles"
                :key="role.id"
                :value="role.id"
            >
                {{ role.name }}
            </option>
          </select>
        </div>
        <div class="col-span-1">
          <InputLabel :value="t('pdf-uploads.session')" />
          <select v-model="form.exam_round" class="input w-full border rounded px-2 py-1">
            <option value="">{{ t('all') }}</option>
            <option
                v-for="cycle in instructorcycles"
                :key="cycle.id"
                :value="cycle.exam_round"
            >
              第 {{ cycle.exam_round }} 回
            </option>
          </select>
        </div>
        <div class="col-span-1">
          <InputLabel :value="t('pdf-uploads.end_date')" />
          <VueDatePicker
            v-model="form.end_date"
            :format-locale="ja"
            format="yyyy/MM/dd"
            model-type="yyyy-MM-dd"
            :enable-time-picker="false"
            :week-start="0"
            :day-class="dayClass"
            auto-apply
            input-class-name="input w-full border rounded px-2 py-1"
          >
          </VueDatePicker>
        </div>
        <div class="flex items-end col-span-1">
          <button
            @click="submitSearch"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full"
          >
            {{ t('search') }}
          </button>
        </div>
      </div>
<!-- TABLE -->
<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">

  <!-- HEADER -->
  <div class="grid grid-cols-9 gap-3 px-5 py-3 bg-slate-50 text-[11px] font-medium text-slate-500">
    <div>姓名</div>
    <div>カテゴリー</div>
    <div>認定回</div>
    <div>学術種別</div>
    <div>参加権限</div>
    <div>証明書</div>
    <!-- status sortable -->
    <div
      class="cursor-pointer select-none flex items-center gap-1"
      @click="sortBy('status')"
    >
      ステータス
  <span class="ml-1 text-slate-400">
    <!-- 未選択 -->
    <ArrowUpDown v-if="form.sort_by !== 'status'" class="w-4 h-4" />

    <!-- 選択済み：昇順 -->
    <ArrowUp
      v-else-if="form.sort_dir === 'asc'"
      class="w-4 h-4 text-slate-700"
    />

    <!-- 選択済み：降順 -->
    <ArrowDown
      v-else
      class="w-4 h-4 text-slate-700"
    />
  </span>
    </div>

    <!-- date sortable -->
    <div
      class="cursor-pointer select-none flex items-center gap-1"
      @click="sortBy('created_at')"
    >
      作成日
  <span class="ml-1 text-slate-400">
    <!-- 未選択 -->
    <ArrowUpDown v-if="form.sort_by !== 'created_at'" class="w-4 h-4" />

    <!-- 選択済み：昇順 -->
    <ArrowUp
      v-else-if="form.sort_dir === 'asc'"
      class="w-4 h-4 text-slate-700"
    />

    <!-- 選択済み：降順 -->
    <ArrowDown
      v-else
      class="w-4 h-4 text-slate-700"
    />
  </span>
    </div>
    <div class="text-right">操作</div>
  </div>

  <!-- BODY -->
  <div class="divide-y divide-slate-100">

    <div
      v-for="upload in props.uploads.data"
      :key="upload.id"
      class="grid grid-cols-9 gap-3 px-5 py-4 items-center hover:bg-slate-50 transition"
    >

      <!-- name -->
      <div class="text-sm font-medium text-slate-900">
        {{ upload.member?.name ?? '-' }}
      </div>

      <!-- category -->
      <div class="text-sm text-slate-600">
        {{ upload.credit_category?.name ?? '-' }}
      </div>

      <!-- session -->
      <div class="text-sm text-slate-600">
        {{ upload.session ?? '-' }}
      </div>

      <!-- conference -->
      <div class="text-sm text-slate-600 truncate max-w-[220px]" :title="upload.credit_conference?.name">
        {{ upload.credit_conference?.name ?? '-' }}
      </div>
      <!-- role -->
      <div class="text-sm text-slate-600">
        {{ upload.credit_role?.role ?? '-' }}
      </div>

      <!-- pdf -->
      <div>
        <div @click="openPreview(upload.id)" class="cursor-pointer flex items-center gap-2">
          <img
            v-if="upload.thumbnail_url"
            :src="upload.thumbnail_url"
            class="w-10 h-10 object-contain border border-slate-200 rounded"
          />
          <span v-else class="text-xs text-slate-400">
            {{ t('no_thumbnail') }}
          </span>
        </div>
      </div>

      <!-- status -->
      <div>
        <span
          class="inline-flex px-2 py-1 text-xs rounded-md"
          :class="{
            'bg-yellow-50 text-yellow-700': upload.status==='pending',
            'bg-emerald-50 text-emerald-700': upload.status==='approved',
            'bg-red-50 text-red-700': upload.status==='rejected'
          }"
        >
          {{ $t(upload.status) }}
        </span>
      </div>

      <!-- date -->
      <div class="text-sm text-slate-500">
        {{ upload.updated_at ? dayjs(upload.updated_at).format('YYYY/MM/DD') : '-' }}
      </div>

      <!-- actions -->
      <div class="text-right flex justify-end gap-3">

        <button
          v-if="upload.status==='pending'"
          @click="approve(upload.id)"
          class="text-sm text-emerald-600 hover:text-emerald-700"
        >
          承認
        </button>

        <button
          v-if="upload.status==='pending'"
          @click="openReject(upload)"
          class="text-sm text-red-600 hover:text-red-700"
        >
          却下
        </button>

      </div>

    </div>

  </div>
</div>
      <!-- ページネーション -->
      <Pagination :paginator="uploads" :onPageChange="goPage"/>
    </div>

    <div>
      <DialogModal :show="showRejectModal" @close="closeModal">
        <template #title>
          {{ t('status') }} {{ t('change') }}
        </template>

        <template #content>
            <textarea
            v-model="rejectForm.message"
            class="w-full border rounded p-2 mb-4"
            rows="4"
            :placeholder="t('pdf-uploads.message')"
            >
            </textarea>
        </template>
        <template #footer>
          <SecondaryButton @click="closeModal">
            {{ t('cancel') }}
          </SecondaryButton>
          <PrimaryButton class="ms-3" @click="submitReject">
              {{ t('reject') }}
          </PrimaryButton>
        </template>
      </DialogModal>

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
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css';
import { ja } from 'date-fns/locale'

import axios from 'axios'
import { Link, usePage, router } from '@inertiajs/vue3'
import { ref, reactive, computed, watch} from 'vue'
import { useI18n } from 'vue-i18n'
import dayjs from 'dayjs'

import { PlusIcon, PencilIcon, TrashIcon, MagnifyingGlassIcon, DocumentPlusIcon, HandThumbUpIcon, HandThumbDownIcon} from '@heroicons/vue/24/outline'
import { ArrowUp, ArrowDown, ArrowUpDown } from 'lucide-vue-next'

const page = usePage()

const props = defineProps({
  uploads: Object,
  categories: Object,
  conferences: Object,
  instructorcycles: Object,
  roles: Object,
  filters: Object,
})

console.log(props.filters)

const { t } = useI18n()

// フィルタフォーム
const form = reactive({
  category_id: props.filters?.category_id ?? '',
  conference_id: props.filters?.conference_id ?? '',
  role_id: props.filters?.role_id ?? '',
  end_date: props.filters?.end_date,
  exam_round: props.filters?.exam_round ?? '',
  status: props.filters?.status,
  per_page: props.filters?.per_page,
  sort_by: props.filters?.sort_by ?? 'created_at',
  sort_dir:props.filters?.sort_dir ?? 'desc',
})

const filteredRoles = computed(() =>
  props.roles.filter(r =>
    r.credit_category_id == form.category_id &&
    r.credit_conference_id == form.conference_id
  )
)

// ページング
const goPage = (page) => {
  router.get(route('admin.pdf-uploads.index'), {...form, page}, {preserveState:true})
}

// 検索
const submitSearch = () => {
  router.get(route('admin.pdf-uploads.index'), form, {preserveState:false})
}

// ソート
const sortBy = (field) => {
  if(form.sort_by===field) form.sort_dir=form.sort_dir==='asc'?'desc':'asc'
  else { form.sort_by=field; form.sort_dir='asc' }
  submitSearch()
}

// 承認
const approve = (id) => {
  router.post(
    route('admin.pdf-uploads.approve', { pdf: id }),
    {},
    {
      preserveState:true,
      onSuccess: () => {
        const upload = props.uploads.find(u => u.id === id)
        if(upload) upload.status = 'approved'
      }
    }
  )
}

const showRejectModal = ref(false)

// 差し戻しモーダル
const rejectForm = ref({
  uploadId: null,
  message: ''
})

const openReject = async (upload) => {
  rejectForm.value.uploadId = upload.id
  rejectForm.value.message = ''
  showRejectModal.value = true
}

const closeModal = () => {
  showRejectModal.value = false;
}

const submitReject = () => {
  if (!rejectForm.value.message) {
    alert('Rejection message is required')
    return
  }

  router.post(
    route('admin.pdf-uploads.reject', { pdf: rejectForm.value.uploadId }),
    { rejection_message: rejectForm.value.message },
    { preserveState: true }
  )

  showRejectModal.value = false
}
const previewPdf = ref(null)

const openPreview = (id) => {
  previewPdf.value = `/admin/pdf-uploads/${id}/view`
}

const dayClass = (date) => {
  const day = date.getDay()

  if (day === 0) return 'dp-sunday'
  if (day === 6) return 'dp-saturday'

  return ''
}

</script>
<style>
.dp-sunday {
  color: red !important;
}

.dp-saturday {
  color: blue !important;
}
/*
.dp__input {
  width: 100%;
  border: 1px solid #d1d5db !important;
  border-radius: 0.375rem !important;
  padding: 0.5rem 2.5rem 0.5rem 0.75rem !important;
  min-height: 38px;
}
*/
</style>


