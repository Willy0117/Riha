<template>
  <AppLayout>
    <template #header>
      {{ t('instructors.list') }}
    </template>

    <div class="p-6">

      <!-- 🔍 検索 -->
      <form @submit.prevent="doSearch" class="mb-4 flex items-center gap-2">
        <input
          v-model="search"
          type="text"
          placeholder="検索"
          class="border px-3 py-2 rounded w-60"
        />

        <button
          type="submit"
          class="bg-blue-500 text-white px-4 py-2 rounded"
        >
          検索
        </button>
      </form>
      <!-- per_page + add -->
      <div class="flex flex-wrap md:flex-nowrap md:justify-between mb-4 items-center gap-2">

        <!-- per_page + add -->
        <div class="flex items-center gap-2">
          <select
            v-model.number="form.per_page"
            @change="submitSearch"
            class="border rounded px-3 py-2 w-16 h-10"
          >
            <option v-for="n in [10,20,30,50]" :key="n" :value="n">{{ n }}</option>
          </select>

        </div>

        <button
          @click="bulkUpdate"
          :disabled="selectedIds.length === 0"
          class="px-4 h-10 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 flex items-center space-x-1"
        >
          <BadgeCheck class="w-4 h-4"/>
          <span>{{ t('update_selected') }}</span>
        </button>
      </div>
      <!-- テーブル -->
      <table class="table-auto w-full border border-gray-300 text-sm">
        <thead>
          <tr class="bg-gray-200">
            <th class="px-3 py-2">
              <input type="checkbox" :checked="selectAll" @change="toggleSelectAll($event.target.checked)" />
            </th>
            <th class="border px-3 py-2">{{ t('name') }}</th>
            <th class="border px-3 py-2">{{ t('instructors.update_period') }}</th>
            <th class="border px-3 py-2">{{ t('instructors.total_points') }}</th>
            <th class="border px-3 py-2">{{ t('instructors.conference_count') }}</th>
            <th class="border px-3 py-2">{{ t('status') }}</th>
            <th class="border px-3 py-2">{{ t('actions') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="member in props.members.data"
            :key="member.id"
            class="odd:bg-white even:bg-gray-100"
          >

            <td class="px-3 py-2">
              <input type="checkbox" :value="member.id" v-model="selectedIds" />
            </td>
            <td class="border px-3 py-2">{{ member.name }}</td>

            <!-- 最新更新サイクル -->
            <td class="border px-3 py-2">
              {{ member.update_cycles[0]?.start_date ?? '-' }} ～ {{ member.update_cycles[0]?.end_date ?? '-' }}
            </td>
            <td class="border px-3 py-2">
              {{ member.update_cycles[0]?.total_points ?? '-' }}
            </td>
            <td class="border px-3 py-2">
              {{ member.update_cycles[0]?.conference_count ?? '-' }}
            </td>
            <td class="border px-3 py-2">
              <!-- before_update の場合のみ審査ボタン表示 -->
              <div v-if="member.update_cycles[0]?.status === 'pending'">
                <button
                  @click="openReviewModal(member)"
                  class="bg-yellow-500 text-white px-2 py-1 rounded text-sm"
                >
                  {{ t('instructors.pending') }}
                </button>
              </div>

              <!-- before_update 以外は通常ステータス表示 -->
              <div v-else :class="statusColor(member.update_cycles[0]?.status)">
                {{ statusLabel(member.update_cycles[0]?.status) }}
              </div>
            </td>  

            <td class="border px-3 py-2">
              <Link
                :href="route('admin.instructorMembers.show', {
                  member: member.id,
                  search: search,
                  page: page
                })"
                class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm"
              >
                {{ t('details') }}
              </Link>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- ページネーション -->
      <Pagination
        :paginator="members"
        :onPageChange="goPage"
        :startItem="startItem"
        :endItem="endItem"
      />

    </div>

    <div>
      <!-- 審査モーダル -->
      <DialogModal :show="reviewModal.show" @close="reviewModal.show = false">
        <template #title>
          {{ t('instructors.update') }}
        </template>

        <template #content>
          <div class="mb-4">
            <label class="block mb-2">{{ t('instructors.choose_status') }}</label>
            <select v-model="reviewModal.status" class="w-full border rounded p-2">
              <option value="updated">{{ t('update') }}</option>
              <option value="no_update">{{ t('no_update') }}</option>
              <option value="rejected">{{ t('rejected') }}</option>
            </select>
          </div>

          <div class="mb-4" v-if="reviewModal.status === 'rejected' || reviewModal.status === 'no_update'">
            <label class="block mb-2">{{ t('instructors.reason') }}</label>
            <textarea
              v-model="reviewModal.reason"
              class="w-full border rounded p-2"
              rows="4"
              placeholder="理由を入力してください"
            ></textarea>
          </div>
        </template>

        <template #footer>
          <SecondaryButton @click="reviewModal.show = false">
            {{ t('cancel') }}
          </SecondaryButton>
          <PrimaryButton class="ms-3" @click="submitReview">
            {{ t('submit') }}
          </PrimaryButton>
        </template>
      </DialogModal>
   
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { BadgeCheck } from 'lucide-vue-next'

