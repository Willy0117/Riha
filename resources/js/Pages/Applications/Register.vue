<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  defaultFuneralDatetime: String,
  minFuneralDatetime: String,
  application_date: String,
  delivery_date: String,
  user: Object,
})

const form = useForm({
    application_date: props.application_date,
    delivery_date: props.delivery_date,
    staff_name: '',
    vigil_datetime: '',
    funeral_datetime: props.defaultFuneralDatetime,
    deceased_name: '',
    deceased_furigana: '',
    age_at_death: '',
    gender: '',
    chief_mourner_name: '',
    relationship: '',
    traits: [],
    special_notes: '',
    text_color: '',
    bg_color: '',
    remarks: '',
    // --- 故人様詳細 ---
    deceased_name: '',
    deceased_furigana: '',
    age_at_death: '', // 享年（満 歳）
    gender: '',
    
    // --- 配偶者・家族情報 ---
    spouse_status: '', // 有・無・死別
    spouse_count: '',  // 有の場合の人数
    children_count: '',
    grandchildren_count: '',

    // --- 喪主様情報 ---
    chief_mourner_name: '', // 喪主様お名前
    relationship_to_deceased: '', // 故人様とのご関係

    // --- その他項目 ---
    traits: [],
    special_notes: '',
    // ... 略    
});

const traitsOptions = [
    '優しい', '明朗', '温和', '誠実', '思いやり', '面倒見良い', '忍耐強い',
    '親切', '真面目', '努力家', '積極的', '責任感が強い', '世話好き'
];

const submit = () => {
    form.post(route('applications.store'), {
        errorBag: 'createApplication',
        preserveScroll: true,
    });
};
</script>

<template>
  <AppLayout>
  <template #header>{{ t('application') }}</template>
    <FormSection @submitted="submit">
        <template #form>
          <div class="col-span-2 sm:col-span-2 bg-gray-100 p-4 rounded mb-4">
            <p>申込日時：{{ application_date }}</p>
            <p>納期予定：{{ delivery_date }}</p>
          </div>
    
          <!-- 申込基本情報 -->
          <div class="col-span-2 sm:col-span-2">
              <InputLabel for="funeral" value="葬儀 日時" />
              <TextInput
                  v-model="form.funeral_datetime"
                  type="datetime-local"
                  :min="minFuneralDatetime"
                  class="mt-1 block w-full"
                />
          </div>
          <div class="col-span-2 sm:col-span-2">
              <InputLabel for="staff_name" value="担当者名" />
              <TextInput id="staff_name" v-model="form.staff_name" type="text" class="mt-1 block w-full" />
          </div>

            <!-- 故人様情報 -->
            <div class="col-span-6 border-t border-gray-200 pt-4 mt-4 text-sm font-bold text-gray-700">喪主様情報</div>

              <!-- 喪主様情報 -->
            <div class="col-span-4 sm:col-span-2 border-gray-300">
                <InputLabel for="chief_mourner_name" value="喪主様お名前" />
                <TextInput id="chief_mourner_name" v-model="form.chief_mourner_name" type="text" class="mt-1 block w-full" />
            </div>

            <div class="col-span-1 sm:col-span-1">
                <InputLabel for="relationship" value="故人様とのご関係" />
                <TextInput id="relationship" v-model="form.relationship_to_deceased" type="text" class="mt-1 block w-full" placeholder="例：長男、妻" />
            </div>
   <!-- 故人様基本情報 -->
            <div class="col-span-6">
                <h2 class="font-semibold border-b pb-2 mb-4">故人様基本情報</h2>
            </div>

            <!-- 氏名 -->
            <div class="col-span-4">
                <InputLabel for="deceased_name" value="故人様お名前" />
                    <TextInput
                        id="deceased_name"
                        v-model="form.deceased_name"
                        type="text"
                        placeholder="氏名"
                        class="w-full"
                    />
            </div>
            <InputError :message="form.errors.deceased_name" class="mt-2" />

            <!-- 性別 -->
            <div class="col-span-1">
                <InputLabel value="性別" />
                <div class="mt-2 flex gap-6">
                    <label class="flex items-center gap-2">
                        <input type="radio" v-model="form.gender" value="男"
                              class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm">男</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" v-model="form.gender" value="女"
                              class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm">女</span>
                    </label>
                </div>
            </div>

            <!-- 年齢 -->
            <div class="col-span-1">
                <InputLabel for="age_at_death" value="年齢" />
                <div class="mt-1 flex items-center gap-2">
                    <TextInput
                        id="age_at_death"
                        v-model="form.age_at_death"
                        type="number"
                        class="w-full"
                    />
                    <span class="text-sm text-gray-500">歳</span>
                </div>
            </div>
            <div class="col-span-2">
                <InputLabel for="deceased_name" value="ふりなが" />
                    <TextInput
                        v-model="form.deceased_furigana"
                        type="text"
                        placeholder="ふりがな"
                        class="w-full"
                    />
            </div>
            <InputError :message="form.errors.deceased_furigana" class="mt-2" />
            <!-- 配偶者 -->
            <div class="col-span-2">
                <InputLabel value="配偶者" />
                <div class="mt-2 flex flex-wrap items-center gap-6">
                    <label class="flex items-center gap-2">
                        <input type="radio" v-model="form.spouse_status" value="有"
                              class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm">有</span>
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="radio" v-model="form.spouse_status" value="無"
                              class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm">無</span>
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="radio" v-model="form.spouse_status" value="死別"
                              class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm">死別</span>
                    </label>

                    <div v-if="form.spouse_status === '有'"
                        class="flex items-center gap-2 bg-gray-50 px-3 py-1 rounded">
                        <TextInput
                            v-model="form.spouse_count"
                            type="number"
                            class="w-16 text-sm"
                        />
                        <span class="text-sm text-gray-600">人</span>
                    </div>
                </div>
            </div>

            <!-- 子・孫 -->
            <div class="col-span-2">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <InputLabel value="子" />
                        <div class="mt-1 flex items-center gap-2">
                            <TextInput v-model="form.children_count" type="number" class="w-full" />
                            <span class="text-sm text-gray-500">人</span>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="孫" />
                        <div class="mt-1 flex items-center gap-2">
                            <TextInput v-model="form.grandchildren_count" type="number" class="w-full" />
                            <span class="text-sm text-gray-500">人</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 性別 -->
 


            <!-- 性格（チェックボックス） -->
            <div class="col-span-6">
                <InputLabel value="故人様の性格" />
                <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-y-2">
                    <div v-for="trait in traitsOptions" :key="trait" class="flex items-center">
                        <Checkbox :id="trait" v-model:checked="form.traits" :value="trait" />
                        <label :for="trait" class="ml-2 text-sm text-gray-600">{{ trait }}</label>
                    </div>
                </div>
            </div>

            <!-- 自由記述 -->
            <div class="col-span-6">
                <InputLabel for="special_notes" value="故人様に対する特記事項（お人柄など）" />
                <textarea id="special_notes" v-model="form.special_notes" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" rows="3"></textarea>
            </div>

            <div class="col-span-6">
                <InputLabel for="remarks" value="備考欄" />
                <textarea id="remarks" v-model="form.remarks" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" rows="2"></textarea>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="mr-3">
                送信完了
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                申込内容を送信
            </PrimaryButton>
        </template>
    </FormSection>
  </AppLayout>    
</template>
