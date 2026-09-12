<template>
  <AppLayout>
    <template #header>
      <h2 class="text-2xl font-bold page-title-navy">更新進捗ダッシュボード</h2>
    </template>

    <div class="p-6 space-y-6">

      <!-- 役割別の対応待ち件数（年度フィルタと無関係に常に全件表示、ページ最上部に配置） -->
      <!-- [今回変更] ログイン中の管理者が view 権限を持つ項目のみ表示する（null なら非表示） -->
      <div v-if="unassignedCount !== null || unreviewedCount !== null || unapprovedCount !== null">
        <h3 class="text-sm font-bold text-gray-700 mb-3">役割別の対応待ち件数</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Link
            v-if="unassignedCount !== null"
            :href="route('admin.subleader.index')"
            class="bg-white border border-gray-200 rounded-xl p-5 block hover:shadow-md hover:border-gray-300 transition"
          >
            <p class="text-xs text-gray-500 mb-1">アサイン担当者：未アサイン</p>
            <p class="text-3xl font-bold" :class="unassignedCount > 0 ? 'text-orange-600' : 'text-gray-400'">
              {{ unassignedCount }}<span class="text-sm font-normal ml-1">件</span>
            </p>
          </Link>

          <Link
            v-if="unreviewedCount !== null"
            :href="route('admin.reviewer.index')"
            class="bg-white border border-gray-200 rounded-xl p-5 block hover:shadow-md hover:border-gray-300 transition"
          >
            <p class="text-xs text-gray-500 mb-1">審査員：未審査</p>
            <p class="text-3xl font-bold" :class="unreviewedCount > 0 ? 'text-orange-600' : 'text-gray-400'">
              {{ unreviewedCount }}<span class="text-sm font-normal ml-1">件</span>
            </p>
          </Link>

          <Link
            v-if="unapprovedCount !== null"
            :href="route('admin.chief.index')"
            class="bg-white border border-gray-200 rounded-xl p-5 block hover:shadow-md hover:border-gray-300 transition"
          >
            <p class="text-xs text-gray-500 mb-1">審査委員長：未承認</p>
            <p class="text-3xl font-bold" :class="unapprovedCount > 0 ? 'text-orange-600' : 'text-gray-400'">
              {{ unapprovedCount }}<span class="text-sm font-normal ml-1">件</span>
            </p>
          </Link>
        </div>
      </div>

      <!-- 年度選択 -->
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-500">対象年度</span>
        <select v-model.number="selectedYear" @change="changeYear" class="border rounded pl-2 pr-7 py-1 text-sm">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}年度</option>
        </select>
      </div>

      <!-- サマリーカード -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-5">
          <p class="text-xs text-gray-500 mb-1">今年度対象者数</p>
          <p class="text-3xl font-bold text-gray-800">{{ totalTargets }}<span class="text-sm font-normal ml-1">名</span></p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
          <p class="text-xs text-gray-500 mb-1">現時点での申請者数</p>
          <p class="text-3xl font-bold text-blue-600">{{ appliedCount }}<span class="text-sm font-normal ml-1">名</span></p>
          <p class="text-xs text-gray-400 mt-1">対象者の {{ appliedPercentage }}%</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
          <p class="text-xs text-gray-500 mb-1">現時点での更新完了者数</p>
          <p class="text-3xl font-bold text-emerald-600">{{ completedCount }}<span class="text-sm font-normal ml-1">名</span></p>
          <p class="text-xs text-gray-400 mt-1">対象者の {{ completedPercentage }}%</p>
        </div>
      </div>

      <!-- ステータス内訳（円グラフ＋バー） -->
      <div class="bg-white border border-gray-200 rounded-xl p-5">
        <h3 class="text-sm font-bold text-gray-700 mb-4">ステータス別内訳</h3>
        <div class="flex flex-col md:flex-row items-center md:items-start gap-8">
          <!-- 円グラフ -->
          <div class="flex-none">
            <svg viewBox="0 0 200 200" width="200" height="200">
              <circle
                v-for="seg in donutSegments"
                :key="seg.status"
                cx="100" cy="100" r="70"
                fill="none"
                :stroke="seg.color"
                stroke-width="30"
                :stroke-dasharray="`${seg.length} ${circumference - seg.length}`"
                :stroke-dashoffset="-seg.offset"
                transform="rotate(-90 100 100)"
              />
              <text x="100" y="95" text-anchor="middle" class="fill-gray-800" style="font-size: 28px; font-weight: 700;">
                {{ totalTargets }}
              </text>
              <text x="100" y="118" text-anchor="middle" class="fill-gray-400" style="font-size: 12px;">
                対象者数
              </text>
            </svg>
          </div>

          <!-- バー一覧 -->
          <div class="flex-1 w-full space-y-3">
            <div v-for="item in breakdown" :key="item.status" class="flex items-center gap-3">
              <span class="w-3 h-3 rounded-full flex-none" :style="{ backgroundColor: statusColor(item.status) }" />
              <span class="w-24 text-sm text-gray-600 flex-none">{{ item.label }}</span>
              <div class="flex-1 bg-gray-100 rounded-full h-3 overflow-hidden">
                <div
                  class="h-full rounded-full"
                  :style="{ width: item.percentage + '%', backgroundColor: statusColor(item.status) }"
                />
              </div>
              <span class="w-20 text-right text-sm text-gray-700 flex-none">{{ item.count }}名（{{ item.percentage }}%）</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'

const props = defineProps({
  currentYear: Number,
  yearOptions: Array,
  totalTargets: Number,
  appliedCount: Number,
  appliedPercentage: Number,
  completedCount: Number,
  completedPercentage: Number,
  breakdown: Array,
  unassignedCount: Number,
  unreviewedCount: Number,
  unapprovedCount: Number,
})

const selectedYear = ref(props.currentYear)

const changeYear = () => {
  router.get(route('admin.dashboard'), { year: selectedYear.value }, { preserveState: true })
}

const statusColorMap = {
  before_update: '#d1d5db', // gray-300
  pending:       '#facc15', // yellow-400
  approved:      '#34d399', // emerald-400
  reject:        '#f87171', // red-400
  no_update:     '#9ca3af', // gray-400
  updated:       '#3b82f6', // blue-500
  lapsed:        '#374151', // gray-700
}
const statusColor = (status) => statusColorMap[status] ?? '#d1d5db'

// [今回追加] ドーナツ円グラフ用のセグメント計算
const circumference = 2 * Math.PI * 70 // r=70

const donutSegments = computed(() => {
  if (!props.totalTargets) return []
  let offset = 0
  return props.breakdown
    .filter(item => item.count > 0)
    .map(item => {
      const length = (item.count / props.totalTargets) * circumference
      const seg = {
        status: item.status,
        color: statusColor(item.status),
        length,
        offset,
      }
      offset += length
      return seg
    })
})
</script>
