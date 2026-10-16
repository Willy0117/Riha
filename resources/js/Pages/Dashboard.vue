<script setup>
import dayjs from 'dayjs'
import AppLayout from '@/Layouts/AppLayout.vue';
import MyPageNotice from './MyPageNotice.vue'
import { useI18n } from 'vue-i18n'
import RehabApplicationsTable from '@/Components/RehabApplicationsTable.vue'
import { usePage } from '@inertiajs/vue3'

const { t } = useI18n()
const { props } = usePage()

const user = props.user
const applications = props.applications
const allSchedules = props.allSchedules ?? []

</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>{{ user.name }}</template>
        <MyPageNotice />

        <!-- [今回追加] 申請受付スケジュール一覧 -->
        <div v-if="allSchedules.length > 0" class="bg-white border border-gray-200 rounded-xl p-5 m-6">
          <h3 class="text-sm font-bold text-gray-700 mb-3">申請受付スケジュールについて</h3>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-gray-500">
                <th class="py-2 text-left">期</th>
                <th class="py-2 text-left">応募期間</th>
                <th class="py-2 text-left">審査期間</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in allSchedules" :key="s.period_name" class="border-b">
                <td class="py-2 font-semibold">{{ s.period_name }}</td>
                <td class="py-2 text-gray-700">
                  {{ dayjs(s.application_start).format('YYYY-MM-DD') }}〜{{ dayjs(s.application_end).format('YYYY-MM-DD') }}
                </td>
                <td class="py-2 text-gray-700">
                  {{ dayjs(s.subleader_start).format('YYYY-MM-DD') }}〜{{ dayjs(s.chief_end).format('YYYY-MM-DD') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
    </AppLayout>
</template>
