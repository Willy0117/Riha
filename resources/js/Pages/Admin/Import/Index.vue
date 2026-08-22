<script setup>
import { ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Separator } from '@/components/ui/separator'
import AppLayout from '@/Layouts/Admin/AppLayout.vue'

const page = usePage()

const file = ref(null)
const importType = ref(null) // 'member' | 'invoice'
const dragging = ref(false)
const uploading = ref(false)
const fileError = ref(null)
const typeError = ref(null)
const result = ref(null)

watch(
    () => page.props.flash?.result,
    (val) => {
        if (val) result.value = val
    },
    { immediate: true }
)

const importTypes = [
    {
        value: 'member',
        label: '会員情報',
        description: 'members・住所・学歴・役職歴など関連テーブルを一括登録・更新',
        badge: 'members',
    },
    {
        value: 'invoice',
        label: '請求情報',
        description: 'invoices 請求・入金情報を一括登録・更新',
        badge: 'invoices',
    },
]

function onFileChange(e) {
    const f = e.target.files[0]
    if (f) setFile(f)
}

function onDrop(e) {
    dragging.value = false
    const f = e.dataTransfer.files[0]
    if (f) setFile(f)
}

function setFile(f) {
    if (!isExcel(f)) {
        fileError.value = 'xlsx / xls ファイルを選択してください'
        return
    }
    fileError.value = null
    file.value = f
}

function clearAll() {
    file.value = null
    importType.value = null
    fileError.value = null
    typeError.value = null
}

function submit() {
    fileError.value = null
    typeError.value = null

    if (!file.value) {
        fileError.value = 'ファイルを選択してください'
        return
    }
    if (!importType.value) {
        typeError.value = 'インポート種別を選択してください'
        return
    }
    // ファイル名と種別の整合性チェック
    const fileName = file.value.name
    if (importType.value === 'member' && !fileName.includes('jsrr_member')) {
        fileError.value = '会員情報には jsrr_member のファイルを選択してください'
        return
    }
    if (importType.value === 'invoice' && !fileName.includes('jsrr_invoice')) {
        fileError.value = '請求情報には jsrr_invoice のファイルを選択してください'
        return
    }
    
    uploading.value = true
    result.value = null

    const form = new FormData()
    form.append('file', file.value)
    form.append('type', importType.value)

    router.post('/admin/import', form, {
        onFinish: () => {
            uploading.value = false
            clearAll()
        },
        onError: (errors) => {
            fileError.value = errors.file ?? null
            typeError.value = errors.type ?? null
            uploading.value = false
        },
    })
}

