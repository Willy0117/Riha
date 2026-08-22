<template>
  <AppLayout title="単位マスタ管理">
    <template #header>単位マスタ管理</template>

    <div class="p-6 space-y-6">

      <!-- 上部ボタン -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex gap-2">
          <Button variant="outline" class="flex items-center gap-2" @click="openCategoryListDialog()">
            区分
          </Button>
          <Button variant="outline" class="flex items-center gap-2" @click="openConferenceListDialog()">
            学会
          </Button>
          <Button variant="outline" class="flex items-center gap-2" @click="openRoleListDialog()">
            role
          </Button>
        </div>

        <div class="flex items-center gap-3">
          <Select v-model="filterCategoryId" @update:model-value="applyFilter">
            <SelectTrigger class="w-48">
              <SelectValue placeholder="すべての区分" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="null">すべての区分</SelectItem>
              <SelectItem v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</SelectItem>
            </SelectContent>
          </Select>

          <!-- 学会名 コンボボックスフィルター -->
          <div class="relative w-56">
            <button
              type="button"
              class="w-full flex items-center justify-between rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm hover:bg-gray-50 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 bg-white"
              @click="toggleConferenceFilterCombobox"
            >
              <span :class="selectedConferenceFilterName ? 'text-gray-900' : 'text-gray-400'">
                {{ selectedConferenceFilterName || 'すべての学会' }}
              </span>
              <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </button>

            <div
              v-if="isConferenceFilterOpen"
              class="fixed inset-0 z-10"
              @click="isConferenceFilterOpen = false"
            />

            <div
              v-if="isConferenceFilterOpen"
              class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg"
            >
              <div class="p-2 border-b border-gray-100">
                <TextInput
                  v-model="conferenceFilterSearch"
                  class="w-full text-sm"
                  placeholder="例: 腎臓 と入力して検索"
                  autocomplete="off"
                  autofocus
                />
              </div>
              <ul class="max-h-56 overflow-y-auto py-1 text-sm">
                <li
                  class="flex items-center px-3 py-2 cursor-pointer hover:bg-indigo-50"
                  @click="selectConferenceFilter(null)"
                >
                  <Check :class="['mr-2 h-4 w-4 flex-shrink-0', !filterConferenceId ? 'opacity-100 text-indigo-600' : 'opacity-0']" />
                  すべての学会
                </li>
                <li v-if="conferenceFilterSuggestions.length === 0" class="px-3 py-2 text-gray-400">
                  該当する学会が見つかりません
                </li>
                <li
                  v-for="conf in conferenceFilterSuggestions"
                  :key="conf.id"
                  class="flex items-center px-3 py-2 cursor-pointer hover:bg-indigo-50"
                  @click="selectConferenceFilter(conf)"
                >
                  <Check :class="['mr-2 h-4 w-4 flex-shrink-0', filterConferenceId === conf.id ? 'opacity-100 text-indigo-600' : 'opacity-0']" />
                  {{ conf.name }}
                </li>
              </ul>
            </div>
          </div>

          <Button class="bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-2" @click="openRolePointDialog()">
            <Plus class="w-4 h-4" />
            単位
          </Button>
        </div>
      </div>

      <!-- credit_role_points 一覧 -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full border-collapse text-[13px]">
          <thead>
            <tr class="bg-gray-50">
              <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200">区分</th>
              <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200">学会名</th>
              <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200">role</th>
              <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200">単位</th>
              <th class="text-center px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200">回数入力</th>
              <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200 w-24">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rolePoints.data.length === 0">
              <td colspan="6" class="text-center text-gray-400 text-sm py-10">データがありません</td>
            </tr>
            <tr v-for="rp in rolePoints.data" :key="rp.id" class="border-b border-gray-100">
              <td class="px-4 py-2.5">
                <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-600">{{ rp.credit_category_name }}</span>
              </td>
              <td class="px-4 py-2.5 font-medium text-gray-800">{{ rp.credit_conference_name }}</td>
              <td class="px-4 py-2.5 text-gray-700">{{ rp.credit_role_name }}</td>
              <td class="px-4 py-2.5 text-right text-gray-600">{{ rp.points }}</td>
              <td class="px-4 py-2.5 text-center text-gray-600">{{ rp.requires_session ? '要' : '不要' }}</td>
              <td class="px-4 py-2.5 text-right">
                <button class="text-gray-400 hover:text-blue-600 mr-2" @click="openEditRolePointDialog(rp)">
                  <Pencil class="w-3.5 h-3.5 inline" />
                </button>
                <button class="text-gray-400 hover:text-red-600" @click="handleDeleteRolePoint(rp)">
                  <Trash2 class="w-3.5 h-3.5 inline" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between text-sm text-gray-500">
        <span>{{ startItem }}〜{{ endItem }} 件 / 全{{ rolePoints.total }}件</span>
        <Pagination :paginator="rolePoints" :onPageChange="goPage" />
      </div>

    </div>

    <!-- 区分 一覧 Dialog -->
    <Dialog :open="isCategoryListDialogOpen" @update:open="isCategoryListDialogOpen = false">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <div class="flex items-center justify-between">
            <DialogTitle>区分一覧</DialogTitle>
            <Button size="sm" class="bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1" @click="openCategoryAddDialog()">
              <Plus class="w-4 h-4" />
              追加
            </Button>
          </div>
        </DialogHeader>

        <div class="max-h-[50vh] overflow-y-auto">
          <table class="w-full border-collapse text-[13px]">
            <thead>
              <tr class="bg-gray-50">
                <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 border-b border-gray-200">区分名</th>
                <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 border-b border-gray-200 w-16">削除</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in localCategories" :key="cat.id" class="border-b border-gray-100">
                <td class="px-3 py-2">
                  <TextInput v-model="cat.name" class="w-full text-sm" @change="saveCategoryInline(cat)" />
                </td>
                <td class="px-3 py-2 text-right">
                  <button class="text-gray-400 hover:text-red-600" @click="handleDeleteCategory(cat)">
                    <Trash2 class="w-3.5 h-3.5 inline" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="isCategoryListDialogOpen = false">閉じる</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 区分 新規追加 Dialog（区分一覧Dialogの上に重ねて開く） -->
    <Dialog :open="isCategoryDialogOpen" @update:open="isCategoryDialogOpen = false">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle>区分の新規登録</DialogTitle>
        </DialogHeader>

        <form @submit.prevent="submitCategory" class="space-y-4">
          <div class="space-y-2">
            <Label>区分名</Label>
            <TextInput v-model="categoryForm.name" class="w-full" placeholder="例: 地方会" />
            <InputError :message="categoryForm.errors?.name" />
          </div>
        </form>

        <DialogFooter>
          <Button variant="outline" @click="isCategoryDialogOpen = false">キャンセル</Button>
          <Button class="bg-blue-600 hover:bg-blue-700 text-white" :disabled="categoryForm.processing" @click="submitCategory">
            登録する
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 学会 一覧 Dialog -->
    <Dialog :open="isConferenceListDialogOpen" @update:open="isConferenceListDialogOpen = false">
      <DialogContent class="sm:max-w-2xl bg-white">
        <DialogHeader>
          <div class="flex items-center justify-between">
            <DialogTitle>学会一覧</DialogTitle>
            <Button size="sm" class="bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1" @click="openConferenceAddDialog()">
              <Plus class="w-4 h-4" />
              追加
            </Button>
          </div>
        </DialogHeader>

        <div class="max-h-[60vh] overflow-y-auto">
          <table class="w-full border-collapse text-[13px]">
            <thead>
              <tr class="bg-gray-50">
                <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 border-b border-gray-200">学会名</th>
                <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 border-b border-gray-200 w-16">削除</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="conf in localConferences" :key="conf.id" class="border-b border-gray-100">
                <td class="px-3 py-2">
                  <TextInput v-model="conf.name" class="w-full text-sm" @change="saveConferenceInline(conf)" />
                </td>
                <td class="px-3 py-2 text-right">
                  <button class="text-gray-400 hover:text-red-600" @click="handleDeleteConference(conf)">
                    <Trash2 class="w-3.5 h-3.5 inline" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="isConferenceListDialogOpen = false">閉じる</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 学会 新規追加 Dialog（学会一覧Dialogの上に重ねて開く） -->
    <Dialog :open="isConferenceDialogOpen" @update:open="isConferenceDialogOpen = false">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle>学会の新規登録</DialogTitle>
        </DialogHeader>

        <form @submit.prevent="submitConference" class="space-y-4">
          <div class="space-y-2">
            <Label>学会名</Label>
            <TextInput v-model="conferenceForm.name" class="w-full" placeholder="例: 日本腎臓リハビリテーション学会" />
            <InputError :message="conferenceForm.errors?.name" />
          </div>

          <p class="text-xs text-gray-500">
            どの区分で使えるか、回数入力が必要かは、この後「単位」の登録（区分×学会×role→単位）で決まります。
          </p>
        </form>

        <DialogFooter>
          <Button variant="outline" @click="isConferenceDialogOpen = false">キャンセル</Button>
          <Button class="bg-blue-600 hover:bg-blue-700 text-white" :disabled="conferenceForm.processing" @click="submitConference">
            登録する
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- role 一覧 Dialog -->
    <Dialog :open="isRoleListDialogOpen" @update:open="isRoleListDialogOpen = false">
      <DialogContent class="sm:max-w-sm bg-white">
        <DialogHeader>
          <div class="flex items-center justify-between">
            <DialogTitle>role一覧</DialogTitle>
            <Button size="sm" class="bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1" @click="openRoleAddDialog()">
              <Plus class="w-4 h-4" />
              追加
            </Button>
          </div>
        </DialogHeader>

        <div class="max-h-[50vh] overflow-y-auto divide-y divide-gray-100">
          <div v-for="r in localRoles" :key="r.id" class="flex items-center gap-2 py-2">
            <span class="flex-1 text-sm text-gray-800">{{ r.name }}</span>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="isRoleListDialogOpen = false">閉じる</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- role 新規追加 Dialog -->
    <Dialog :open="isRoleDialogOpen" @update:open="isRoleDialogOpen = false">
      <DialogContent class="sm:max-w-sm bg-white">
        <DialogHeader>
          <DialogTitle>roleの新規登録</DialogTitle>
        </DialogHeader>

        <form @submit.prevent="submitRole" class="space-y-4">
          <div class="space-y-2">
            <Label>role名</Label>
            <TextInput v-model="roleForm.name" class="w-full" placeholder="例: 演者" />
            <InputError :message="roleForm.errors?.name" />
          </div>
        </form>

        <DialogFooter>
          <Button variant="outline" @click="isRoleDialogOpen = false">キャンセル</Button>
          <Button class="bg-blue-600 hover:bg-blue-700 text-white" :disabled="roleForm.processing" @click="submitRole">
            登録する
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- 単位 Dialog（区分・学会名・role・単位） -->
    <Dialog :open="isRolePointDialogOpen" @update:open="isRolePointDialogOpen = false">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle>{{ isEditingRolePoint ? '単位の編集' : '単位の新規登録' }}</DialogTitle>
        </DialogHeader>

        <form @submit.prevent="submitRolePoint" class="space-y-4">
          <div class="space-y-2">
            <Label>区分</Label>
            <Select v-model="rolePointForm.credit_category_id" :disabled="isEditingRolePoint">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="区分を選択してください" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="rolePointForm.errors?.credit_category_id" />
          </div>

          <div class="space-y-2">
            <Label>学会名</Label>
            <Select v-model="rolePointForm.credit_conference_id" :disabled="isEditingRolePoint">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="学会を選択してください" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="c in conferences" :key="c.id" :value="c.id">{{ c.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="rolePointForm.errors?.credit_conference_id" />
          </div>

          <div class="space-y-2">
            <Label>role</Label>
            <Select v-model="rolePointForm.credit_role_id">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="roleを選択してください" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="rolePointForm.errors?.credit_role_id" />
          </div>

          <div class="space-y-2">
            <Label>単位</Label>
            <TextInput v-model.number="rolePointForm.points" type="number" class="w-full" />
            <InputError :message="rolePointForm.errors?.points" />
          </div>

          <div class="space-y-2">
            <label class="flex items-center gap-1.5 text-sm">
              <input type="checkbox" v-model="rolePointForm.requires_session" />
              提出フォームで「回数」の入力を必要とする
            </label>
            <InputError :message="rolePointForm.errors?.requires_session" />
          </div>
        </form>

        <DialogFooter>
          <Button variant="outline" @click="isRolePointDialogOpen = false">キャンセル</Button>
          <Button class="bg-blue-600 hover:bg-blue-700 text-white" :disabled="rolePointForm.processing" @click="submitRolePoint">
            {{ isEditingRolePoint ? '更新する' : '登録する' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter
} from '@/components/ui/dialog'
import {
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem
} from '@/components/ui/select'
import { Plus, Pencil, Trash2, ChevronsUpDown, Check } from 'lucide-vue-next'

const props = defineProps({
  categories: { type: Array, required: true },
  roles: { type: Array, required: true },
  conferences: { type: Array, required: true },
  rolePoints: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const startItem = computed(() =>
  props.rolePoints.per_page * (props.rolePoints.current_page - 1) + 1
)
const endItem = computed(() =>
  Math.min(props.rolePoints.per_page * props.rolePoints.current_page, props.rolePoints.total)
)

const goPage = (page) => {
  router.get(route('admin.credit-role-points.index'), {
    credit_category_id: filterCategoryId.value,
    credit_conference_id: filterConferenceId.value,
    page,
  }, { preserveState: true, preserveScroll: true })
}

// --- 区分フィルター ---
const filterCategoryId = ref(props.filters?.credit_category_id ?? null)

function applyFilter() {
  router.get(route('admin.credit-role-points.index'), {
    credit_category_id: filterCategoryId.value,
    credit_conference_id: filterConferenceId.value,
    page: 1,
  }, { preserveState: true, preserveScroll: true })
}

// --- 学会名フィルター（コンボボックス） ---
const filterConferenceId = ref(props.filters?.credit_conference_id ?? null)
const isConferenceFilterOpen = ref(false)
const conferenceFilterSearch = ref('')

const selectedConferenceFilterName = computed(() => {
  const selected = props.conferences.find(c => c.id === filterConferenceId.value)
  return selected?.name ?? ''
})

const conferenceFilterSuggestions = computed(() => {
  const keyword = conferenceFilterSearch.value.trim()
  if (!keyword) return props.conferences
  return props.conferences.filter(c => c.name.includes(keyword))
})

function toggleConferenceFilterCombobox() {
  isConferenceFilterOpen.value = !isConferenceFilterOpen.value
  if (isConferenceFilterOpen.value) {
    conferenceFilterSearch.value = ''
  }
}

function selectConferenceFilter(conf) {
  filterConferenceId.value = conf ? conf.id : null
  isConferenceFilterOpen.value = false
  applyFilter()
}

// --- 区分 一覧 Dialog + インライン編集 ---
const isCategoryListDialogOpen = ref(false)
const localCategories = ref(props.categories.map(c => ({ ...c })))

watch(() => props.categories, (newVal) => {
  localCategories.value = newVal.map(c => ({ ...c }))
}, { deep: true })

function openCategoryListDialog() {
  isCategoryListDialogOpen.value = true
}

function saveCategoryInline(cat) {
  router.put(route('admin.credit-role-points.categories.update', { category: cat.id }), {
    name: cat.name,
  }, { preserveScroll: true, preserveState: true })
}

function handleDeleteCategory(cat) {
  if (!confirm(`「${cat.name}」を削除しますか？紐づく単位設定も削除されます。`)) return
  router.delete(route('admin.credit-role-points.categories.destroy', { category: cat.id }), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      localCategories.value = localCategories.value.filter(c => c.id !== cat.id)
    },
  })
}

// --- 区分 新規追加 Dialog ---
const isCategoryDialogOpen = ref(false)

const categoryForm = useForm({
  name: '',
})

function openCategoryAddDialog() {
  categoryForm.reset()
  categoryForm.clearErrors()
  isCategoryDialogOpen.value = true
}

function submitCategory() {
  categoryForm.post(route('admin.credit-role-points.categories.store'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => { isCategoryDialogOpen.value = false },
  })
}

// --- 学会 一覧 Dialog + インライン編集 ---
const isConferenceListDialogOpen = ref(false)
const localConferences = ref(props.conferences.map(c => ({ ...c })))

// サーバーから新しいconferences一覧が返ってきたら（追加・削除・別タブでの変更後など）自動で反映する
watch(() => props.conferences, (newVal) => {
  localConferences.value = newVal.map(c => ({ ...c }))
}, { deep: true })

function openConferenceListDialog() {
  isConferenceListDialogOpen.value = true
}

function saveConferenceInline(conf) {
  router.put(route('admin.credit-role-points.conferences.update', { conference: conf.id }), {
    name: conf.name,
  }, { preserveScroll: true, preserveState: true })
}

function handleDeleteConference(conf) {
  if (!confirm(`「${conf.name}」を削除しますか？紐づく単位も削除されます。`)) return
  router.delete(route('admin.credit-role-points.conferences.destroy', { conference: conf.id }), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      localConferences.value = localConferences.value.filter(c => c.id !== conf.id)
    },
  })
}

// --- 学会 新規追加 Dialog ---
const isConferenceDialogOpen = ref(false)

const conferenceForm = useForm({
  name: '',
})

function openConferenceAddDialog() {
  conferenceForm.reset()
  conferenceForm.clearErrors()
  isConferenceDialogOpen.value = true
}

function submitConference() {
  conferenceForm.post(route('admin.credit-role-points.conferences.store'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      isConferenceDialogOpen.value = false
    },
  })
}

