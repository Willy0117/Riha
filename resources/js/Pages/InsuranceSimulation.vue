<script setup>
// import AppLayout from '@/Layouts/AppLayout.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';

// シミュレーションフォームの状態管理
const form = useForm({
    completion_amount: 10000, // 完工高（万円）初期値
    industry_risk: 'medium', // 業種リスク 初期値
    liability_limit: '300M_100M', // 支払限度額 初期値
    deductible: 'none', // 免責金額 初期値
    entrusted_property_coverage: false, // 受託物賠償特約 初期値
});

// 計算結果のプレミアム
const calculatedPremium = ref(0);
const loading = ref(false);
const errors = ref({});

// プレミアムを計算する非同期関数
const calculatePremium = async () => {
    loading.value = true;
    errors.value = {}; // エラーをクリア

    try {
        const response = await axios.post('/api/calculate-premium', form.data());
        calculatedPremium.value = response.data.premium;
    } catch (error) {
        console.error("Calculation error:", error);
        calculatedPremium.value = 0; // エラー時は0にリセット
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.messages; // バリデーションエラー
        } else {
            errors.value = { general: "計算中にエラーが発生しました。再度お試しください。" };
        }
    } finally {
        loading.value = false;
    }
};

// --- ここを修正 ---
// form.data() の代わりに、form.completion_amount など、フォーム内の個別のプロパティを監視するか、
// useForm が提供するリアクティブなプロパティを適切に監視します。
// useForm は内部でリアクティブなオブジェクトを返すため、以下のようにシンプルに記述できます。
watch(
    () => form.data(), // form.data() の最新のスナップショットを返す関数を監視
    (newData, oldData) => {
        // データが実際に変更された場合のみ計算を実行
        if (JSON.stringify(newData) !== JSON.stringify(oldData)) {
            calculatePremium();
        }
    },
    { deep: true } // オブジェクトの深い変更を監視
);

// コンポーネントがマウントされたときに初回計算を実行
calculatePremium();
</script>

<template>
    <GuestLayout title="保険料シミュレーション">
        <Head title="保険料シミュレーション" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                賠償責任保険料シミュレーション
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-xl font-bold mb-6">シミュレーション条件入力</h3>

                    <div v-if="Object.keys(errors).length" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        <p class="font-bold">入力エラー:</p>
                        <ul class="mt-2 list-disc list-inside">
                            <li v-for="(errorList, field) in errors" :key="field">
                                <span v-if="field !== 'general'">{{ field }}: </span>
                                <span v-for="err in errorList" :key="err">{{ err }}</span>
                            </li>
                            <li v-if="errors.general">{{ errors.general }}</li>
                        </ul>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="completion_amount" class="block text-sm font-medium text-gray-700">直近1年間の完工高（万円）</label>
                            <input
                                type="number"
                                id="completion_amount"
                                v-model.number="form.completion_amount"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                min="0"
                                step="100"
                            >
                            <p class="mt-1 text-sm text-gray-500">（例: 10000で1億円）</p>
                        </div>

                        <div>
                            <label for="industry_risk" class="block text-sm font-medium text-gray-700">主な事業内容（リスク）</label>
                            <select
                                id="industry_risk"
                                v-model="form.industry_risk"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            >
                                <option value="low">軽微な改修、内装工事など (低リスク)</option>
                                <option value="medium">一般建築、土木、電気工事など (中リスク)</option>
                                <option value="high">大規模高所作業、特殊解体など (高リスク)</option>
                            </select>
                        </div>

                        <div>
                            <label for="liability_limit" class="block text-sm font-medium text-gray-700">支払限度額（対人 / 対物）</label>
                            <select
                                id="liability_limit"
                                v-model="form.liability_limit"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            >
                                <option value="100M_100M">対人1億円 / 対物1億円</option>
                                <option value="300M_100M">対人3億円 / 対物1億円</option>
                                <option value="500M_300M">対人5億円 / 対物3億円</option>
                                <option value="unlimited_500M">対人無制限 / 対物5億円</option>
                            </select>
                        </div>

                        <div>
                            <label for="deductible" class="block text-sm font-medium text-gray-700">免責金額（自己負担額）</label>
                            <select
                                id="deductible"
                                v-model="form.deductible"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            >
                                <option value="none">なし</option>
                                <option value="100k">10万円</option>
                                <option value="300k">30万円</option>
                            </select>
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <div class="flex items-center">
                                <input
                                    type="checkbox"
                                    id="entrusted_property_coverage"
                                    v-model="form.entrusted_property_coverage"
                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                >
                                <label for="entrusted_property_coverage" class="ml-2 block text-sm font-medium text-gray-700">
                                    受託物賠償特約（預かった資材などの損壊を補償）
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <h3 class="text-xl font-bold mb-4">概算保険料</h3>
                        <div class="text-4xl font-extrabold text-blue-600">
                            <span v-if="loading">計算中...</span>
                            <span v-else>{{ calculatedPremium.toLocaleString() }} 円</span>
                        </div>
                        <p class="mt-4 text-sm text-gray-600">
                            ※ このシミュレーション結果はあくまで概算であり、実際の保険料とは異なる場合があります。正確な保険料は、別途お見積もりをご依頼ください。
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            @click="() => {}"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            この条件で見積もりを依頼する
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </GuestLayout>
</template>