function isExcel(f) {
    return ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'].includes(f.type)
        || /\.(xlsx|xls)$/.test(f.name)
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const resultLabel = () => {
    if (!result.value) return ''
    return result.value.type === 'member' ? '会員情報' : '請求情報'
}
</script>

<template>
    <AppLayout>
        <div class="py-10 px-4">
            <div class="max-w-4xl mx-auto space-y-6">

                <!-- タイトル -->
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">データインポート</h1>
                    <p class="text-muted-foreground text-sm mt-1">Excel ファイル（.xlsx / .xls）をアップロードしてください</p>
                </div>

                <!-- 結果表示 -->
                <Alert v-if="result" :variant="result.errors?.length ? 'destructive' : 'default'">
                    <AlertTitle>{{ resultLabel() }}インポート完了</AlertTitle>
                    <AlertDescription>
                        <div class="flex gap-8 mt-3">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">{{ result.insert }}</div>
                                <div class="text-xs text-muted-foreground">新規登録</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">{{ result.update }}</div>
                                <div class="text-xs text-muted-foreground">更新</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-muted-foreground">{{ result.skip }}</div>
                                <div class="text-xs text-muted-foreground">スキップ</div>
                            </div>
                            <div v-if="result.errors?.length" class="text-center">
                                <div class="text-2xl font-bold text-destructive">{{ result.errors.length }}</div>
                                <div class="text-xs text-muted-foreground">エラー</div>
                            </div>
                        </div>

                        <template v-if="result.errors?.length">
                            <Separator class="my-3" />
                            <p class="text-sm font-medium mb-2">エラー詳細</p>
                            <ScrollArea class="h-40 rounded border bg-background">
                                <div class="p-2 space-y-1">
                                    <div v-for="(err, i) in result.errors" :key="i"
                                        class="flex gap-3 text-xs px-2 py-1.5 rounded hover:bg-muted">
                                        <span class="font-mono font-semibold whitespace-nowrap text-muted-foreground">
                                            {{ err.code ?? '-' }}
                                            <span v-if="err.fiscal_year"> / {{ err.fiscal_year }}</span>
                                        </span>
                                        <span>{{ err.message }}</span>
                                    </div>
                                </div>
                            </ScrollArea>
                        </template>
                    </AlertDescription>
                </Alert>

                <!-- メインカード -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">インポート</CardTitle>
                        <CardDescription>ファイルを選択後、インポート種別を指定して実行してください</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">

                        <!-- Step 1: ファイル選択 -->
                        <div class="space-y-2">
                            <p class="text-sm font-medium">① ファイルを選択</p>
                            <label
                                class="flex flex-col items-center justify-center w-full h-40 rounded-lg border-2 border-dashed cursor-pointer transition-colors"
                                :class="dragging
                                    ? 'border-primary bg-primary/5'
                                    : file
                                        ? 'border-primary/50 bg-primary/5'
                                        : 'border-border hover:border-primary/50 hover:bg-muted/60'"
                                @dragover.prevent="dragging = true"
                                @dragleave="dragging = false"
                                @drop.prevent="onDrop">
                                <input type="file" class="hidden" accept=".xlsx,.xls" @change="onFileChange" />

                                <template v-if="!file">
                                    <svg class="w-10 h-10 text-muted-foreground/50 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <p class="text-sm text-muted-foreground">
                                        ファイルをドロップ または <span class="text-primary font-medium">クリックして選択</span>
                                    </p>
                                    <p class="text-xs text-muted-foreground/70 mt-1">.xlsx / .xls（最大 10MB）</p>
                                </template>

                                <template v-else>
                                    <svg class="w-9 h-9 text-primary mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-sm font-medium text-primary">{{ file.name }}</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ formatBytes(file.size) }}</p>
                                </template>
                            </label>
                            <p v-if="fileError" class="text-sm text-destructive">{{ fileError }}</p>
                        </div>

                        <!-- Step 2: インポート種別選択 -->
                        <div class="space-y-2">
                            <p class="text-sm font-medium">② インポート種別を選択</p>
                            <div class="grid grid-cols-2 gap-4">
                                <button
                                    v-for="t in importTypes"
                                    :key="t.value"
                                    type="button"
                                    class="relative text-left rounded-lg border-2 p-5 transition-colors focus:outline-none"
                                    :class="importType === t.value
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/40 hover:bg-muted/40'"
                                    @click="importType = t.value">
                                    <span v-if="importType === t.value"
                                        class="absolute top-3 right-3 w-4 h-4 rounded-full bg-primary flex items-center justify-center">
                                        <svg class="w-2.5 h-2.5 text-primary-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                    <p class="font-semibold text-sm">{{ t.label }}</p>
                                    <Badge variant="secondary" class="mt-1 text-xs">{{ t.badge }}</Badge>
                                    <p class="text-xs text-muted-foreground mt-2 leading-relaxed">{{ t.description }}</p>
                                </button>
                            </div>
                            <p v-if="typeError" class="text-sm text-destructive">{{ typeError }}</p>
                        </div>

                        <Separator />

                        <!-- アクション -->
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" :disabled="uploading" @click="clearAll">
                                クリア
                            </Button>
                            <Button variant="default" :disabled="uploading" @click="submit">
                                <svg v-if="uploading" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                {{ uploading ? 'インポート中...' : 'インポート実行' }}
                            </Button>
                        </div>

                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>
</template>