import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                //sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                sans: ['"Noto Sans JP"', 'Roboto', ...defaultTheme.fontFamily.sans],
            },
                        // ★★★ ここに colors プロパティを追加/修正 ★★★
            colors: {
                primary: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    200: '#bbf7d0',
                    300: '#86efad',
                    400: '#4ade80',
                    500: '#22c55e', // メインカラーとして使用
                    600: '#16a34a',
                    700: '#15803d',
                    800: '#166534',
                    900: '#14532d',
                    950: '#0f3a21',
                },
                secondary: { // 例：アクセントカラー
                    50: '#f9fafb',
                    100: '#f3f4f6',
                    // ... Tailwindの gray などから選んでも良い
                },
                // 必要に応じて他の色も定義
                // danger: '#ef4444', // 赤色を再定義
            },
        },
    },

    plugins: [forms, typography],
};
