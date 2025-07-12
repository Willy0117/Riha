<script setup>
import { ref, watch, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Welcome from '@/Components/Welcome.vue'
import AutoCompleteInput from '@/Components/AutoCompleteInput.vue'

const selectedCustomer = ref(null)

watch(selectedCustomer, (newVal) => {
  console.log('👀 [Dashboard] selectedCustomer changed:', newVal)
})

</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard
            </h2>
        </template>
    <div class="p-6 max-w-md mx-auto">
      <AutoCompleteInput
        v-model="selectedCustomer"
        labelKey="company_name"
        placeholder="顧客名を入力してください"
      />
     <!-- デバッグ用: オブジェクトをそのまま表示 -->
      <pre class="mt-4 bg-gray-100 p-2">
        {{ selectedCustomer }}
      </pre>      
      <p class="mt-4">
        選択中の顧客:
        {{ selectedCustomer ? selectedCustomer.company_name : 'なし' }}
      </p>
      <!-- hiddenにIDや他フィールドも渡せます -->
      <input type="hidden" name="customer_id" :value="selectedCustomer?.id || ''" />
    </div>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <Welcome />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