const { t } = useI18n()

const page = usePage()

// props
const props = defineProps({
  members: Object, // 👈 paginator オブジェクトに変更
  filters: Object, // { search: "" }
})

const form = reactive({
  name: props.filters.name,
  per_page: props.filters.per_page || 20,
  sort_by: props.filters.sort_by,   // ← 初期値を必ずセット
  sort_dir: props.filters.sort_dir,    // ← 初期値を必ずセット
})
// persistQueryに各検索項目を追加
const persistQuery = () => ({
  name: form.name,
  per_page: form.per_page,
  sort_by: form.sort_by,
  sort_dir: form.sort_dir,
  page: props.members.current_page
})

const submitSearch = () => {
  console.log(persistQuery())
  router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page: 1 }, {
    preserveState: true,
    replace: true,
    onSuccess: () => resetSelectedIds()
  })
}

// ページ番号クリック
const goPage = (page) => {
  router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page }, {
    preserveState: true,
    replace: true,
    onSuccess: () => resetSelectedIds()
  })
}

// 列ヘッダクリックでソート
const sortBy = (field) => {
  if (form.sort_by === field) form.sort_dir = form.sort_dir==='asc'?'desc':'asc'
  else { form.sort_by = field; form.sort_dir = 'desc' }
  submitSearch()
}
// ---------- 件数計算（あなたのロジック） ----------
const startItem = computed(() => {
  if (props.members.total === 0) return 0
  return form.per_page * (props.members.current_page - 1) + 1
})

const endItem = computed(() => {
  if (props.members.total === 0) return 0
  return Math.min(form.per_page * props.members.current_page, props.members.total)
})

// statusLabel / statusColor を function で定義
function statusLabel(s) {
  return {
    updated: t('updated'),
    before_update: t('before_update'),
    no_update: t('no_update'),
    rejected: t('rejected')
  }[s] || '-'
}
// 選択削除
const selectedIds = ref([])

const toggleSelectAll = (checked) => {
  selectedIds.value = checked ? props.members.data.map(s => s.id) : []
}

const resetSelectedIds = () => {
  selectedIds.value = []
}

const selectAll = computed({
  get() {
    return selectedIds.value.length === props.members.data.length
  }
})

// 複数更新
const bulkUpdate = () => {
  if (!confirm(t('confirm_update_selected'))) return
  router.post(
    route('admin.instructorMembers.bulkUpdate'),
    { ids: selectedIds.value },
    {
      preserveState: true,
      onSuccess: () => {
        router.get(route('admin.instructorMembers.index'), { ...persistQuery(), page: props.members.current_page }, { preserveState: true })
      }
    }
  )
}

function statusColor(s) {
  return {
    updated: 'text-green-600 font-semibold',
    before_update: 'text-blue-600 font-semibold',
    no_update: 'text-gray-600',
    rejected: 'text-red-600 font-semibold'
  }[s] || 'text-gray-600'
}

const reviewModal = ref({
  show: false,
  member: null,
  status: 'updated',
  reason: '',
})

function openReviewModal(member) {
  const cycle = member.update_cycles[0] // 最新の更新サイクル
  if (!cycle) return

  reviewModal.value.show = true
  reviewModal.value.cycleId = cycle.id
  reviewModal.value.memberName = member.name
  reviewModal.value.status = 'updated'  // デフォルト選択
  reviewModal.value.reason = ''
}

function submitReview() {
  if (!reviewModal.value.cycleId) return

  router.post(
    route('admin.instructorUpdateCycles.review', reviewModal.value.cycleId),
    {
      status: reviewModal.value.status,
      reason: reviewModal.value.reason,
    },
    {
      onSuccess: () => {
        reviewModal.value.show = false
        router.get(
          route('admin.instructorMembers.index'),
          { search: search.value, page: members.current_page },
          { preserveState: true, preserveScroll: true }
        )
      },
      onError: (errors) => {
        console.error(errors)
      }
    }
  )
}
</script>
