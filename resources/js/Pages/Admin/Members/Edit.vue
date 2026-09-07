<script setup lang="ts">
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'
import MemberForm from '@/Components/MemberForm.vue'
import type { MemberEditProps, MemberFormData } from '@/types'

const props = defineProps<MemberEditProps>()

const isEdit = computed(() => !!props.member?.id)

// バリデーションエラー（Laravel側の withErrors() が自動的にここへ入る）
const errors = computed(() => usePage().props.errors as Record<string, string>)

function handleSubmit(data: MemberFormData) {
  isEdit.value
    ? router.put(`/admin/members/${props.member!.id}`, data)
    : router.post('/admin/members', data)
}

function handleCancel() {
  router.visit('/admin/members', { data: props.filters })
}
</script>

<template>
  <AppLayout>
    <template #header>
      <p class="text-xs text-muted-foreground">会員管理</p>
      <h1 class="text-xl font-semibold">
        {{ isEdit ? '会員情報を編集' : '新規会員登録' }}
      </h1>
    </template>
    <div class="h-full flex flex-col">
      <!-- [今回追加] members.edit 権限が無い場合の警告バナー -->
      <div
        v-if="!can_edit"
        class="mx-6 mt-4 bg-amber-50 border border-amber-300 text-amber-800 text-sm rounded-lg px-4 py-3"
      >
        閲覧はできますが、編集・登録はできません。
      </div>

      <!-- [今回追加] 保存時に権限エラーが返ってきた場合の表示（根本的なガード） -->
      <div
        v-if="errors.permission"
        class="mx-6 mt-4 bg-red-50 border border-red-300 text-red-700 text-sm rounded-lg px-4 py-3"
      >
        {{ errors.permission }}
      </div>

      <MemberForm
        v-bind="props"
        :errors="errors"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </div>
  </AppLayout>
</template>
