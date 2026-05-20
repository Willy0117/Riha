<template>
 <div class="flex justify-between items-center mt-4">
<div v-if="paginator.last_page > 1" class="flex items-center gap-1">

  <!-- FULL MODE（1〜10ページ） -->
  <template v-if="!isCompactPagination">

    <button
      v-for="page in paginator.last_page"
      :key="page"
      @click="changePage(page)"
      class="w-8 h-8 rounded-md text-sm transition"
      :class="page === paginator.current_page
        ? 'bg-slate-900 text-white'
        : 'hover:bg-slate-100 text-slate-600'
      "
    >
      {{ page }}
    </button>

  </template>

  <!-- COMPACT MODE（11ページ以上） -->
  <template v-else>

    <button
      v-for="page in pagesToShow"
      :key="page"
      @click="changePage(page)"
      class="w-8 h-8 rounded-md text-sm transition"
      :class="page === paginator.current_page
        ? 'bg-slate-900 text-white'
        : 'hover:bg-slate-100 text-slate-600'
      "
    >
      {{ page }}
    </button>

  </template>

</div>

  <!-- info -->
  <div class="text-sm text-slate-500">
    {{ startItem }}-{{ endItem }} / {{ paginator.total }}
  </div>

</div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  paginator: Object,          // Inertia のページネーションオブジェクト
  onPageChange: Function      // ページ番号クリック時のコールバック
})

// 表示件数計算
const startItem = computed(() => props.paginator.per_page * (props.paginator.current_page - 1) + 1)
const endItem = computed(() => Math.min(props.paginator.per_page * props.paginator.current_page, props.paginator.total))

// ページ切り替え
function changePage(page) {
  props.onPageChange(page)
}

const pagesToShow = computed(() => {
  const last = props.paginator.last_page
  const current = props.paginator.current_page

  const start = Math.max(1, current - 2)
  const end = Math.min(last, current + 2)

  const pages = []
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
})
</script>
