<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import Checkbox from '@/Components/Checkbox.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
    canResetPassword: Boolean,
    status: String,
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('admin.login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head :title="t('login')" />

    <div class="min-h-screen bg-slate-100 flex">
        <!-- Left Branding -->
        <div
            class="hidden lg:flex lg:w-1/2 relative
                bg-white border-r border-slate-200"
        >
        <div class="relative z-10 flex flex-col justify-between p-14 w-full">

            <div>
                <div class="w-12 h-12 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold">
                    I
                </div>

                <h1 class="mt-6 text-3xl font-semibold text-slate-900">
                    IntoBridge
                </h1>

                <p class="mt-3 text-sm text-slate-500 leading-relaxed">
                    学会・団体の運営・認定・会費管理を一元化します
                </p>

                <div class="mt-10 space-y-5">

                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 mt-2 rounded-full bg-slate-400 shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">会員管理</p>
                            <p class="text-sm text-slate-400">登録・更新・検索を一元管理</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 mt-2 rounded-full bg-slate-400 shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">会費管理</p>
                            <p class="text-sm text-slate-400">請求・入金・ステータス管理</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 mt-2 rounded-full bg-slate-400 shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">認定管理</p>
                            <p class="text-sm text-slate-400">申請・審査・更新をオンライン化</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 mt-2 rounded-full bg-slate-400 shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">オンライン対応</p>
                            <p class="text-sm text-slate-400">Web申請・通知・デジタル運用</p>
                        </div>
                    </div>

                </div>
            </div>

                <div class="text-xs text-slate-400">
                    © 2026 Vision Bridge
                </div>
            </div>
        </div>
        <!-- Right Login -->
        <div
            class="flex-1 flex items-center justify-center
                   px-6 py-10 sm:px-10"
        >
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden mb-10 text-center">
                    <div
                        class="mx-auto flex items-center justify-center
                               w-14 h-14 rounded-2xl
                               bg-slate-900 text-white"
                    >
                        <span class="text-2xl font-bold">I</span>
                    </div>

                    <h1 class="mt-4 text-3xl font-bold text-slate-900">
                        IntoBridge
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        学会・団体運営をもっとシンプルに
                    </p>
                </div>

                <!-- Card -->
                <div
                    class="bg-white rounded-3xl shadow-xl
                           border border-slate-200/60
                           p-6 sm:p-10"
                >
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-slate-900">
                            {{ t('login') }}
                        </h2>

                        <p class="mt-2 text-sm text-sm text-slate-500">
                            アカウント情報を入力してください
                        </p>
                    </div>

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
                        <!-- Email -->
                        <div>
                            <InputLabel
                                for="email"
                                :value="t('email')"
                                class="mb-2 text-sm font-medium text-slate-700"
                            />

                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                class="w-full h-12 rounded-xl
                                       border-slate-300
                                       focus:border-indigo-500
                                       focus:ring-indigo-500"
                                placeholder="you@example.com"
                            />

                            <InputError
                                class="mt-2"
                                :message="form.errors.email"
                            />
                        </div>

                        <!-- Password -->
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
                                class="w-full h-12 rounded-xl
                                       border-slate-300
                                       focus:border-indigo-500
                                       focus:ring-indigo-500"
                                placeholder="••••••••"
                            />

                            <InputError
                                class="mt-2"
                                :message="form.errors.password"
                            />
                        </div>

                        <!-- Remember + Forgot -->
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
                                class="text-sm text-indigo-600
                                       hover:text-indigo-500
                                       transition"
                            >
                                {{ t('forgot_password') }}
                            </Link>
                        </div>

                        <!-- Submit -->
                        <PrimaryButton
                            class="w-full h-12 justify-center
                                   rounded-xl text-sm font-semibold
                                   bg-indigo-600 hover:bg-indigo-700
                                   transition-all duration-200"
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