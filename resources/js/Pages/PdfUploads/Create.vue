<template>
  <AppLayout :title="$t('pdf_upload')">

    <template #header>{{ $t('pdf_upload') }}</template>
 <div class="dashboard">

    <!-- 上段：資格更新状況 + 認定・更新期間 -->
    <!--div class="grid-top" -->
    <div class="grid grid-cols-6 gap-6">  

      <!-- 資格更新状況 -->
      <!-- div class="card card-main" -->
      <div class="col-span-6 md:col-span-4 bg-white rounded-xl border border-gray-200 p-6">
        <div class="card-header">
          <div class="card-title">
            <Award class="w-5 h-5 text-blue-600" />
            <h2>資格更新状況</h2>
          </div>
          <span class="badge-draft">蓄積中（一時保存）</span>
        </div>
        <p class="card-desc">現在の獲得単位数と更新要件の達成度です。</p>

        <div class="progress-area">
          <div class="progress-info">
            <div class="unit-display">
              <span class="unit-current">{{ totalCredits }}</span>
              <span class="unit-separator"> / {{ props?.requiredUnits }} 単位</span>
            </div>
            <div class="unit-right">
              <span class="unit-remaining">あと {{ Math.max((props.requiredUnits || 0) - (props.approvedTotal || 0), 0) }} 単位</span>
              <span class="gakkai-info">腎リハ学術集会参加: {{ props?.conference_count }} / 2 回</span>
            </div>
          </div>
          <Progress :value="progressPercent" class="h-3" />
        </div>
        <div class="flex items-center justify-between p-4 bg-gray-50 border border-gray-200 rounded-xl">
          <div class="flex items-center gap-3">
            <AlertCircle 
              :class="['w-8 h-8 flex-shrink-0', isEligible ? 'text-blue-500' : 'text-gray-400']" 
            />
            <div>
              <p class="font-semibold text-gray-700">更新要件の確認中</p>
              <p class="text-sm text-gray-500">すべての要件を満たすと申請ボタンが有効になります。</p>
            </div>
          </div>
          <Button
            :disabled="!isEligible"
            :class="[
              'whitespace-nowrap',
              isEligible
                ? 'bg-blue-600 hover:bg-blue-700 text-white'
                : 'text-gray-400 bg-gray-100 border border-gray-200'
            ]"
            @click="showUpdateDialog = true"
          >
            更新申請を行う
          </Button>
        </div>
        <div class="stats-row">
          <div class="stat-box">
            <span class="stat-label">既得単位</span>
            <span class="stat-value">{{ props?.approvedTotal }}</span>
          </div>
          <div class="stat-box">
            <span class="stat-label">申請中・承認待ち</span>
            <span class="stat-value pending">+{{ props?.pendingTotal }}</span>
          </div>
        </div>
      </div>

      <!-- 認定・更新期間 -->
      <!-- div class="card card-side" -->
      <div class="col-span-6 md:col-span-2 bg-white rounded-xl border border-gray-200 p-6">  
        <div class="card-title">
          <Calendar class="card-icon orange" :size="20" />
          <h2>認定・更新期間</h2>
        </div>

        <div class="period-block">
          <p class="period-label">
            認定期間 {{ cycleYears }} 年間
            <span v-if="cycleYears > 5" class="text-red-500">
              （原則5年だが延長されている扱い）
            </span>
          </p>
          <p class="period-value">{{ props.cycle?.start_date }} 〜 {{ props.cycle?.end_date }}</p>
        </div>

        <div class="period-block renewal">
          <p class="period-label orange">更新申請受付期間</p>
          <p class="period-value orange">{{ props.cycle?.renewal_start_date }} 〜 {{ props.cycle?.renewal_end_date }}</p>
        </div>

        <div class="info-box">
          <Info class="w-4 h-4 text-orange-500 flex-shrink-0 mt-0.5" />
          <p>更新申請は認定期間最終年の指定期間内（上記）にのみ受け付けています。</p>
        </div>
      </div>
    </div>
   <div class="grid grid-cols-6 gap-6">
    <!-- 支払い状況 -->
    <div class="col-span-6 md:col-span-4 bg-white rounded-xl border border-gray-200 p-6">
      <div class="card-title">
        <CreditCard class="card-icon green" :size="20" />
        <h2>支払い状況</h2>
      </div>
      <p class="card-desc">年会費および更新料の納入状況です。</p>

      <div class="payment-row">
        <div class="payment-box cursor-pointer hover:opacity-80" @click="showFeeDialog = true">
          <span class="payment-label">本年度年会費</span>
          <div class="payment-status">
            <span :class="['payment-value', annualFeeStatus === '未納' ? 'unpaid' : 'paid']">
              {{ annualFeeStatus }}
            </span>
            <Clock class="w-4 h-4 text-gray-300" />
          </div>
        </div>
        <div class="payment-box cursor-pointer hover:opacity-80" @click="showFeeDialog = true">
          <span class="payment-label">更新料</span>
          <div class="payment-status">
            <span :class="['payment-value', renewalFeeStatus === '未請求' ? 'unpaid' : 'paid']">
              {{ renewalFeeStatus }}
            </span>
            <Clock class="w-4 h-4 text-gray-300" />
          </div>
        </div>
      </div>
      
      <div class="warning-box">
        <Info class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
        <p>更新料の支払いが確認できるまで、更新手続きは完了しません。振込後、反映まで数日かかる場合があります。</p>
      </div>
    </div>  
    <!-- 認定・更新期間 -->
    <div class="col-span-6 md:col-span-2 bg-white rounded-xl border border-gray-200 p-6"> 
      <div class="card-title">
        <Award class="card-icon orange" :size="20" />
        <h2>更新を辞退する</h2>
      </div>

      <div class="info-box">
        <Info class="w-4 h-4 text-orange-500 flex-shrink-0 mt-0.5" />
        <p>更新を辞退される場合は、右記のボタンよりお知らせください。</p>
        <Button size="sm" class="bg-orange-600 hover:bg-orange-700 text-white" @click="updateStatus('no_update'); showUpdateDialog = false">
          {{ t('instructors.cancel') }}
        </Button>
      </div>
    </div>
    
   </div>    
    <!-- 提出書類・申請履歴 -->
    <div class="section-header">
      <h2 class="section-title">提出書類・申請履歴</h2>
      <Button 
        class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-5 py-2.5 text-xs font-semibold whitespace-nowrap flex items-center gap-2" 
        @click="isOpen = true"
      >
        <Upload class="w-4 h-4" /> 新規申請・書類アップロード
      </Button>
    </div>

    <div class="card card-table">
      <table class="data-table">
        <thead>
          <tr>
            <th>書類名 / 区分</th>
            <th>日付</th>
            <th>単位</th>
            <th>ステータス</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="props.uploads?.length === 0">
            <td colspan="5" class="empty-row">
              <div class="empty-state">
                <p>申請履歴がありません</p>
              </div>
            </td>
          </tr>
          <tr v-for="app in props.uploads" :key="app.id">
            <td>
              <div class="flex items-center gap-2">
                <FileText class="w-4 h-4 text-gray-400 flex-shrink-0" />
                <div>
                  <p class="font-medium">{{ app.session }}{{ app.credit_conference_name }}</p>
                  <Badge variant="outline" class="text-xs mt-1">{{ app.role_name }}</Badge>
                  <p class="text-xs text-blue-600 font-semibold mt-1">認定学会: {{ app.credit_category_name }}</p>
                </div>
              </div>
            </td>
            <td>{{ app.date }}</td>
            <td>{{ app.points }} {{ t('instructors.point') }}</td>
            <td><span
                class="text-xs"
                :class="{
                  'text-amber-500': app.status === 'pending',
                  'text-green-600': app.status === 'approved',
                  'text-red-500': app.status === 'rejected'
                }"
              >
              {{ t(app.status) }}
            </span>
            </td>
            <td
              v-if="app.status === 'rejected'"
              class="text-xs text-red-500 mt-1"
            >
            {{ app.rejection_message }}
            </td>
            <td v-else class="text-xs text-gray-400 mt-1">
              -
            </td>
            <td>
              <div class="flex items-center gap-2">
                <button
                  @click="previewPdf = `/pdf-uploads/${app.id}/view`"
                  class="text-xs text-gray-500 hover:text-sky-700 transition flex items-center gap-1"
                >
                  <FileText class="w-4 h-4" />
                  詳細
                </button>
                <button
                  @click=""
                  class="text-xs text-red-500 hover:text-red-600 transition flex items-center gap-1"
                >
                  <Trash2 class="w-4 h-4" />
                  削除
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>

    <div>
      <Dialog :open="showUpdateDialog" @update:open="showUpdateDialog = false">
        <DialogContent class="sm:max-w-2xl bg-white">
          <DialogHeader>
            <DialogTitle>更新申請</DialogTitle>
            <DialogDescription>
              以下の情報を確認の上、送信してください。
            </DialogDescription>
          </DialogHeader>

          <form @submit.prevent="submit" class="space-y-4">
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
          </form>

          <DialogFooter>
            <Button variant="outline" size="sm" @click="showUpdateDialog = false">
              {{ t('cancel') }}
            </Button>
            <Button size="sm" class="bg-blue-600 hover:bg-blue-700 text-white" @click="updateStatus('pending'); showUpdateDialog = false">
              {{ t('instructors.send') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog :open="showFeeDialog" @update:open="showFeeDialog = false">
        <DialogContent class="sm:max-w-lg bg-white">
          <DialogHeader>
            <DialogTitle>年会費詳細</DialogTitle>
          </DialogHeader>

          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-gray-500">
                <th class="py-2 text-left">年度</th>
                <th class="py-2 text-right">年会費</th>
                <th class="py-2 text-right">更新費</th>
                <th class="py-2 text-right">納入額</th>
                <th class="py-2 text-center">状態</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="fee in props.fees" :key="fee.id" class="border-b">
                <td class="py-2">{{ fee.fiscal_year }}年度</td>
                <td class="py-2 text-right">{{ fee.annual_fee.toLocaleString() }}円</td>
                <td class="py-2 text-right">{{ fee.renewal_fee.toLocaleString() }}円</td>
                <td class="py-2 text-right">{{ fee.payment_amount.toLocaleString() }}円</td>
                <td class="py-2 text-center">
                  <span :class="[
                    'px-2 py-0.5 rounded-full text-xs font-medium',
                    fee.status === 'paid' ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-500'
                  ]">
                    {{ fee.status === 'paid' ? '納入済' : '未納' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>

          <DialogFooter>
            <Button variant="outline" size="sm" @click="showFeeDialog = false">閉じる</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog :open="!!previewPdf" @update:open="previewPdf = null">
        <DialogOverlay class="fixed inset-0 z-40 bg-black/50" />
        <DialogContent class="w-[95vw] max-w-[95vw] h-[95vh] max-h-[95vh] p-0 flex flex-col sm:rounded-lg">
          <DialogHeader class="px-4 py-3 border-b">
            <DialogTitle>{{ t('PDFpreview') }}</DialogTitle>
          </DialogHeader>
          <div class="flex-1 overflow-hidden">
            <iframe
              v-if="previewPdf"
              :src="previewPdf"
              class="w-full h-full border-0"
            />
          </div>
          <DialogFooter class="px-4 py-3 border-t">
            <Button variant="outline" @click="previewPdf = null">
              {{ t('closed') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
        <!-- Dialog -->
      <Dialog :open="isOpen" @update:open="isOpen = false">
        <DialogContent class="sm:max-w-2xl bg-white"">
          <DialogHeader>
            <DialogTitle>新規書類アップロード</DialogTitle>
            <DialogDescription>
              参加証や修了証などの書類をアップロードし、区分を選択してください。
            </DialogDescription>
          </DialogHeader>

          <form @submit.prevent="submit" class="space-y-5 py-2">

            <!-- 書類名（session） -->
            <div class="space-y-2">
              <Label for="session">書類名</Label>
              <TextInput
                id="session"
                v-model="form.session"
                placeholder="例: 第16回学術集会 参加証"
                class="w-full"
              />
              <InputError :message="form.errors?.session" />
            </div>

            <!-- 区分 -->
            <div class="space-y-2">
              <Label>区分</Label>
              <select v-model.number="form.credit_category_id" class="input-field">
                <option value="" disabled>区分を選択してください</option>
                <option v-for="c in props.creditCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <InputError :message="form.errors?.credit_category_id" />
            </div>

            <!-- conference -->
            <div v-if="props.conferences.length > 0" class="space-y-2">
              <Label>学会名</Label>
              <select v-model.number="form.credit_conference_id" class="input-field">
                <option value="" disabled>学会を選択してください</option>
                <option v-for="conf in filteredConferences" :key="conf.id" :value="conf.id">{{ conf.name }}</option>
              </select>
            </div>

            <!-- role -->
            <div v-if="props.roles.length > 0" class="space-y-2">
              <Label>役割</Label>
              <select v-model="form.role_id" class="input-field">
                <option value="" disabled>役割を選択してください</option>
                <option v-for="r in filteredRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>

            <!-- 参加日/発行日 -->
            <div class="space-y-2">
              <Label for="issued_date">参加日 / 発行日</Label>
              <TextInput id="issued_date" v-model="form.issued_date" type="date" class="w-full" />
            </div>

            <!-- ファイルドラッグ&ドロップ -->
            <div
              class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition"
              @dragover.prevent
              @drop.prevent="onFileDrop"
              @click="fileInput?.click()"
            >
              <FileUp class="w-8 h-8 text-gray-400 mx-auto mb-2" />
              <p class="text-sm text-gray-500">ファイルをドラッグ＆ドロップ、またはクリックして選択</p>
              <p class="text-xs text-gray-400 mt-1">PDF, JPG, PNG (最大 10MB)</p>
              <p v-if="form.file" class="text-xs text-indigo-600 mt-2 font-medium">{{ form.file.name }}</p>
              <input type="file" class="hidden" ref="fileInput" @change="onFileChange" />
              <InputError :message="form.errors?.file" />
            </div>

          </form>
          <div v-if="warnings.length > 0" class="bg-white border border-gray-200 rounded-lg p-4 space-y-1 shadow-sm">
            <p class="text-sm font-semibold text-red-600 flex items-center gap-2">
              <AlertTriangle class="w-4 h-4 text-red-500" />
              AI検証で不一致が検出されました
            </p>
            <ul class="text-sm text-gray-700 list-disc list-inside mt-2">
              <li v-for="(w, i) in warnings" :key="i">{{ w }}</li>
            </ul>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="isOpen = false">キャンセル</Button>
            <Button
              class="bg-indigo-600 hover:bg-indigo-700 text-white"
              :disabled="!isWithinPeriod || form.processing"
              @click="upload"
            >
              <Save class="w-4 h-4 mr-2" />
              保存（下書き保存）
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import {
  Dialog, DialogContent, DialogHeader,
  DialogTitle, DialogDescription, DialogFooter, DialogOverlay
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'

import { ref, computed , watch } from 'vue'
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Eye, FileText, Calendar, CreditCard, Clock, Info, GraduationCap, Award, Trash2, Upload, Save, FileUp, AlertCircle, AlertTriangle } from 'lucide-vue-next'
import dayjs from 'dayjs'

import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const page = usePage()

const warnings = computed(() => page.props.flash?.warnings ?? [])

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
  requiredUnits: Number,
  creditCategories: { type: Array, required: true },
  conferences: { type: Array, required: true },
  roles: { type: Array, required: true },
  fees: Object,
  annualFeeStatus: Boolean, 
})

const totalCredits = computed(() => (props.approvedTotal ?? 0) + (props.pendingTotal ?? 0))

const form = useForm({
  last_name: props.member?.last_name ?? '',
  first_name: props.member?.first_name ?? '',
  email: props.member?.email ?? '',
  code: props.member?.code ?? '',
  instructor_code: props.cycle?.instructor_no ?? '',
  file: null,
  credit_category_id: '',
  credit_conference_id: '',
  role_id: '',
  session: '',
  issued_date: ''
})

const cycleYears = computed(() => {
  if (!props.cycle?.start_date || !props.cycle?.end_date) return 5
  const start = new Date(props.cycle.start_date)
  const end = new Date(props.cycle.end_date)
  return end.getFullYear() - start.getFullYear()
})

const progressPercent = computed(() => {
  const approved = props.approvedTotal ?? 0
  const required = props.requiredUnits ?? 50
  return Math.min(Math.round((approved / required) * 100), 100)
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

const isOpen = ref(false)

// --- 検索・フィルター ---
const searchQuery = ref('')
const filterYear = ref('')

// --- 選択 ---
const selectedIds = ref([])

// --- フィルタリング ---
const filteredMembers = computed(() => {
  return members.value.filter((m) => {
    const matchYear = filterYear.value === '' || m.renewalYear === Number(filterYear.value)
    const q = searchQuery.value.toLowerCase()
    const matchSearch =
      q === '' ||
      m.name.includes(q) ||
      m.memberNo.toLowerCase().includes(q)
    return matchYear && matchSearch
  })
})

const showUpdateDialog = ref(false)

const isEligible = computed(() => {
  const now = new Date()
  const renewalStart = props.cycle?.renewal_start_date ? new Date(props.cycle.renewal_start_date) : null
  const renewalEnd = props.cycle?.renewal_end_date ? new Date(props.cycle.renewal_end_date) : null

  const isWithinPeriod = renewalStart && renewalEnd
    ? now >= renewalStart && now <= renewalEnd
    : false

  return (
    isWithinPeriod &&
    (props.conference_count ?? 0) > 1 &&
    (totalCredits.value ?? 0) >= (props.requiredUnits ?? 50)
  )
})

// --- 全選択 ---
const isAllSelected = computed(
  () =>
    filteredMembers.value.length > 0 &&
    filteredMembers.value.every((m) => selectedIds.value.includes(m.id))
)

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = filteredMembers.value.map((m) => m.id)
  }
}

const toggleSelect = (id) => {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((i) => i !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

// --- アクション ---
const handleDeleteSelected = () => {
  if (confirm(`選択した ${selectedIds.value.length} 名を削除しますか？`)) {
    members.value = members.value.filter((m) => !selectedIds.value.includes(m.id))
    selectedIds.value = []
  }
}

const handleEdit = (member) => alert(`編集: ${member.name}`)

const fileInput = ref(null);

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.file = file;
    }
};

const onFileDrop = (e) => {
    const file = e.dataTransfer.files[0];
    if (file) {
        form.file = file;
    }
};

function upload() {
  console.log(form)
  const issuedDate = dayjs(form.issued_date)
  const startDate = dayjs(props.cycle?.start_date)
  const endDate = dayjs(props.cycle?.end_date)

  if (!issuedDate.isValid()) {
    alert('参加日/発行日を入力してください')
    return
  }

  if (issuedDate.isBefore(startDate) || issuedDate.isAfter(endDate)) {
    alert(`参加日/発行日は認定期間（${props.cycle?.start_date} 〜 ${props.cycle?.end_date}）内である必要があります`)
    return
  }

  form.post('/pdf-uploads', {
    file: form.file,
    credit_category_id: form.credit_category_id,
    credit_conference_id: form.credit_conference_id,
    role_id: form.role_id, 
    session: form.session,
    issued_date: form.issued_date,
  })
}

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

const isWithinPeriod = computed(() => {
  return true
  if (!props.cycle) return false

  const now = form.issued_date // new Date()

  return (
    now >= new Date(props.cycle.start_date) &&
    now <= new Date(props.cycle.end_date)
  )
})

const annualFeeStatus = computed(() => props.annualFeeStatus ? '納入済' : '未納')

const showFeeDialog = ref(false)

</script>
<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
.dashboard {
  margin: 0 auto;
  padding: 32px;
  display: flex;
  flex-direction: column;
  gap: 24px;
  font-family: 'Hiragino Sans', 'Noto Sans JP', sans-serif;
  color: #1a1a2e;
}

/* 上段グリッド */
.grid-top {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
}

/* カード共通 */
.card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 24px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 4px;
}

.card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.card-title h2 {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}

.card-icon { font-size: 18px; }
.card-icon.orange { color: #f97316; }
.card-icon.green  { color: #10b981; }

.card-desc {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 20px;
}

/* 蓄積中バッジ */
.badge-draft {
  font-size: 11px;
  font-weight: 600;
  color: #d97706;
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 20px;
  padding: 3px 10px;
  white-space: nowrap;
}

/* プログレス */
.progress-area {
  margin-bottom: 20px;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 10px;
}

.unit-display {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.unit-current {
  font-size: 28px;
  font-weight: 700;
  color: #2563eb;
}

.unit-separator {
  font-size: 14px;
  color: #6b7280;
}

.unit-right {
  text-align: right;
}

.unit-remaining {
  display: block;
  font-size: 13px;
  color: #2563eb;
  font-weight: 600;
}

.gakkai-info {
  display: block;
  font-size: 12px;
  color: #dc2626;
  font-weight: 600;
  margin-top: 2px;
}

.progress-bar-bg {
  height: 8px;
  background: #e5e7eb;
  border-radius: 99px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: #2563eb;
  border-radius: 99px;
  transition: width 0.4s ease;
}

/* 統計ボックス */
.stats-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 8px;
}

.stat-box {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px 16px;
}

.stat-label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
}

.stat-value {
  display: block;
  font-size: 24px;
  font-weight: 700;
  color: #111827;
}

.stat-value.pending {
  color: #2563eb;
}

/* 認定・更新期間 */
.period-block {
  margin-bottom: 16px;
}

.period-block.renewal {
  background: #fff7ed;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 16px;
}

.period-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.period-label.orange { color: #f97316; font-weight: 600; }

.period-value {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.period-value.orange { color: #f97316; }

/* 情報ボックス */
.info-box {
  display: flex;
  gap: 8px;
  background: #f0f9ff;
  border-radius: 8px;
  padding: 12px;
  font-size: 12px;
  color: #374151;
  line-height: 1.6;
}

.info-icon { flex-shrink: 0; }

/* 支払い */
.card-payment { max-width: calc(100% - 340px - 24px); }

.payment-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

.payment-box {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.payment-label {
  font-size: 12px;
  color: #6b7280;
  display: block;
  margin-bottom: 4px;
}

.payment-status {
  display: flex;
  align-items: center;
  gap: 8px;
}

.payment-value {
  font-size: 16px;
  font-weight: 600;
}

.payment-value.unpaid { color: #111827; }
.payment-value.paid   { color: #10b981; }

.payment-clock { font-size: 18px; color: #d1d5db; }

.warning-box {
  display: flex;
  gap: 8px;
  background: #eff6ff;
  border-radius: 8px;
  padding: 12px;
  font-size: 12px;
  color: #374151;
  line-height: 1.6;
}

.warning-icon { flex-shrink: 0; }

/* セクションヘッダー */
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  font-size: 18px;
  font-weight: 800;
  color: #1e3a6e;
  font-style: italic;
}

/* テーブル */
.card-table { padding: 0; overflow: hidden; }

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.data-table th {
  padding: 12px 20px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.data-table td {
  padding: 14px 20px;
  border-bottom: 1px solid #f3f4f6;
  color: #374151;
}

.empty-row { padding: 48px 20px !important; }

.empty-state {
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
}

/* ステータスバッジ */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.status-approved  { background: #d1fae5; color: #065f46; }
.status-reviewing { background: #fef3c7; color: #d97706; }
.status-applied   { background: #dbeafe; color: #1d4ed8; }
.status-rejected  { background: #fee2e2; color: #dc2626; }

.action-btn {
  padding: 5px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  font-size: 12px;
  cursor: pointer;
  color: #374151;
  transition: all 0.15s;
}

.action-btn:hover {
  border-color: #2563eb;
  color: #2563eb;
}
/* DialogのOverlayを薄くする */
[data-radix-popper-content-wrapper],
.fixed.inset-0.z-50.bg-black\/80 {
  background-color: rgba(0, 0, 0, 0.4) !important;
}

</style>

