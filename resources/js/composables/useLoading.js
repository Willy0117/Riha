import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

export const isLoading = ref(false)
let timer = null

router.on('start', () => {
  // 速すぎる通信でチラつかないように
  timer = setTimeout(() => {
    isLoading.value = true
  }, 200)
})

router.on('finish', () => {
  if (timer) clearTimeout(timer)
  isLoading.value = false
})

// --- 追記: 401/403/404/419 時、Inertiaのエラー画面を出さずに再読み込み ---
// サーバー側（bootstrap/app.php）でこれらの例外を全てリダイレクト+トースト表示に変換しているため、
// フロント側では「エラー画面を出さず、素直にリダイレクト先へ再読み込みする」だけでよい
router.on('invalid', (event) => {
  const status = event.detail.response?.status
  if ([401, 403, 404, 419].includes(status)) {
    event.preventDefault()
    window.location.reload()
  }
})