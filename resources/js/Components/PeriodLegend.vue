<script setup lang="ts">
import { periodColor } from '@/composables/usePeriodColor'

defineProps<{ periods: { no: number; name: string; start: string; end: string }[] }>()
</script>

<template>
  <div v-if="periods?.length" class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-3 text-xs text-gray-600">
    <span class="font-semibold">申請した期：</span>
    <span v-for="p in periods" :key="p.no" class="inline-flex items-center gap-1.5">
      <span class="inline-block w-1.5 h-4 rounded-sm" :style="{ backgroundColor: periodColor(p.no) }"></span>
      {{ p.name }}
    </span>
  </div>
</template>
