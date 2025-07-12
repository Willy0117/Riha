<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDF; // Barryvdh\DomPDF\Facade\Pdf のエイリアス

class PDFController extends Controller
{
    public function showSimplePdf()
    {
        // IPAexゴシックフォントファイルへの絶対パスを取得
        // このパスが正しいことを何度も確認してください
        $fontPath = storage_path('fonts/ipaexg.ttf');

        $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>シンプルPDFテスト</title>
                <style>
                    body {
                        font-family: "ipaexg_custom", sans-serif; /* @font-face で定義したフォント名を指定 */
                        font-size: 12px;
                        text-align: center;
                        margin-top: 100px;
                    }
                    h1 {
                        color: #333;
                    }
                </style>
            </head>
            <body>
                <h1>こんにちは、PDF！ fontを組み込んでみた</h1>
                <p>これはテスト用のPDFです。</p>
                <p>日本語も表示されるか確認してください。</p>
            </body>
            </html>
        ';

        $pdf = PDF::loadHTML($html);

        return $pdf->stream('simple_test.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="simple_test.pdf"',
        ]);
    }
}