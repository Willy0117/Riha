<?php

namespace App\Services;

use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;

/**
 * AWS SNS を使ったSMS送信サービス。
 * 用途：
 *   - 審査結果通知
 *   - 更新時期リマインド
 *   - 更新料の支払い案内
 */
class SmsService
{
    private SnsClient $client;

    public function __construct()
    {
        $this->client = new SnsClient([
            'version' => 'latest',
            'region'  => env('AWS_DEFAULT_REGION', 'ap-northeast-1'),
        ]);
    }

    /**
     * SMSを送信する。
     *
     * @param string $phoneNumber 送信先電話番号（国内形式：090-1234-5678 や 09012345678 でも可。国際形式に自動変換する）
     * @param string $message 送信するメッセージ本文
     * @return bool 送信に成功したか
     */
    public function send(string $phoneNumber, string $message): bool
    {
        $formatted = $this->toInternationalFormat($phoneNumber);

        if (!$formatted) {
            \Log::warning('SmsService: invalid phone number', ['phone_number' => $phoneNumber]);
            return false;
        }

        try {
            $this->client->publish([
                'Message'     => $message,
                'PhoneNumber' => $formatted,
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType'    => 'String',
                        // Transactional：到達率を優先（会員向けの業務通知のため）
                        'StringValue' => 'Transactional',
                    ],
                ],
            ]);

            return true;
        } catch (AwsException $e) {
            \Log::error('SmsService: send failed', [
                'phone_number' => $phoneNumber,
                'message'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 日本国内の電話番号表記（090-1234-5678 / 09012345678 等）を、
     * AWS SNSが要求する国際形式（+819012345678）に変換する。
     * 変換できない場合は null を返す。
     */
    private function toInternationalFormat(string $phoneNumber): ?string
    {
        // ハイフン・スペース等を除去
        $digits = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // 既に国際形式（+81...）ならそのまま
        if (str_starts_with($digits, '+81')) {
            return $digits;
        }

        // 0始まりの国内形式（090xxxxxxxx等）を +81 に変換（先頭の0を除去）
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            return '+81' . substr($digits, 1);
        }

        return null;
    }
}
