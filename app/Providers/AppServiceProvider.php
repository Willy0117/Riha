<?php

namespace App\Providers;

use Inertia\Inertia;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Inertia::share([
             'locale' => function () {
                // Laravelの現在のロケールが何であるかを確認
                // Log::info('DEBUG: AppServiceProvider - Current Laravel locale: ' . App::getLocale());
                return App::getLocale();
            },
            'language' => function () {
                $locales = ['ja', 'en']; // ★ここにサポートする全てのロケールを定義★
                $translations = [];

                foreach ($locales as $locale) {
                    $path = lang_path($locale . '.json');
                    if (file_exists($path)) {
                        $jsonContent = file_get_contents($path);
                        $data = json_decode($jsonContent, true);

                        if (json_last_error() === JSON_ERROR_NONE) {
                            $translations[$locale] = $data;
                        } else {
                            // JSONデコードエラーがあればログに出力
                            Log::error('JSON Decode Error for ' . $path . ': ' . json_last_error_msg());
                            $translations[$locale] = []; // エラー時は空の配列を設定
                        }
                    } else {
                        // ファイルが見つからない場合もログに出力
                        Log::warning('Translation file not found for locale: ' . $locale . ' at ' . $path);
                        $translations[$locale] = []; // 見つからない場合も空の配列を設定
                    }
                }
                
                // ★ログで、jaとenの両方のデータが正しく読み込まれているか確認できます★
                // Log::info('Shared translations with Vue', ['translations' => $translations]);

                return $translations;
            },
            // ... 他のシェアしているデータ
        ]);        
        //
    }
}
