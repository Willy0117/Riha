<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { AlertTriangle } from 'lucide-vue-next'

const page = usePage()

const show = ref(false)
const message = ref('')
const warnings = ref([])
const type = ref('success')
let timer = null

watch(
  () => [page.props.flash?.success, page.props.flash?.error, page.props.flash?.warnings],
  ([success, error, newWarnings]) => {
    if (!success && !error && !newWarnings?.length) return

    message.value = success ?? error ?? ''
    type.value = error ? 'error' : 'success'
    warnings.value = newWarnings ?? []
    show.value = true

    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
      show.value = false
    }, 5000) // warningがある場合は少し長めに
  },
  { deep: true }
)
</script>

<template>
  <div class="fixed top-4 right-4 z-[9999] max-w-sm">
    <transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 translate-y-2 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
    <div v-if="show"
        :class="[
          'px-4 py-3 rounded-xl shadow-lg',
          warnings.length > 0 ? 'bg-white border border-gray-200' : (type === 'success' ? 'bg-green-500' : 'bg-red-500')
        ]">

      <div class="flex items-center">
        <span :class="['flex-1 text-sm font-semibold', warnings.length > 0 ? 'text-gray-800' : 'text-white']">
          {{ message }}
        </span>
        <button
          @click="show = false"
          :class="['ml-3 w-6 h-6 flex items-center justify-center rounded', warnings.length > 0 ? 'hover:bg-gray-100 text-gray-500' : 'hover:bg-white/20 text-white']"
        >
          ×
        </button>
      </div>

      <!-- warnings -->
      <ul v-if="warnings.length > 0" class="mt-2 space-y-2 border-t border-gray-100 pt-2">
        <li v-for="(w, i) in warnings" :key="i" class="text-xs flex items-start gap-2 text-gray-600">
          <AlertTriangle class="w-3 h-3 text-red-500 mt-0.5 shrink-0" />
          <span>{{ w }}</span>
        </li>
      </ul>

    </div>
    </transition>
  </div>
</template>