<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\App;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // ★ここからロケール設定の追加/修正★
        $locale = $request->query('locale', session('locale', config('app.locale')));
        
        // サポートされているロケールか確認（必要に応じて）
        $supportedLocales = ['en', 'ja']; 
        if (!in_array($locale, $supportedLocales)) {
            $locale = config('app.fallback_locale', 'en'); // サポート外ならフォールバックロケールを使用
        }

        App::setLocale($locale); // アプリケーションのロケールを設定
        session()->put('locale', $locale); // セッションにも保存して、次回のアクセス時にも利用できるようにする

        // 共有データ
        return array_merge(parent::share($request), [
            'locale' => function () {
                return App::getLocale();
            },
            'language' => function () {
                $localesToLoad = ['ja', 'en']; // サポートする全てのロケール
                $translations = [];

                foreach ($localesToLoad as $lang) {
                    $path = lang_path($lang . '.json');
                    if (file_exists($path)) {
                        $jsonContent = file_get_contents($path);
                        $data = json_decode($jsonContent, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $translations[$lang] = $data;
                        } else {
                            // JSONデコードエラーがあればログに出力
                            \Log::error('JSON Decode Error for ' . $path . ': ' . json_last_error_msg());
                            $translations[$lang] = [];
                        }
                    } else {
                        // ファイルが見つからない場合もログに出力
                        \Log::warning('Translation file not found for locale: ' . $lang . ' at ' . $path);
                        $translations[$lang] = [];
                    }
                }
                return $translations;
            },
            // 他の共有データ...
        ]);
    }
}
