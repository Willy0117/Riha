<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\MemberAddress;
use App\Models\MemberCommittee;
use App\Models\MemberDegree;
use App\Models\MemberEducation;
use App\Models\MemberRole;
use App\Models\InstructorUpdateCycle;
use App\Models\InstructorCycle;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class MemberImport implements ToCollection, WithChunkReading
{
    // ヘッダー行数（1〜4行目はヘッダー、5行目も空行のためスキップ）
    private const HEADER_ROWS = 5;

    // [今回追加] 新規ユーザーの初期パスワード（固定値。ログイン後、本人にパスワードリセットしてもらう運用）
    private const DEFAULT_PASSWORD = '12345678';

    public int $insertCount = 0;
    public int $updateCount = 0;
    public int $skipCount   = 0;
    public array $errors    = [];

    private int $tenantId;

    public function __construct()
    {
        $this->tenantId = Auth::user()?->tenant_id ?? 1;
    }

    // ============================================================
    // Excelカラムインデックス定義
    // ============================================================

    private const COL_MEMBER_NUMBER   = 0;
    private const COL_JOINED_AT       = 4;
    private const COL_WITHDRAWN_AT    = 7;
    private const COL_STATUS          = 8;
    private const COL_MEMBER_TYPE     = 12;
    private const COL_LAST_NAME       = 13;
    private const COL_FIRST_NAME      = 14;
    private const COL_LAST_NAME_KANA  = 15;
    private const COL_FIRST_NAME_KANA = 16;
    private const COL_GENDER          = 17;
    private const COL_BIRTHDATE       = 18;
    private const COL_EMAIL           = 25;

    private const COL_ORG_NAME        = 26;
    private const COL_ORG_DEPT        = 27;
    private const COL_ORG_SECTION     = 28;
    private const COL_POSITION        = 31;
    private const COL_ORG_TEL         = 36;
    private const COL_ORG_FAX         = 38;

    private const COL_HOME_POSTAL     = 40;
    private const COL_HOME_PREF       = 41;
    private const COL_HOME_ADDRESS    = 42;
    private const COL_PERSONAL_EMAIL  = 43;
    private const COL_MOBILE          = 44;
    private const COL_HOME_FAX        = 45;

    private const COL_SEND_TYPE       = 46;
    private const COL_SEND_POSTAL     = 48;
    private const COL_SEND_ADDRESS    = 49;

    private const COL_EDU_SCHOOL      = 50;
    private const COL_EDU_FACULTY     = 51;
    private const COL_EDU_GRADUATED   = 52;

    private const COL_DEGREE_START    = 53;
    private const DEGREE_COUNT        = 5;

    private const COL_INSTRUCTOR      = 63;
    private const COL_INSTRUCTOR_FROM = 64;
    private const COL_INSTRUCTOR_TO   = 65;

    // DX列：備考欄。「第N回指導士認定番号：XXXXXX」という形式が複数行含まれることがある
    private const COL_MEMO             = 127;

    private const COL_ROLE_START      = 67;
    private const ROLE_COUNT          = 10;

    private const COL_COMMITTEE_START = 97;
    private const COMMITTEE_COUNT     = 10;

    // ============================================================

    public function collection(Collection $rows)
    {
        $dataRows = $rows->slice(self::HEADER_ROWS)->filter(
            fn($row) => !empty($row[self::COL_MEMBER_NUMBER])
        );

        if ($dataRows->isEmpty()) {
            return;
        }

        $memberNumbers = $dataRows->pluck(self::COL_MEMBER_NUMBER)->map(fn($v) => (string) $v);
        $existingMembers = Member::whereIn('code', $memberNumbers)
            ->get()
            ->keyBy('code');

        foreach ($dataRows as $row) {
            try {
                DB::transaction(function () use ($row, $existingMembers) {
                    $this->processRow($row, $existingMembers);
                });
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'code' => $row[self::COL_MEMBER_NUMBER],
                    'message' => $e->getMessage(),
                ];
            }
        }
    }

    private function processRow($row, Collection $existingMembers): void
    {
        $memberNumber = (string) $row[self::COL_MEMBER_NUMBER];
        $existing     = $existingMembers->get($memberNumber);

        // ---------- Member ----------
        $memberData = $this->buildMemberData($row);
        if ($existing) {
            if ($this->hasChanges($existing, $memberData)) {
                $existing->update($memberData);
                $this->updateCount++;
            } else {
                $this->skipCount++;
            }
            $member = $existing;
        } else {
            $member = Member::create(array_merge($memberData, [
                'code' => $memberNumber,
            ]));
            $this->insertCount++;
        }

        // ---------- User ----------
        $this->syncUser($member, $row);

        // ---------- 子テーブル（既存を全削除して再登録） ----------
        $this->syncAddresses($member, $row);
        $this->syncEducations($member, $row);
        $this->syncDegrees($member, $row);
        $this->syncRoles($member, $row);
        $this->syncCommittees($member, $row);
        $this->syncInstructorCycle($member, $row);
    }

    // ============================================================
    // users
    // ============================================================

    // [今回修正]
    // - users.name カラムは削除済みのため、name は一切設定しない（member.name のアクセサに一本化）
    // - 既存ユーザーが見つかった場合、メールアドレス・member_idの変更を反映するよう更新処理を追加
    // - 新規作成時は固定パスワード + パスワードリセットメールを送信する
    //   （本物のメールアドレスがある場合のみ送信。プレースホルダーアドレスには送らない）
    private function syncUser(Member $member, $row): void
    {
        $code     = (string) $row[self::COL_MEMBER_NUMBER];
        $rawEmail = $row[self::COL_EMAIL] ?? null;
        $email    = $rawEmail ?: ($code . '@example.com');

        $user = User::where('username', $code)->first();

        if (!$user) {
            $user = User::create([
                'tenant_id' => $this->tenantId,
                'member_id' => $member->id,
                'username'  => $code,
                'email'     => $email,
                'password'  => Hash::make(self::DEFAULT_PASSWORD),
                'status'    => 1,
            ]);

            // 実メールアドレスが分かっている場合のみ、パスワードリセットメールを送信する
            // [今回修正] ログインはemailではなくusername（会員番号）で行うため、
            // Password Brokerの検索条件もusernameベースにする（通知メール自体はuser.emailへ自動送信される）
            if ($rawEmail) {
                try {
                    Password::sendResetLink(['username' => $code]);
                } catch (\Throwable $e) {
                    $this->errors[] = [
                        'code'    => $code,
                        'message' => 'パスワードリセットメールの送信に失敗しました: ' . $e->getMessage(),
                    ];
                }
            }
        } else {
            // 既存ユーザー：member_id・メールアドレスの変更だけ反映する（パスワードは触らない）
            $changed = false;
            if ($user->member_id !== $member->id) {
                $user->member_id = $member->id;
                $changed = true;
            }
            if ($rawEmail && $user->email !== $rawEmail) {
                $user->email = $rawEmail;
                $changed = true;
            }
            if ($changed) {
                $user->save();
            }
        }
    }

    // ============================================================
    // members
    // ============================================================

    private function buildMemberData($row): array
    {
        $organizationId = null;
        $orgName = $row[self::COL_ORG_NAME] ?? null;
        if ($orgName) {
            $org = Organization::where('name', $orgName)
                ->orWhere('abbr', $orgName)
                ->first();
            $organizationId = $org?->id;
        }

        return [
            'organization_id'  => $organizationId,
            'position'         => $row[self::COL_POSITION] ?? null,
            'last_name'        => $row[self::COL_LAST_NAME] ?? null,
            'first_name'       => $row[self::COL_FIRST_NAME] ?? null,
            'last_name_kana'   => $row[self::COL_LAST_NAME_KANA] ?? null,
            'first_name_kana'  => $row[self::COL_FIRST_NAME_KANA] ?? null,
            'gender'           => $row[self::COL_GENDER] ?? null,
            'birthdate'        => $this->toDate($row[self::COL_BIRTHDATE] ?? null),
            'email'            => $row[self::COL_EMAIL] ?? null,
            'personal_email'   => $row[self::COL_PERSONAL_EMAIL] ?? null,
            'mobile'           => $row[self::COL_MOBILE] ?? null,
            'fax'              => $row[self::COL_ORG_FAX] ?? null,
            'tel'              => $row[self::COL_ORG_TEL] ?? null,
            // [今回修正] resolveStatus() を実際に呼び出すよう修正（今までは呼ばれていなかった）
            'status_id'        => $this->resolveStatus($row[self::COL_STATUS] ?? null),
            'member_type'      => $row[self::COL_MEMBER_TYPE] ?? null,
            'joined_at'        => $this->toDate($row[self::COL_JOINED_AT] ?? null),
            'withdrawn_at'     => $this->toDate($row[self::COL_WITHDRAWN_AT] ?? null),
        ];
    }

    // ============================================================
    // member_addresses（自宅=1、送付先=2）
    // ============================================================

    private function syncAddresses(Member $member, $row): void
    {
        MemberAddress::where('member_id', $member->id)->delete();

        $inserts = [];

        if (($row[self::COL_HOME_POSTAL] ?? null) || ($row[self::COL_HOME_ADDRESS] ?? null)) {
            $inserts[] = [
                'member_id'   => $member->id,
                'type'        => MemberAddress::TYPE_HOME,
                'postal_code' => $row[self::COL_HOME_POSTAL] ?? null,
                'address1'    => $row[self::COL_HOME_PREF] ?? null,
                'address2'    => $row[self::COL_HOME_ADDRESS] ?? null,
                'address3'    => null,
                'tel'         => $row[self::COL_MOBILE] ?? null,
                'fax'         => $row[self::COL_HOME_FAX] ?? null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        if (($row[self::COL_SEND_POSTAL] ?? null) || ($row[self::COL_SEND_ADDRESS] ?? null)) {
            $inserts[] = [
                'member_id'   => $member->id,
                'type'        => MemberAddress::TYPE_SHIPPING,
                'postal_code' => $row[self::COL_SEND_POSTAL] ?? null,
                'address1'    => null,
                'address2'    => $row[self::COL_SEND_ADDRESS] ?? null,
                'address3'    => null,
                'tel'         => null,
                'fax'         => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        if (!empty($inserts)) {
            MemberAddress::insert($inserts);
        }
    }

    // ============================================================
    // member_educations
    // ============================================================

    private function syncEducations(Member $member, $row): void
    {
        MemberEducation::where('member_id', $member->id)->delete();

        $school      = $row[self::COL_EDU_SCHOOL] ?? null;
        $faculty     = $row[self::COL_EDU_FACULTY] ?? null;
        $graduatedAt = $row[self::COL_EDU_GRADUATED] ?? null;

        if ($school || $faculty || $graduatedAt) {
            MemberEducation::insert([[
                'member_id'    => $member->id,
                'school_name'  => $school,
                'faculty'      => $faculty,
                'graduated_at' => $graduatedAt,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]]);
        }
    }

    // ============================================================
    // member_degrees（最大5件）
    // ============================================================

    private function syncDegrees(Member $member, $row): void
    {
        MemberDegree::where('member_id', $member->id)->delete();

        $inserts = [];
        for ($i = 0; $i < self::DEGREE_COUNT; $i++) {
            $degreeCol   = self::COL_DEGREE_START + ($i * 2);
            $obtainedCol = $degreeCol + 1;
            $degree      = $row[$degreeCol] ?? null;
            $obtainedAt  = $row[$obtainedCol] ?? null;

            if ($degree || $obtainedAt) {
                $inserts[] = [
                    'member_id'   => $member->id,
                    'degree'      => $degree,
                    'obtained_at' => $obtainedAt,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
        }

        if (!empty($inserts)) {
            MemberDegree::insert($inserts);
        }
    }

    // ============================================================
    // member_roles（学会役職歴、最大10件）
    // ============================================================

    private function syncRoles(Member $member, $row): void
    {
        MemberRole::where('member_id', $member->id)->delete();

        $inserts = [];
        for ($i = 0; $i < self::ROLE_COUNT; $i++) {
            $roleCol  = self::COL_ROLE_START + ($i * 3);
            $startCol = $roleCol + 1;
            $endCol   = $roleCol + 2;
            $role      = $row[$roleCol] ?? null;
            $startedAt = $row[$startCol] ?? null;
            $endedAt   = $row[$endCol] ?? null;

            if ($role || $startedAt) {
                $inserts[] = [
                    'member_id'  => $member->id,
                    'role_name'  => $role,
                    'start_date' => $startedAt,
                    'end_date'   => $endedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($inserts)) {
            MemberRole::insert($inserts);
        }
    }

    // ============================================================
    // member_committees（学会委員歴、最大10件）
    // ============================================================

    private function syncCommittees(Member $member, $row): void
    {
        MemberCommittee::where('member_id', $member->id)->delete();

        $inserts = [];
        for ($i = 0; $i < self::COMMITTEE_COUNT; $i++) {
            $commCol   = self::COL_COMMITTEE_START + ($i * 3);
            $startCol  = $commCol + 1;
            $endCol    = $commCol + 2;
            $committee = $row[$commCol] ?? null;
            $startedAt = $row[$startCol] ?? null;
            $endedAt   = $row[$endCol] ?? null;

            if ($committee || $startedAt) {
                $inserts[] = [
                    'member_id'  => $member->id,
                    'committee'  => $committee,
                    'started_at' => $startedAt,
                    'ended_at'   => $endedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($inserts)) {
            MemberCommittee::insert($inserts);
        }
    }

    // ============================================================
    // instructor_update_cycles
    // ============================================================

    private function syncInstructorCycle(Member $member, $row): void
    {
        $hasInstructor = $row[self::COL_INSTRUCTOR] ?? null;

        if ($hasInstructor !== 'あり') {
            return;
        }

        // [今回追加] DX列（備考）から「第N回指導士認定番号：XXXXXX」を全件抽出する。
        $memo = $row[self::COL_MEMO] ?? null;
        $rounds = [];
        if ($memo) {
            preg_match_all(
                '/第\s*(\d+)\s*回指導士認定番号[:：]\s*([0-9A-Za-z\-]+)/u',
                (string) $memo,
                $matches,
                PREG_SET_ORDER
            );
            foreach ($matches as $m) {
                $rounds[] = ['exam_round' => (int) $m[1], 'instructor_no' => $m[2]];
            }
        }

        // [今回変更] 備考欄から抽出できなかった会員は、期間を判定する根拠（exam_round）が無いため、
        // 会員番号と理由を記録した上でスキップする（COL_INSTRUCTOR_FROM/TOからの計算は廃止）。
        if (empty($rounds)) {
            $this->errors[] = "会員番号 {$member->code}：備考欄に指導士認定番号の記載が無いため、指導士サイクルの作成をスキップしました。";
            return;
        }

        // [今回修正] 1人の会員が持つ instructor_update_cycles は常に1レコードのみが正しい設計。
        // 備考欄に複数回（第1回・第2回...）の記載があっても、レコードは複数作らず、
        // 最新の回（exam_round が最大のもの）の情報だけを使って1レコードにする。
        usort($rounds, fn ($a, $b) => $b['exam_round'] <=> $a['exam_round']);
        $latestRound = $rounds[0];

        // [今回追加] instructor_cycles マスターから、exam_round に対応する正しい認定期間を取得する。
        // マスターに存在しない exam_round は、会員番号と理由を記録してスキップする。
        $masterCycle = InstructorCycle::where('exam_round', $latestRound['exam_round'])->first();

        if (!$masterCycle) {
            $this->errors[] = "会員番号 {$member->code}：第{$latestRound['exam_round']}回に対応する認定期間がinstructor_cyclesに存在しないため、指導士サイクルの作成をスキップしました。";
            return;
        }

        $startDate = $masterCycle->start_date->format('Y-m-d');
        $endDate   = $masterCycle->end_date->format('Y-m-d');

        // [今回踏襲] renewal_start_date/renewal_end_date は、end_date と同じ年の 4/1〜12/1 のまま
        $endYear          = (int) $masterCycle->end_date->format('Y');
        $renewalStartDate = "{$endYear}-04-01";
        $renewalEndDate   = "{$endYear}-12-01";

        // [今回修正] 今日の日付と、更新申請受付期間（renewal_start_date〜renewal_end_date）の
        // 前後関係で status を判定する。
        //   受付期間前（まだ現行認定が有効） → updated
        //   受付期間中（今まさに更新すべきタイミング、未申請） → before_update
        //   受付期間後（更新されないまま失効） → no_update
        $today = \Carbon\Carbon::today();
        $renewalStart = \Carbon\Carbon::parse($renewalStartDate);
        $renewalEnd   = \Carbon\Carbon::parse($renewalEndDate);

        if ($today->lt($renewalStart)) {
            $initialStatus = 'updated';
        } elseif ($today->lte($renewalEnd)) {
            $initialStatus = 'before_update';
        } else {
            $initialStatus = 'no_update';
        }

        $cycleData = [
            'exam_round'         => $latestRound['exam_round'],
            'instructor_no'      => $latestRound['instructor_no'],
            'start_date'         => $startDate,
            'end_date'           => $endDate,
            'renewal_start_date' => $renewalStartDate,
            'renewal_end_date'   => $renewalEndDate,
            'total_points'       => 0,
            'conference_count'   => 0,
            'status'             => $initialStatus,
        ];

        // [今回変更] 既存レコードの有無は member_id だけで判定する（exam_round は絡めない）。
        // 既に存在する場合は完全にスキップする。
        // 理由：再インポート時に status/total_points/conference_count を無条件で上書きすると、
        // 既にシステム上で進んでいた審査状況（承認済み等）が pending に巻き戻ってしまうため。
        $exists = InstructorUpdateCycle::where('member_id', $member->id)->exists();

        if ($exists) {
            return;
        }

        InstructorUpdateCycle::create(array_merge($cycleData, [
            'member_id' => $member->id,
        ]));
    }

    // ============================================================
    // ヘルパー
    // ============================================================

    // [今回修正] null/空文字/日付型の揺れを吸収し、実質同じ値なら「変更なし」と判定する
    private function hasChanges($model, array $data): bool
    {
        foreach ($data as $key => $value) {
            $modelValue = $model->{$key};

            $normalizedModel = $this->normalizeForCompare($modelValue);
            $normalizedNew   = $this->normalizeForCompare($value);

            if ($normalizedModel !== $normalizedNew) {
                return true;
            }
        }
        return false;
    }

    private function normalizeForCompare($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }
        return (string) $value;
    }

    private function toDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveStatus(?string $status): int
    {
        return match ($status) {
            '通常会員', '通常' => 1,
            '休会'           => 2,
            '退会'           => 3,
            '除名'           => 4,
            default          => 1,
        };
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
