<?php

namespace App\Services;

use Aws\Textract\TextractClient;
use Aws\Exception\AwsException;
use Illuminate\Http\UploadedFile;

/**
 * AWS Textract を使い、アップロードされた書類（PDF・jpg・png）からテキストを抽出し、
 * 入力データ（日付・学会名・参加種別・氏名）と照合する。
 * 旧 verifyPdfWithGroq() / verifyPdfWithGemini() の代替として PdfUploadController から利用する想定。
 */
class TextractService
{
    private TextractClient $client;

    public function __construct()
    {
        $this->client = new TextractClient([
            'version' => 'latest',
            'region'  => config('services.textract.region', env('AWS_DEFAULT_REGION', 'ap-northeast-1')),
            // 認証情報は、既存のS3運用と同じくIAMロール／.envのAWSキーをそのまま利用する
        ]);
    }

    /**
     * アップロードされたファイル（PDF・jpg・png）からテキストを抽出する。
     * Textract の DetectDocumentText は、PDF・PNG・JPEG のいずれもそのまま渡せる。
     */
    public function extractText(UploadedFile $file): string
    {
        try {
            $result = $this->client->detectDocumentText([
                'Document' => [
                    'Bytes' => file_get_contents($file->getRealPath()),
                ],
            ]);

            $lines = [];
            foreach ($result['Blocks'] ?? [] as $block) {
                if (($block['BlockType'] ?? null) === 'LINE') {
                    $lines[] = $block['Text'] ?? '';
                }
            }

            return implode("\n", $lines);
        } catch (AwsException $e) {
            \Log::error('Textract error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * 抽出したテキストと、入力データ（日付・学会名・参加種別・氏名）を照合する。
     * 旧 verifyPdfWithGroq() が返していた形式（date_match等のキー）に合わせて返す。
     * 加えて、実際に書類から読み取れた値（pdf_date・pdf_conference・pdf_role）も返す。
     *
     * @param array $inputData ['date' => ..., 'conference' => ..., 'role' => ..., 'member_name' => ...]
     * @param array $conferenceCandidates 学会名マスターの全名称（実際に書類に書かれているものを特定するために使う）
     * @param array $roleCandidates 参加種別マスターの全名称（同上）
     */
    public function verify(
        UploadedFile $file,
        array $inputData,
        array $conferenceCandidates = [],
        array $roleCandidates = []
    ): array {
        $text = $this->extractText($file);

        if ($text === '') {
            return [
                'date_match'       => true,
                'conference_match' => true,
                'role_match'       => true,
                'name_match'       => true,
                'pdf_date'         => null,
                'pdf_conference'   => null,
                'pdf_role'         => null,
            ];
        }

        // 実際に書類から読み取れた値（会員に「実際はこう書いてありました」と見せるための値）
        $pdfDate       = $this->extractDate($text);
        $pdfConference = $this->findKnownValue($text, $conferenceCandidates);
        $pdfRole       = $this->findKnownValue($text, $roleCandidates);

        // 日付照合（issued_dateがテキスト中に含まれるか。区切り文字違いを吸収する）
        $dateMatch = true;
        if (!empty($inputData['date'])) {
            $dateMatch = $this->containsDate($text, $inputData['date']);
        }

        // 学会名照合（空白除去のうえ部分一致）
        $conferenceMatch = true;
        if (!empty($inputData['conference'])) {
            $conferenceMatch = $this->containsNormalized($text, $inputData['conference']);
        }

        // 参加種別照合（例：「参加」「発表」等）
        $roleMatch = true;
        if (!empty($inputData['role'])) {
            $roleMatch = $this->containsNormalized($text, $inputData['role']);
        }

        // 氏名照合（スペース除去のうえ部分一致）
        $nameMatch = true;
        if (!empty($inputData['member_name'])) {
            $nameMatch = $this->containsNormalized($text, $inputData['member_name']);
        }

        return [
            'date_match'       => $dateMatch,
            'conference_match' => $conferenceMatch,
            'role_match'       => $roleMatch,
            'name_match'       => $nameMatch,
            // [今回追加] 実際に書類から読み取れた値。会員が見比べて判断できるようにする
            'pdf_date'         => $pdfDate,
            'pdf_conference'   => $pdfConference,
            'pdf_role'         => $pdfRole,
        ];
    }

    // [今回追加] テキスト中から日付らしき文字列を検出し、実際に読み取った値として返す
    private function extractDate(string $text): ?string
    {
        // 「2026年1月6日」「2026/01/06」「2026-01-06」のパターンを順に探す
        $patterns = [
            '/(\d{4})年\s*(\d{1,2})月\s*(\d{1,2})日/u',
            '/(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
            }
        }

        return null;
    }

    // [今回追加] 候補リスト（学会名・参加種別のマスター名称）の中から、
    // 実際にテキストに含まれているものを1件見つけて返す
    private function findKnownValue(string $text, array $candidates): ?string
    {
        $normalizedText = preg_replace('/[\s　]+/u', '', $text);

        foreach ($candidates as $candidate) {
            if (empty($candidate)) {
                continue;
            }
            $normalizedCandidate = preg_replace('/[\s　]+/u', '', $candidate);
            if (str_contains($normalizedText, $normalizedCandidate)) {
                return $candidate;
            }
        }

        return null;
    }

    // 全角/半角スペース・改行を除去してから部分一致を見る
    private function containsNormalized(string $text, string $needle): bool
    {
        $normalize = fn (string $s) => preg_replace('/[\s　]+/u', '', $s);
        return str_contains($normalize($text), $normalize($needle));
    }

    // 日付は「2026-01-06」「2026/01/06」「2026年1月6日」のような表記ゆれを吸収して照合する
    private function containsDate(string $text, string $dateValue): bool
    {
        try {
            $date = \Carbon\Carbon::parse($dateValue);
        } catch (\Throwable) {
            return true; // 日付として解釈できない場合は検証をスキップ（誤検知を避ける）
        }

        $normalizedText = preg_replace('/[\s　]+/u', '', $text);

        $patterns = [
            $date->format('Y-m-d'),
            $date->format('Y/m/d'),
            $date->format('Y年n月j日'),
            $date->format('Y年m月d日'),
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($normalizedText, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
