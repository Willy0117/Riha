<template>
  <AppLayout>
    <template #header>
      <h2 class="text-2xl font-bold page-title-navy">SMS送信テスト</h2>
    </template>

    <div class="p-6 max-w-lg space-y-4">

      <div
        v-if="page.props.flash?.success"
        class="px-4 py-3 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm"
      >
        {{ page.props.flash.success }}
      </div>

      <div
        v-if="errors.message"
        class="px-4 py-3 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm"
      >
        {{ errors.message }}
      </div>

      <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-4">
        <p class="text-xs text-gray-500">
          AWS SNS経由でSMSを送信する動作確認用の画面です。管理者自身の携帯電話番号宛に、
          テストメッセージを送信できます。
        </p>

        <div>
          <label class="text-xs text-gray-500 mb-1 block">送信先電話番号</label>
          <input
            v-model="form.phone_number"
            type="text"
            placeholder="090-1234-5678"
            class="input-field"
          />
          <p v-if="adminMobile" class="text-[11px] text-gray-400 mt-1">
            登録済みの携帯番号：{{ adminMobile }}
          </p>
        </div>

        <div>
          <label class="text-xs text-gray-500 mb-1 block">メッセージ本文</label>
          <textarea
            v-model="form.message"
            rows="4"
            class="input-field resize-none"
            placeholder="テストメッセージです。"
          />
        </div>

        <div class="flex justify-end">
          <Button :disabled="!form.phone_number || !form.message" @click="submit">
            送信する
          </Button>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import { Button } from '@/components/ui/button'

const page = usePage()

const props = defineProps({
  adminMobile: String,
})

const errors = computed(() => page.props.errors ?? {})

const form = reactive({
  phone_number: props.adminMobile ?? '',
  message: 'テストメッセージです。JSRRシステムよりSMS送信テストを実施しています。',
})

const submit = () => {
  router.post(route('admin.smsTest.send'), form, {
    preserveScroll: true,
  })
}
</script>

<style>
.input-field {
  @apply w-full rounded-md border border-gray-300 px-3 py-2 text-sm
         shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500;
}
</style>
