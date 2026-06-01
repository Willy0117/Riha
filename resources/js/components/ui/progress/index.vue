<script setup>
defineProps({
  value: {
    type: Number,
    default: 0,
  },
})
</script>

<template>
  <div class="relative h-3 w-full overflow-hidden rounded-full bg-gray-100">
    <div
      class="h-full bg-blue-600 transition-all duration-300"
      :style="{ width: `${Math.min(value, 100)}%` }"
    />
  </div>
</template>
