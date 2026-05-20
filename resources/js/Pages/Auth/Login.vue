<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Checkbox from '@/Components/Checkbox.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
    canResetPassword: Boolean,
    status: String,
})

const form = useForm({
    username: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head :title="t('login')" />

    <div class="min-h-screen bg-slate-50 flex">

        <!-- LEFT -->
        <div
            class="hidden lg:flex lg:w-1/2 relative overflow-hidden
                   bg-white border-r border-slate-200"
        >

            <!-- subtle background -->
            <div
                class="absolute top-[-120px] left-[-120px]
                       w-[300px] h-[300px]
                       bg-emerald-100 rounded-full blur-3xl opacity-60"
            />

            <div class="relative z-10 flex flex-col justify-between p-16 w-full">

                <div>

                    <!-- logo -->
                    <div
                        class="flex items-center justify-center
                               w-14 h-14 rounded-2xl
                               bg-emerald-50 border border-emerald-100"
                    >
                        <span class="text-2xl font-bold text-emerald-700">
                            I
                        </span>
                    </div>

                    <!-- title -->
                    <h1 class="mt-8 text-5xl font-bold tracking-tight text-slate-900">
                        Into Bridge
                    </h1>

                    <p class="mt-5 text-lg leading-relaxed text-slate-500 max-w-md">
                        学会・団体運営を、
                        シンプルでスマートに。
                    </p>

                    <!-- features -->
                    <div class="mt-12 space-y-6">

                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-emerald-500 shrink-0" />
                            <div>
                                <p class="font-medium text-slate-900">
                                    会員管理
                                </p>
                                <p class="text-sm text-slate-500">
                                    登録・更新・検索を一元管理
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-emerald-500 shrink-0" />
                            <div>
                                <p class="font-medium text-slate-900">
                                    会費管理
                                </p>
                                <p class="text-sm text-slate-500">
                                    請求・入金・未納ステータスを管理
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 mt-2 rounded-full bg-emerald-500 shrink-0" />
                            <div>
                                <p class="font-medium text-slate-900">
                                    認定管理
                                </p>
                                <p class="text-sm text-slate-500">
                                    申請・審査・更新をオンライン化
                                </p>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- footer -->
                <div class="text-sm text-slate-400">
                    © 2026 Vision Bridge
                </div>

            </div>

        </div>

        <!-- RIGHT -->
        <div
            class="flex-1 flex items-center justify-center
                   px-6 py-10 sm:px-10"
        >

            <div class="w-full max-w-md">

                <!-- mobile -->
                <div class="lg:hidden text-center mb-10">

                    <div
                        class="mx-auto flex items-center justify-center
                               w-14 h-14 rounded-2xl
                               bg-emerald-50 border border-emerald-100"
                    >
                        <span class="text-2xl font-bold text-emerald-700">
                            I
                        </span>
                    </div>

                    <h1 class="mt-4 text-3xl font-bold text-slate-900">
                        Vision Bridge
                    </h1>

                </div>

                <!-- login card -->
                <div
                    class="bg-white border border-slate-200
                           rounded-3xl shadow-sm
                           p-8 sm:p-10"
                >

                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-slate-900">
                            {{ t('login') }}
                        </h2>

                        <p class="mt-2 text-sm text-slate-500">
                            アカウント情報を入力してください
                        </p>
                    </div>

                    <!-- status -->
                    <div
                        v-if="status"
                        class="mb-6 rounded-xl bg-green-50
                               border border-green-200
                               px-4 py-3 text-sm text-green-700"
                    >
                        {{ status }}
                    </div>

                    <form
                        class="space-y-6"
                        @submit.prevent="submit"
                    >

                        <!-- username -->
                        <div>
                            <InputLabel
                                for="username"
                                :value="t('username')"
                                class="mb-2 text-sm font-medium text-slate-700"
                            />

                            <TextInput
                                id="username"
                                v-model="form.username"
                                type="text"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="username"
                                class="w-full h-12 rounded-xl
                                       border-slate-300
                                       focus:border-emerald-500
                                       focus:ring-emerald-500"
                            />

                            <InputError
                                class="mt-2"
                                :message="form.errors.username"
                            />
                        </div>

                        <!-- password -->
                        <div>
                            <InputLabel
                                for="password"
                                :value="t('password')"
                                class="mb-2 text-sm font-medium text-slate-700"
                            />

                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full h-12 rounded-xl
                                       border-slate-300
                                       focus:border-emerald-500
                                       focus:ring-emerald-500"
                            />

                            <InputError
                                class="mt-2"
                                :message="form.errors.password"
                            />
                        </div>

                        <!-- remember -->
                        <div class="flex items-center justify-between">

                            <label class="flex items-center gap-2">
                                <Checkbox
                                    v-model:checked="form.remember"
                                    name="remember"
                                />

                                <span class="text-sm text-slate-600">
                                    {{ t('remember') }}
                                </span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm text-emerald-600 hover:text-emerald-700"
                            >
                                {{ t('forgot_password') }}
                            </Link>

                        </div>

                        <!-- submit -->
                        <PrimaryButton
                            class="w-full h-12 justify-center
                                   rounded-xl text-sm font-semibold
                                   bg-emerald-600 hover:bg-emerald-700
                                   transition"
                            :class="{ 'opacity-50': form.processing }"
                            :disabled="form.processing"
                        >
                            {{ t('login') }}
                        </PrimaryButton>

                    </form>

                </div>

            </div>

        </div>

    </div>
</template>