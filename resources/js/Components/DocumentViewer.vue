<template>
    <Dialog :open="!!current" @update:open="close">
      <DialogContent class="w-[70vw] max-w-[70vw] h-[95vh] max-h-[95vh] p-0 flex flex-col">
        <DialogHeader class="px-4 py-3 border-b flex-row items-center justify-between">
          <DialogTitle>
            {{ current ? [sessionLabel(current.session), current.credit_conference_name].filter(Boolean).join(' ') : 'プレビュー' }}
          </DialogTitle>
          <!-- [今回追加] 今どの書類を見ているか分かるよう、ページ番号を表示する -->
          <span v-if="current" class="text-sm text-gray-400 mr-8">
            {{ previewIndex + 1 }} / {{ uploads.length }}
          </span>
        </DialogHeader>

        <div class="flex-1 flex overflow-hidden">
          <!-- 左：プレビュー -->
          <div class="flex-1 relative flex items-center justify-center bg-gray-50 overflow-hidden">
            <button
              v-if="previewIndex > 0"
              class="absolute left-2 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/90 border border-gray-200 shadow flex items-center justify-center hover:bg-white"
              @click="showAdjacentPreview(-1)"
            >
              <ChevronLeft class="w-6 h-6 text-gray-600" />
            </button>

            <img
              v-if="current?.is_image"
              :src="previewFileUrl"
              class="max-w-full max-h-full object-contain"
            />
            <iframe
              v-else-if="previewFileUrl"
              :src="`${previewFileUrl}#toolbar=0&navpanes=0`"
              class="w-full h-full border-0"
            />

            <button
              v-if="previewIndex < uploads.length - 1"
              class="absolute right-2 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full bg-white/90 border border-gray-200 shadow flex items-center justify-center hover:bg-white"
              @click="showAdjacentPreview(1)"
            >
              <ChevronRight class="w-6 h-6 text-gray-600" />
            </button>
          </div>

          <!-- [今回追加] 右：情報＋操作パネル -->
          <div v-if="current" class="w-96 flex-none border-l border-gray-200 overflow-y-auto p-4 space-y-4">
            <div>
              <h3 class="text-lg font-bold text-indigo-900">
                {{ [sessionLabel(current.session), current.credit_conference_name].filter(Boolean).join(' ') }}
              </h3>
              <p class="text-sm text-gray-700 mt-1">
                【{{ current.credit_conference_name }}】{{ current.role_name }}
              </p>
              <div class="flex items-center gap-3 mt-2">
                <span class="flex items-center gap-1 text-xs text-gray-500">
                  <Calendar class="w-3.5 h-3.5" />
                  {{ current.issued_date?.split('T')[0] }}
                </span>
                <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                  +{{ current.points }} 単位
                </span>
              </div>
              <span
                class="inline-block mt-2 text-xs px-2 py-1 rounded-full font-medium"
                :class="{
                  'bg-yellow-50 text-yellow-600 border border-yellow-200': current.status === 'pending',
                  'bg-green-50 text-green-600 border border-green-200': current.status === 'approved',
                  'bg-red-50 text-red-600 border border-red-200': current.status === 'rejected',
                }"
              >
                {{ statusLabel(current.status) }}
              </span>
            </div>

            <!-- 審査済み：結果表示 -->
            <div v-if="current.status === 'approved' || current.status === 'rejected'">
              <div v-if="current.status === 'approved'" class="bg-green-50 rounded-xl p-3 flex items-center gap-2 text-green-700">
                <CheckCircle2 class="w-5 h-5" />
                <span class="font-semibold text-sm">この書類は確認済です。</span>
              </div>
              <div v-else class="bg-red-50 rounded-xl p-3 space-y-1">
                <div class="flex items-center gap-2 text-red-600 font-semibold text-sm">
                  <XCircle class="w-5 h-5" />
                  却下
                </div>
                <p class="text-sm text-red-500">{{ current.rejection_message }}</p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter class="px-4 py-3 border-t">
          <Button variant="outline" @click="close">閉じる</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
</template>

<script setup>
// 書類ビューア（共通）。審査員／アサイン担当者／委員長／事務局の閲覧画面で同じ見た目・操作にする。
// ◁▷で前後の書類に移動、右側に書類情報（操作ボタンなし）。
import { computed } from 'vue'
import { ChevronLeft, ChevronRight, Calendar, CheckCircle2, XCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter
} from '@/components/ui/dialog'

const props = defineProps({
  uploads: { type: Array, default: () => [] },
  modelValue: { type: Object, default: null },
  // 書類ファイルを返すルート名（画面ごとの権限に合わせて指定）
  fileRoute: { type: String, required: true },
})
const emit = defineEmits(['update:modelValue'])

const current = computed(() => props.modelValue)
const close = () => emit('update:modelValue', null)

const previewFileUrl = computed(() =>
  current.value ? route(props.fileRoute, { id: current.value.id }) : null
)

const previewIndex = computed(() =>
  current.value ? props.uploads.findIndex(u => u.id === current.value.id) : -1
)

const showAdjacentPreview = (direction) => {
  if (!current.value) return
  const next = previewIndex.value + direction
  if (next < 0 || next >= props.uploads.length) return
  emit('update:modelValue', props.uploads[next])
}

const sessionLabel = (session) => (session ? `第${session}回` : '')
const statusLabel = (status) => ({ pending: '未審査', approved: '確認済', rejected: '却下' }[status] ?? '-')
</script>
