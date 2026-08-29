<template>
  <AppLayout :title="$t('pdf_upload')">

    <template #header>{{ $t('pdf_upload') }}</template>
    <div class="max-w-none mx-auto p-8 flex flex-col gap-6 font-sans text-[#1a1a2e]">

      <!-- [今回追加] 委員長が却下した場合、最上部に表示（×で閉じられる） -->
      <div
        v-if="showRejectNotice && props.cycle?.status === 'reject' && props.cycle?.reason"
        class="relative bg-red-50 border border-red-500 rounded-xl p-4 pr-10"
      >
        <button
          type="button"
          class="absolute top-3 right-3 text-red-400 hover:text-red-600"
          @click="showRejectNotice = false"
        >
          <X class="w-4 h-4" />
        </button>
        <p class="text-sm font-semibold text-red-700 mb-1">今回の更新申請は却下されました</p>
        <p class="text-sm text-red-600 whitespace-pre-wrap">{{ props.cycle.reason }}</p>
      </div>

      <!-- 更新手続きフェーズ（新規追加レイアウト） -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div class="border border-gray-300 rounded-lg px-4 py-2.5">
            <p class="flex items-center gap-1.5 text-sm text-gray-500 mb-1">
              <Calendar class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
              認定期間 {{ cycleYears }} 年間
            </p>
            <p class="text-lg font-bold text-gray-900">
              {{ props.cycle?.start_date ? dayjs(props.cycle.start_date).format('YYYY-MM-DD') : '' }}
              〜
              {{ props.cycle?.end_date ? dayjs(props.cycle.end_date).format('YYYY-MM-DD') : '' }}
            </p>
            <p v-if="cycleYears > 5" class="text-xs text-red-500 mt-1">
              （原則5年だが延長されている扱い）
            </p>
          </div>
          <div v-if="isWithinRenewalPeriod" class="bg-orange-50 rounded-lg px-4 py-2.5">
            <p class="flex items-center gap-1.5 text-sm text-orange-500 font-semibold mb-1">
              <Clock class="w-3.5 h-3.5 text-orange-400 flex-shrink-0" />
              更新申請受付期間
            </p>
            <p class="text-lg font-bold text-orange-500">
              {{ props.cycle?.renewal_start_date ? dayjs(props.cycle.renewal_start_date).format('YYYY-MM-DD') : '' }}
              〜
              {{ props.cycle?.renewal_end_date ? dayjs(props.cycle.renewal_end_date).format('YYYY-MM-DD') : '' }}
            </p>
          </div>

          <!-- [今回追加] 現在の期区分（応募期間・審査期間） -->
          <div v-if="props.schedule" class="border border-blue-200 bg-blue-50 rounded-lg px-4 py-2.5">
            <p class="flex items-center gap-1.5 text-sm text-blue-600 font-semibold mb-1.5">
              <Calendar class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" />
              {{ props.schedule.period_name }}
            </p>
            <div class="space-y-1">
              <p class="text-xs text-blue-700">
                <span class="font-semibold">応募期間：</span>
                {{ dayjs(props.schedule.application_start).format('YYYY-MM-DD') }}〜{{ dayjs(props.schedule.application_end).format('YYYY-MM-DD') }}
              </p>
              <p class="text-xs text-blue-700">
                <span class="font-semibold">審査期間：</span>
                {{ dayjs(props.schedule.chief_start).format('YYYY-MM-DD') }}〜{{ dayjs(props.schedule.chief_end).format('YYYY-MM-DD') }}
              </p>
            </div>
          </div>

          <!-- [今回追加] ステータスバッジ（独立カード・期間内のみ表示） -->
          <div v-if="isWithinRenewalPeriod" class="border border-gray-300 rounded-lg px-4 py-2.5">
            <p class="flex items-center gap-1.5 text-sm text-gray-500 mb-1">
              ステータス
            </p>
            <div class="flex items-center gap-2 flex-wrap">
              <span
                class="inline-block w-fit text-base px-3 py-1.5 rounded-full font-semibold"
                :class="cycleStatusBadgeClass"
              >
                {{ cycleStatusLabel }}
              </span>
              <button
                v-if="isDeclined"
                type="button"
                class="text-xs px-2.5 py-1 rounded-full font-semibold bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 transition"
                @click="onDeclineButtonClick"
              >
                更新手続きを再開する
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ここから、更新を辞退している場合はグレーアウト -->
      <div :class="['flex flex-col gap-6', isNoUpdate ? 'opacity-40 pointer-events-none' : '']">

      <!-- 更新手続きフェーズ ステッパー -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <p class="text-sm font-semibold text-gray-500 mb-5 text-center">更新手続きフェーズ（現在の進捗状況）</p>

        <div class="flex items-center">
          <template v-for="(phase, idx) in phases" :key="phase">
            <div class="flex flex-col items-center flex-1">
              <div :class="phaseCircleClass(idx)">
                <CheckCircle2 v-if="idx < currentPhaseIndex" class="w-4 h-4" />
                <span v-else>{{ idx + 1 }}</span>
              </div>
              <span :class="['text-sm mt-2 text-center whitespace-nowrap', idx === currentPhaseIndex ? 'font-bold text-gray-900' : 'text-gray-500']">
                {{ phase }}
              </span>
            </div>
            <div v-if="idx < phases.length - 1" class="flex-1 h-px bg-gray-200 -mt-6"></div>
          </template>
        </div>
      </div>

      <!-- 上段：資格更新状況 -->
      <div class="grid grid-cols-6 gap-6">

        <!-- 資格更新状況 -->
        <div class="col-span-6 bg-white rounded-xl border border-gray-200 p-6">
          <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

            <!-- 左側：① 更新要件充足チェック（横並び3カード） -->
            <div class="lg:col-span-4 border border-gray-200 rounded-xl p-4">
              <p class="text-sm font-semibold text-gray-500 mb-3">① 更新要件充足チェック</p>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

                <!-- (a) 学術集会への参加 -->
                <div class="relative border border-emerald-300 rounded-xl p-4">
                  <div v-if="false && hasConferenceCount" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center shadow">
                    <Check class="w-3.5 h-3.5 text-white" stroke-width="3" />
                  </div>
                  <p class="font-semibold text-gray-900 text-base mb-1 pr-4">(a) 腎リハ学術集会に2回以上参加</p>
                  <p class="text-sm text-gray-500 mb-3">
                    5年間に日本腎臓リハビリテーション学会学術集会に2回以上参加している（参加証明書を提出してください）。
                  </p>
                  <div class="flex items-center gap-3">
                    <div class="flex items-baseline gap-0.5 flex-shrink-0">
                      <span class="text-lg font-bold text-blue-600">{{ props?.conference_count ?? 0 }}</span>
                      <span class="text-sm text-gray-500">/ 2 回</span>
                    </div>
                    <div class="flex flex-col gap-1.5 flex-1">
                      <div class="bg-amber-50 border border-amber-200 rounded-lg text-center py-1.5">
                        <span class="text-sm text-amber-700 font-semibold">蓄積中：{{ props?.pendingConferenceCount ?? 0 }} 回</span>
                      </div>
                      <div class="bg-emerald-50 border border-emerald-200 rounded-lg text-center py-1.5">
                        <span class="text-sm text-emerald-700 font-semibold">承認済み：{{ props?.approvedConferenceCount ?? 0 }} 回</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- (b) 単位取得 -->
                <div class="relative border border-emerald-300 rounded-xl p-4">
                  <div v-if="false && isCreditsRequirementMet" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center shadow">
                    <Check class="w-3.5 h-3.5 text-white" stroke-width="3" />
                  </div>
                  <p class="font-semibold text-gray-900 text-base mb-1 pr-4">(b) {{ props?.requiredUnits }}単位以上取得</p>
                  <p class="text-sm text-gray-500 mb-3">
                    5年間に{{ props?.requiredUnits }}単位以上取得している（参加証あるいは抄録・論文のコピーを提出してください）。
                  </p>
                  <div class="flex items-center gap-3">
                    <div class="flex items-baseline gap-0.5 flex-shrink-0">
                      <span class="text-lg font-bold text-blue-600">{{ totalCredits }}</span>
                      <span class="text-sm text-gray-500">/ {{ props?.requiredUnits }} 単位</span>
                    </div>
                    <div class="flex flex-col gap-1.5 flex-1">
                      <div class="bg-amber-50 border border-amber-200 rounded-lg text-center py-1.5">
                        <span class="text-sm text-amber-700 font-semibold">蓄積中：{{ props?.pendingTotal ?? 0 }} 単位</span>
                      </div>
                      <div class="bg-emerald-50 border border-emerald-200 rounded-lg text-center py-1.5">
                        <span class="text-sm text-emerald-700 font-semibold">承認済み：{{ props?.approvedTotal ?? 0 }} 単位</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- (c) 年会費完納 -->
                <div class="relative border border-emerald-300 rounded-xl p-4">
                  <div v-if="false && props?.annualFeeStatus" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center shadow">
                    <Check class="w-3.5 h-3.5 text-white" stroke-width="3" />
                  </div>
                  <p class="font-semibold text-gray-900 text-base mb-1 pr-4">(c) 学会年会費の完納</p>
                  <p class="text-sm text-gray-500 mb-3">会費を完納している（更新年度の会費も含む）。</p>
                  <span :class="badgeClasses(props?.annualFeeStatus ? 'met' : 'pending')">
                    <CheckCircle2 v-if="props?.annualFeeStatus" class="w-3.5 h-3.5" />
                    <Clock v-else class="w-3.5 h-3.5" />
                    年会費：{{ annualFeeStatus }}
                  </span>
                  <Button
                    type="button"
                    variant="outline"
                    class="w-full whitespace-nowrap text-blue-600 bg-blue-50 border-blue-200 hover:bg-blue-100 mt-3"
                    @click="showFeeDialog = true"
                  >
                    年会費納入状況
                  </Button>
                </div>
              </div>
            </div>

            <!-- 右側：② 更新申請 / ③ 資格更新料納付（縦並び） -->
            <div class="lg:col-span-1 flex flex-col gap-3">
              <div class="border border-gray-200 rounded-xl p-4">
                <p class="text-sm font-semibold text-gray-500 mb-3">② 更新申請を行う</p>
                <div class="flex flex-col gap-2">
                  <div>
                    <Button
                      v-if="isWithinRenewalPeriod && !applyBadgeState"
                      :disabled="!isEligible"
                      :class="[
                        'w-full whitespace-nowrap',
                        isEligible
                          ? 'bg-blue-600 hover:bg-blue-700 text-white'
                          : 'text-gray-400 bg-gray-100 border border-gray-200'
                      ]"
                      @click="submitUpdateApplication"
                    >
                      更新申請を行う
                    </Button>
                    <span v-else-if="applyBadgeState" :class="badgeClasses(applyBadgeState.tone)">
                      <CheckCircle2 v-if="applyBadgeState.tone === 'met'" class="w-3.5 h-3.5" />
                      <Clock v-else-if="applyBadgeState.tone === 'pending'" class="w-3.5 h-3.5" />
                      <AlertCircle v-else class="w-3.5 h-3.5" />
                      {{ applyBadgeState.label }}
                    </span>
                    <span v-else :class="badgeClasses('neutral')">
                      受付期間外
                    </span>
                  </div>
                  <button
                    v-if="isWithinRenewalPeriod && !isDeclined"
                    type="button"
                    :disabled="props.cycle?.status === 'pending'"
                    class="w-full flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold whitespace-nowrap transition bg-orange-50 text-orange-600 border border-orange-200 hover:bg-orange-100 disabled:text-orange-300 disabled:bg-orange-100 disabled:border-orange-200 disabled:cursor-not-allowed"
                    @click="onDeclineButtonClick"
                  >
                    更新をしない
                  </button>
                </div>
              </div>

              <div class="border border-gray-200 rounded-xl p-4">
                <p class="text-sm font-semibold text-gray-500 mb-3">③ 資格更新料納付</p>
                <span :class="badgeClasses(paymentBadgeToneMap[paymentBadgeState.tone])">
                  <CheckCircle2 v-if="paymentBadgeState.tone === 'paid'" class="w-3.5 h-3.5" />
                  <Clock v-else-if="paymentBadgeState.tone === 'unpaid'" class="w-3.5 h-3.5" />
                  <AlertCircle v-else class="w-3.5 h-3.5" />
                  {{ paymentBadgeState.label }}
                </span>
              </div>
            </div>
          </div>

        </div>
      </div>
      <!-- 提出書類・申請履歴 -->
      <div class="flex items-center gap-3">
        <h2 class="text-lg font-extrabold text-[#1e3a6e] italic">提出書類・申請履歴</h2>
        <Button 
          :disabled="props.cycle?.status === 'pending'"
          :class="[
            'rounded-lg px-5 py-2.5 text-sm font-semibold whitespace-nowrap flex items-center gap-2',
            props.cycle?.status === 'pending'
              ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
              : 'bg-blue-600 hover:bg-blue-700 text-white'
          ]"
          @click="isOpen = true"
        >
          <Upload class="w-4 h-4" /> 新規書類アップロード
        </Button>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-0 overflow-hidden">
        <table class="w-full border-collapse text-[13px]">
          <thead>
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">書類名 / 区分</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">参加日 / 発行日</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">単位</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">ステータス</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">却下理由</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 border-b border-gray-200">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="props.uploads?.length === 0">
              <td colspan="6" class="!p-12 border-b border-gray-100">
                <div class="text-center text-gray-400 text-[13px]">
                  <p>申請履歴がありません</p>
                </div>
              </td>
            </tr>
            <tr v-for="app in props.uploads" :key="app.id">
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">
                <div class="flex items-center gap-2">
                  <FileText class="w-4 h-4 text-gray-400 flex-shrink-0" />
                  <div>
                    <p class="font-medium">
                      <template v-if="app.session">第{{ app.session }}回 </template>{{ app.credit_conference_name }}
                    </p>
                    <Badge variant="outline" class="text-xs mt-1">{{ app.role_name }}</Badge>
                    <p class="text-xs text-blue-600 font-semibold mt-1">認定学会: {{ app.credit_category_name }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">{{ app.issued_date }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">{{ app.points }} {{ t('instructors.point') }}</td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">
                <span
                  class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
                  :class="{
                    'bg-gray-50 text-gray-500 border-gray-300': app.status === 'pending',
                    'bg-emerald-50 text-emerald-700 border-emerald-200': app.status === 'approved',
                    'bg-red-50 text-red-600 border-red-300': app.status === 'rejected',
                    'bg-blue-50 text-blue-600 border-blue-200': app.status === 'under_review',
                    'bg-slate-50 text-slate-400 border-slate-200': app.status === 'out_of_period'
                  }"
                >
                  {{ uploadStatusLabel(app.status) }}
                </span>
              </td>
              <td
                v-if="app.status === 'rejected'"
                class="px-5 py-3.5 border-b border-gray-100 text-xs text-red-500 mt-1"
              >
              {{ app.rejection_message }}
              </td>
              <td v-else class="px-5 py-3.5 border-b border-gray-100 text-xs text-gray-400 mt-1">
                -
              </td>
              <td class="px-5 py-3.5 border-b border-gray-100 text-gray-700">
                <div class="flex items-center gap-2">
                  <button
                    @click="previewPdf = `/pdf-uploads/${app.id}/view`"
                    class="text-xs text-gray-500 hover:text-sky-700 transition flex items-center gap-1"
                  >
                    <FileText class="w-4 h-4" />
                    詳細
                  </button>
                  <button
                    :disabled="props.cycle?.status === 'pending'"
                    :class="[
                      'text-xs transition flex items-center gap-1',
                      props.cycle?.status === 'pending'
                        ? 'text-gray-300 cursor-not-allowed'
                        : 'text-red-500 hover:text-red-600'
                    ]"
                    @click="deleteUpload(app.id)"
                  >
                    <Trash2 class="w-4 h-4" />
                    削除
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      </div>
      <!-- グレーアウトラッパーここまで -->

    </div>

    <div>
      <Dialog :open="showDeclineConfirm" @update:open="showDeclineConfirm = false">
        <DialogContent class="sm:max-w-lg bg-white">
          <DialogHeader>
            <DialogTitle>資格更新の辞退確認</DialogTitle>
          </DialogHeader>

          <p class="text-sm text-gray-700 leading-relaxed">
            資格更新の手続きを辞退（キャンセル）します。資格更新を辞退すると、現在の認定期間終了に伴い指導士資格を喪失し、その後は「腎臓リハビリテーション指導士」と称することができなくなります。本当によろしいですか？
          </p>

          <DialogFooter>
            <SecondaryButton @click="showDeclineConfirm = false">
              いいえ（元の画面に戻る）
            </SecondaryButton>
            <Button
              class="bg-red-600 hover:bg-red-700 text-white ms-3"
              @click="confirmDecline"
            >
              はい（指導士資格を更新しない）
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog :open="showFeeDialog" @update:open="showFeeDialog = false">
        <DialogContent class="sm:max-w-lg bg-white">
          <DialogHeader>
            <DialogTitle>年会費詳細</DialogTitle>
          </DialogHeader>

          <table class="w-full text-base">
            <thead>
              <tr class="border-b text-gray-500">
                <th class="py-2 text-left">年度</th>
                <th class="py-2 text-right">年会費</th>
                <th class="py-2 text-right">納入額</th>
                <th class="py-2 text-center">状態</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="fee in props.fees" :key="fee.id" class="border-b">
                <td class="py-2">{{ fee.fiscal_year }}年度</td>
                <td class="py-2 text-right">{{ fee.annual_fee.toLocaleString() }}円</td>
                <td class="py-2 text-right">{{ fee.payment_amount.toLocaleString() }}円</td>
                <td class="py-2 text-center">
                  <span :class="[
                    'px-2 py-0.5 rounded-full text-sm font-medium',
                    fee.status === 'paid' ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-500'
                  ]">
                    {{ fee.status === 'paid' ? '納入済' : '未納' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>

          <DialogFooter>
            <Button variant="outline" size="sm" @click="showFeeDialog = false">閉じる</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog :open="!!previewPdf" @update:open="previewPdf = null">
        <DialogOverlay class="fixed inset-0 z-40 bg-white" />
        <DialogContent class="w-[95vw] max-w-[95vw] h-[95vh] max-h-[95vh] p-0 flex flex-col sm:rounded-lg">
          <DialogHeader class="px-4 py-3 border-b">
            <DialogTitle>{{ t('PDFpreview') }}</DialogTitle>
          </DialogHeader>
          <div class="flex-1 overflow-hidden">
            <iframe
              v-if="previewPdf"
              :src="`${previewPdf}#toolbar=0&navpanes=0`"
              class="w-full h-full border-0"
            />
          </div>
          <DialogFooter class="px-4 py-3 border-t">
            <Button variant="outline" @click="previewPdf = null">
              {{ t('closed') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <!-- Dialog -->
      <Dialog :open="isOpen" @update:open="isOpen = false">
        <DialogContent class="sm:max-w-2xl bg-white">
          <DialogHeader>
            <DialogTitle>新規書類アップロード</DialogTitle>
            <DialogDescription>
              参加証や修了証などの書類をアップロードし、区分を選択してください。
            </DialogDescription>
          </DialogHeader>

          <form @submit.prevent="submit" class="space-y-5 py-2">

            <!-- 区分 -->
            <div class="space-y-2">
              <Label>区分</Label>
              <Select v-model="form.credit_category_id">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="区分を選択してください" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="c in props.creditCategories" :key="c.id" :value="c.id">
                    {{ c.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors?.credit_category_id" />
            </div>

            <!-- conference（コンボボックス：部分一致で検索して選択） -->
            <div v-if="props.conferences.length > 0" class="space-y-2">
              <Label>学会名</Label>
              <Popover v-model:open="isConferencePopoverOpen">
                <PopoverTrigger as-child>
                  <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    :aria-expanded="isConferencePopoverOpen"
                    class="w-full justify-between font-normal"
                  >
                    <span :class="selectedConferenceName ? 'text-gray-900' : 'text-gray-400'">
                      {{ selectedConferenceName || '学会を選択してください' }}
                    </span>
                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[--reka-popover-trigger-width] p-0">
                  <Command>
                    <CommandInput placeholder="例: 腎臓 と入力して検索" />
                    <CommandEmpty>該当する学会が見つかりません</CommandEmpty>
                    <CommandList>
                      <CommandGroup>
                        <CommandItem
                          v-for="conf in filteredConferences"
                          :key="conf.id"
                          :value="conf.name"
                          @select="selectConference(conf)"
                        >
                          <Check
                            :class="['mr-2 h-4 w-4', form.credit_conference_id === conf.id ? 'opacity-100' : 'opacity-0']"
                          />
                          {{ conf.name }}
                        </CommandItem>
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
              <InputError :message="form.errors?.credit_conference_id" />
            </div>

            <!-- role -->
            <div v-if="props.roles.length > 0" class="space-y-2">
              <Label>役割</Label>
              <Select v-model="form.role_id">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="役割を選択してください" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="r in filteredRoles" :key="r.id" :value="r.id">
                    {{ r.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- 書類名（session：回数） -->
            <div v-if="selectedRolePointRequiresSession" class="space-y-2">
              <Label for="session">回数</Label>
              <TextInput
                id="session"
                v-model="form.session"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                class="w-full"
                placeholder="例: 16"
                @input="form.session = form.session.replace(/[^0-9]/g, '')"
              />
              <InputError :message="form.errors?.session" />
            </div>

            <!-- 参加日/発行日 -->
            <div class="space-y-2">
              <Label for="issued_date">参加日 / 発行日</Label>
              <TextInput id="issued_date" v-model="form.issued_date" type="date" class="w-full" />
            </div>

            <!-- ファイルドラッグ&ドロップ -->
            <div
              class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition"
              @dragover.prevent
              @drop.prevent="onFileDrop"
              @click="fileInput?.click()"
            >
              <FileUp class="w-8 h-8 text-gray-400 mx-auto mb-2" />
              <p class="text-base text-gray-500">ファイルをドラッグ＆ドロップ、またはクリックして選択</p>
              <p class="text-sm text-gray-400 mt-1">PDF, JPG, PNG (最大 10MB)</p>
              <p v-if="form.file" class="text-sm text-indigo-600 mt-2 font-medium">{{ form.file.name }}</p>
              <input type="file" class="hidden" ref="fileInput" @change="onFileChange" />
              <InputError :message="form.errors?.file" />
            </div>

          </form>
          <div v-if="uploadError" class="bg-red-50 border border-red-200 rounded-lg p-4 shadow-sm">
            <p class="text-base font-semibold text-red-600 flex items-center gap-2">
              <AlertTriangle class="w-4 h-4 text-red-500" />
              {{ uploadError }}
            </p>
          </div>
          <div v-if="warnings.length > 0" class="bg-white border border-gray-200 rounded-lg p-4 space-y-1 shadow-sm">
            <p class="text-base font-semibold text-red-600 flex items-center gap-2">
              <AlertTriangle class="w-4 h-4 text-red-500" />
              AI検証で不一致が検出されました
            </p>
            <ul class="text-base text-gray-700 list-disc list-inside mt-2">
              <li v-for="(w, i) in warnings" :key="i">{{ w }}</li>
            </ul>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="isOpen = false">キャンセル</Button>
            <Button
              class="bg-indigo-600 hover:bg-indigo-700 text-white"
              :disabled="!isWithinPeriod || form.processing"
              @click="upload"
            >
              <Save class="w-4 h-4 mr-2" />
              アップロード
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import {
  Dialog, DialogContent, DialogHeader,
  DialogTitle, DialogDescription, DialogFooter, DialogOverlay
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import {
  Popover, PopoverContent, PopoverTrigger
} from '@/components/ui/popover'
import {
  Command, CommandInput, CommandEmpty, CommandGroup, CommandItem, CommandList
} from '@/components/ui/command'
import {
  Select, SelectTrigger, SelectValue, SelectContent, SelectItem
} from '@/components/ui/select'

import { ref, computed , watch } from 'vue'
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Eye, FileText, Calendar, Clock, Info, GraduationCap, Award, Trash2, Upload, Save, FileUp, AlertCircle, AlertTriangle, CheckCircle2, Check, ChevronsUpDown, X } from 'lucide-vue-next'
import dayjs from 'dayjs'

import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const page = usePage()

const warnings = computed(() => page.props.flash?.warnings ?? [])
const uploadError = computed(() => page.props.flash?.error ?? null)

const props = defineProps({
  uploads: { type: Array, required: true },
  member: Object,
  cycle: Object,
  approvedTotal: Number,
  pendingTotal: Number,
  total: Number,
  totalFee: Number,
  totalPaid: Number,
  isFeeOk: Boolean,
  conference_count: Number,
  pendingConferenceCount: Number,
  approvedConferenceCount: Number,
  requiredUnits: Number,
  creditCategories: { type: Array, required: true },
  conferences: { type: Array, required: true },
  roles: { type: Array, required: true },
  fees: Object,
  annualFeeStatus: Boolean, 
  schedule: Object, // [今回追加] 現在の期区分（応募期間・審査期間）
})

const totalCredits = computed(() => (props.approvedTotal ?? 0) + (props.pendingTotal ?? 0))

const form = useForm({
  last_name: props.member?.last_name ?? '',
  first_name: props.member?.first_name ?? '',
  email: props.member?.email ?? '',
  code: props.member?.code ?? '',
  instructor_code: props.cycle?.instructor_no ?? '',
  file: null,
  credit_category_id: '',
  credit_conference_id: '',
  role_id: '',
  session: '',
  issued_date: ''
})

const cycleYears = computed(() => {
  if (!props.cycle?.start_date || !props.cycle?.end_date) return 5
  const start = new Date(props.cycle.start_date)
  const end = new Date(props.cycle.end_date)
  return end.getFullYear() - start.getFullYear()
})

const progressPercent = computed(() => {
  const approved = props.approvedTotal ?? 0
  const required = props.requiredUnits ?? 50
  return Math.min(Math.round((approved / required) * 100), 100)
})

const isNoUpdate = computed(() => props.cycle?.status === 'no_update')

const isDeclined = computed(() => props.cycle?.status === 'no_update')

const showDeclineConfirm = ref(false)

// ボタンクリック時：辞退する場合のみ確認ダイアログを挟む。再開する場合は確認なしで直接切り替える。
const onDeclineButtonClick = () => {
  if (isDeclined.value) {
    updateStatus('before_update')
    return
  }
  showDeclineConfirm.value = true
}

// 確認ダイアログで「はい」を選んだ場合
const confirmDecline = () => {
  showDeclineConfirm.value = false
  updateStatus('no_update')
}

// ---- 更新手続きフェーズ ステッパー ----

const phases = ['単位蓄積', '更新申請', '審査', '更新料納付', '資格認定更新']

// 「審査結果まち」「資格更新認定」は四角、それ以外は丸
const squarePhaseIndexes = [2, 4]
const isSquarePhase = (idx) => squarePhaseIndexes.includes(idx)

// ①(a)(b)(c) の3つの要件チェックが全て満たされているか
// [今回追加] これがtrueのとき、まだ申請前（before_update）でも
// ①単位蓄積を完了扱い（緑）にし、②更新申請を現在地点（青）に進める
const allRequirementsMet = computed(() =>
  hasConferenceCount.value && isCreditsRequirementMet.value && !!props.annualFeeStatus
)

const currentPhaseIndex = computed(() => {
  switch (props.cycle?.status) {
    case 'pending':  return 2
    case 'approved': return 3
    case 'updated':  return 4
    case 'reject':   return 2
    case 'before_update':
      // [今回追加] 3要件を全て満たしていれば①を緑、②を現在地点（青）にする
      return allRequirementsMet.value ? 1 : 0
    default:
      return 0 // no_update 等
  }
})

const isRejectedPhase = computed(() => props.cycle?.status === 'reject')

const phaseCircleClass = (idx) => {
  const shape = isSquarePhase(idx) ? 'rounded-md' : 'rounded-full'
  const base = `w-9 h-9 ${shape} flex items-center justify-center text-base font-bold flex-shrink-0`

  // 達成済み（通過済み）＝グリーン
  if (idx < currentPhaseIndex.value) return `${base} bg-emerald-500 text-white`

  // 現在地点が却下の場合だけ特別に赤で強調
  if (idx === currentPhaseIndex.value && isRejectedPhase.value) {
    return `${base} bg-red-500 text-white`
  }

  // 現在地点＝ブルー
  if (idx === currentPhaseIndex.value) return `${base} bg-blue-600 text-white`

  // まだ到達していない先のステップ＝グレー
  return `${base} bg-gray-300 text-white`
}

// ---- 資格更新状況チェックリスト用ロジック ----

// レ点表示専用：参加登録の抜けは別問題として扱い、回数のみで判定
const hasConferenceCount = computed(() => (props.conference_count ?? 0) >= 2)

const isCreditsRequirementMet = computed(() =>
  totalCredits.value >= (props.requiredUnits ?? 0)
)

// 4. 更新申請の状態（before_update以外はボタンではなくバッジ表示にする）
// [今回追加] 更新申請受付期間カード右横に表示するステータスバッジ
const cycleStatusLabel = computed(() => {
  const map = {
    before_update: '未申請',
    pending: '更新申請済',
    no_update: '辞退',
    reject: '却下',
    approved: '委員長が承認',
    updated: '更新完了',
  }
  return map[props.cycle?.status] ?? '未申請'
})

const cycleStatusBadgeClass = computed(() => {
  const map = {
    before_update: 'bg-gray-100 text-gray-500',
    pending: 'bg-blue-100 text-blue-700',
    no_update: 'bg-orange-100 text-orange-700',
    reject: 'bg-red-100 text-red-700',
    approved: 'bg-emerald-100 text-emerald-700',
    updated: 'bg-emerald-100 text-emerald-700',
  }
  return map[props.cycle?.status] ?? 'bg-gray-100 text-gray-500'
})

const applyBadgeState = computed(() => {
  switch (props.cycle?.status) {
    case 'pending':  return { label: '更新申請 提出完了', tone: 'met' }
    case 'approved': return { label: '承認済み', tone: 'met' }
    case 'updated':  return { label: '完了', tone: 'met' }
    default:         return null // before_update・reject → ボタン表示
  }
})

// 5. 更新料送金の状態：未請求（審査承認前）／未納（承認済み・送金待ち）／納付済み（updated）
const paymentBadgeState = computed(() => {
  switch (props.cycle?.status) {
    case 'approved': return { label: '未納', tone: 'unpaid' }
    case 'updated':  return { label: '納付済み', tone: 'paid' }
    default:         return { label: '未請求', tone: 'unbilled' }
  }
})

// paymentBadgeState.tone（unbilled/unpaid/paid）を、②と共通のbadgeClassesトーン（neutral/pending/met）に変換
const paymentBadgeToneMap = {
  unbilled: 'neutral',
  unpaid: 'pending',
  paid: 'met',
}

const badgeClasses = (tone) => [
  'inline-flex items-center gap-1 text-sm font-semibold px-2.5 py-1 rounded-full border whitespace-nowrap',
  {
    met:     'bg-emerald-50 text-emerald-700 border-emerald-200',
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    reject:  'bg-red-50 text-red-600 border-red-200',
    neutral: 'bg-gray-50 text-gray-400 border-gray-200',
  }[tone]
]

// ---- カード右上バッジ（全体ステータス、現在は未表示） ----

const previewPdf = ref(null)

const openPdf = (pdfPath) => {
  console.log('PDF PATH:', pdfPath)
  if (!pdfPath) return

  previewPdf.value = pdfPath
}

const deleteUpload = (id) => {
  if (!confirm('この書類を削除しますか？この操作は取り消せません。')) return

  router.delete(`/pdf-uploads/${id}`, {
    preserveScroll: true,
  })
}

const uploadStatusLabel = (status) => {
  switch (status) {
    case 'pending':       return '蓄積中'
    case 'approved':      return '承認'
    case 'rejected':      return '却下'
    case 'under_review':  return '審査中'
    case 'out_of_period': return '期間外'
    default:              return status
  }
}

const updateStatus = (status) => {

  router.post('/instructor-update-cycles/status', {
    id: props.cycle.id,
    status,
  })
}

// ②更新申請ボタン：ダイアログを挟まず、確認だけして直接送信する
const submitUpdateApplication = () => {
  if (!confirm('更新申請を提出します。よろしいですか？')) return
  updateStatus('pending')
}

const isOpen = ref(false)

// [今回追加] 却下メッセージの表示状態（×で閉じられる）
const showRejectNotice = ref(true)

// [今回追加] 更新申請受付期間内かどうか（①充足チェック・②申請ボタンの表示可否に使う）
const isWithinRenewalPeriod = computed(() => {
  const now = new Date()
  const renewalStart = props.cycle?.renewal_start_date ? new Date(props.cycle.renewal_start_date) : null
  const renewalEnd = props.cycle?.renewal_end_date ? new Date(props.cycle.renewal_end_date) : null

  return renewalStart && renewalEnd
    ? now >= renewalStart && now <= renewalEnd
    : false
})

const isEligible = computed(() => {
  return (
    isWithinRenewalPeriod.value &&
    (props.conference_count ?? 0) > 1 &&
    (totalCredits.value ?? 0) >= (props.requiredUnits ?? 50)
  )
})

const fileInput = ref(null);

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.file = file;
    }
};

const onFileDrop = (e) => {
    const file = e.dataTransfer.files[0];
    if (file) {
        form.file = file;
    }
};

function upload() {
  const issuedDate = dayjs(form.issued_date)
  const startDate = dayjs(props.cycle?.start_date)
  const endDate = dayjs(props.cycle?.end_date)

  if (!issuedDate.isValid()) {
    alert('参加日/発行日を入力してください')
    return
  }

  if (issuedDate.isBefore(startDate) || issuedDate.isAfter(endDate)) {
    alert(`参加日/発行日は認定期間外のため登録できません。認定期間（${startDate.format('YYYY-MM-DD')} 〜 ${endDate.format('YYYY-MM-DD')}）`)
    return
  }

  form.post('/pdf-uploads', {
    forceFormData: true,
    onSuccess: () => {
      // store()側は例外時もback()で通常レスポンスを返すため、
      // flash.error が立っていたら保存失敗とみなしダイアログは開いたままにする
      if (!page.props.flash?.error) {
        isOpen.value = false
      }
    },
  })
}

const filteredConferences = computed(() =>
  props.conferences.filter(c =>
    (c.available_category_ids ?? []).map(String).includes(String(form.credit_category_id))
  )
)

// --- 学会名コンボボックス ---
const isConferencePopoverOpen = ref(false)

const selectedConferenceName = computed(() => {
  const selected = props.conferences.find(c => c.id === form.credit_conference_id)
  return selected?.name ?? ''
})

function selectConference(conf) {
  form.credit_conference_id = conf.id
  isConferencePopoverOpen.value = false
}

const filteredRoles = computed(() =>
  props.roles.filter(r =>
    r.credit_category_id == form.credit_category_id &&
    r.credit_conference_id == form.credit_conference_id
  )
);

const isWithinPeriod = computed(() => {
  return true
})

const annualFeeStatus = computed(() => props.annualFeeStatus ? '納入済' : '未納')

const showFeeDialog = ref(false)
const missingParticipationSessions = computed(() => {
  const uploads = props.uploads || []

  const groups = {}
  for (const app of uploads) {
    if (!app.session) continue
    if (!groups[app.session]) groups[app.session] = []
    groups[app.session].push(app)
  }

  const missing = []
  for (const [session, items] of Object.entries(groups)) {
    const targetItems = items.filter(
      (a) =>
        a.credit_category_name === '学術集会' &&
        a.credit_conference_name === '日本腎臓リハビリテーション学会'
    )
    if (targetItems.length === 0) continue

    const hasParticipation = targetItems.some((a) => a.role_name === '参加')
    if (!hasParticipation) {
      missing.push(session)
    }
  }
  return missing
})

// 選択中のrole(=credit_role_pointsの1行)が「回数入力」を必要とするかどうか
const selectedRolePointRequiresSession = computed(() => {
  const selected = props.roles.find(r => r.id === form.role_id)
  return selected ? !!selected.requires_session : true
})

// 区分が変わると選べる学会も変わるので、学会名の選択もリセットする
watch(() => form.credit_category_id, () => {
  form.credit_conference_id = ''
  form.role_id = ''
  form.session = ''
})

// 学会が変わると選べるroleも変わるのでリセット
watch(() => form.credit_conference_id, () => {
  form.role_id = ''
  form.session = ''
})

// roleが変わったタイミングで、回数入力が不要になったら session をクリアしておく
watch(() => form.role_id, () => {
  if (!selectedRolePointRequiresSession.value) {
    form.session = ''
  }
})

</script>