// --- role 一覧 Dialog ---
const isRoleListDialogOpen = ref(false)
const localRoles = ref(props.roles.map(r => ({ ...r })))

watch(() => props.roles, (newVal) => {
  localRoles.value = newVal.map(r => ({ ...r }))
}, { deep: true })

function openRoleListDialog() {
  isRoleListDialogOpen.value = true
}

// --- role 新規追加 Dialog ---
const isRoleDialogOpen = ref(false)
const roleForm = useForm({ name: '' })

function openRoleAddDialog() {
  roleForm.reset()
  roleForm.clearErrors()
  isRoleDialogOpen.value = true
}

function submitRole() {
  roleForm.post(route('admin.credit-role-points.roles.store'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => { isRoleDialogOpen.value = false },
  })
}

// --- 単位（credit_role_points）Dialog ---
const isRolePointDialogOpen = ref(false)
const isEditingRolePoint = ref(false)
const editingRolePointId = ref(null)

const rolePointForm = useForm({
  credit_category_id: '',
  credit_conference_id: '',
  credit_role_id: '',
  points: 0,
  requires_session: true,
})

function openRolePointDialog() {
  isEditingRolePoint.value = false
  editingRolePointId.value = null
  rolePointForm.reset()
  rolePointForm.clearErrors()
  isRolePointDialogOpen.value = true
}

function openEditRolePointDialog(rp) {
  isEditingRolePoint.value = true
  editingRolePointId.value = rp.id
  rolePointForm.clearErrors()
  rolePointForm.credit_category_id = rp.credit_category_id
  rolePointForm.credit_conference_id = rp.credit_conference_id
  rolePointForm.credit_role_id = rp.credit_role_id
  rolePointForm.points = rp.points
  rolePointForm.requires_session = rp.requires_session
  isRolePointDialogOpen.value = true
}

function submitRolePoint() {
  if (isEditingRolePoint.value) {
    rolePointForm.put(route('admin.credit-role-points.update', { rolePoint: editingRolePointId.value }), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => { isRolePointDialogOpen.value = false },
    })
  } else {
    rolePointForm.post(route('admin.credit-role-points.store'), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => { isRolePointDialogOpen.value = false },
    })
  }
}

function handleDeleteRolePoint(rp) {
  if (!confirm(`「${rp.credit_conference_name} / ${rp.credit_role_name}」を削除しますか？`)) return
  router.delete(route('admin.credit-role-points.destroy', { rolePoint: rp.id }), {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>