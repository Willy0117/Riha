<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\MemberAddress;
use App\Models\MemberCommittee;
use App\Models\MemberDegree;
use App\Models\MemberEducation;
use App\Models\MemberRole;
use App\Models\InstructorUpdateCycle;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class MemberImport implements ToCollection, WithChunkReading
{
    // ヘッダー行数（1〜4行目はヘッダー、5行目も空行のためスキップ）
    private const HEADER_ROWS = 5;

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

    // 会員基本情報
    private const COL_MEMBER_NUMBER   = 0;   // 会員番号
    private const COL_JOINED_AT       = 4;   // 入会日
    private const COL_WITHDRAWN_AT    = 7;   // 退会日
    private const COL_STATUS          = 8;   // 会員状況
    private const COL_MEMBER_TYPE     = 12;  // 会員種別
    private const COL_LAST_NAME       = 13;  // 姓
    private const COL_FIRST_NAME      = 14;  // 名
    private const COL_LAST_NAME_KANA  = 15;  // せい
    private const COL_FIRST_NAME_KANA = 16;  // めい
    private const COL_GENDER          = 17;  // 性別
    private const COL_BIRTHDATE       = 18;  // 生年月日
    private const COL_EMAIL           = 25;  // メールアドレス（ログインID）

    // 所属
    private const COL_ORG_NAME        = 26;  // 勤務先・在学先名
    private const COL_ORG_DEPT        = 27;  // 所属・学部名
    private const COL_ORG_SECTION     = 28;  // 部署・学科名
    private const COL_POSITION        = 31;  // 役職名
    private const COL_ORG_TEL         = 36;  // 所属先電話番号
    private const COL_ORG_FAX         = 38;  // 所属先FAX

    // 自宅住所
    private const COL_HOME_POSTAL     = 40;  // 自宅郵便番号
    private const COL_HOME_PREF       = 41;  // 自宅都道府県
    private const COL_HOME_ADDRESS    = 42;  // 自宅住所
    private const COL_PERSONAL_EMAIL  = 43;  // 個人メールアドレス
    private const COL_MOBILE          = 44;  // 電話番号（携帯）
    private const COL_HOME_FAX        = 45;  // 自宅FAX

    // 送付先住所
    private const COL_SEND_TYPE       = 46;  // 郵送物送付先（自宅 or 勤務先）
    private const COL_SEND_POSTAL     = 48;  // 送付先郵便番号
    private const COL_SEND_ADDRESS    = 49;  // 送付先住所

    // 学歴
    private const COL_EDU_SCHOOL      = 50;  // 学校名
    private const COL_EDU_FACULTY     = 51;  // 学部・学科名
    private const COL_EDU_GRADUATED   = 52;  // 卒業（予定）年月

    // 学位（最大5件、3列ずつ: 学位名・取得年月）
    private const COL_DEGREE_START    = 53;  // col053〜062（2列×5件）
    private const DEGREE_COUNT        = 5;

    // 指導士資格
    private const COL_INSTRUCTOR      = 63;  // 指導士資格所持（あり/なし）
    private const COL_INSTRUCTOR_FROM = 64;  // 取得年
    private const COL_INSTRUCTOR_TO   = 65;  // 更新予定年

    // 学会役職歴（最大10件、3列ずつ: 担当役職・開始日・終了日）
    private const COL_ROLE_START      = 67;  // col067〜096
    private const ROLE_COUNT          = 10;

    // 学会委員歴（最大10件、3列ずつ: 担当委員・開始日・終了日）
    private const COL_COMMITTEE_START = 97;  // col097〜126
    private const COMMITTEE_COUNT     = 10;

    // ============================================================

    public function collection(Collection $rows)
    {
        // ヘッダー行をスキップ
        $dataRows = $rows->slice(self::HEADER_ROWS)->filter(
            fn($row) => !empty($row[self::COL_MEMBER_NUMBER])
        );

        if ($dataRows->isEmpty()) {
            return;
        }

        // 会員番号一覧で既存Memberを一括取得
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
                    'message'       => $e->getMessage(),
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
                'code'      => $memberNumber,
                'status_id' => 1, // 新規はデフォルト1（通常）
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

    private function syncUser(Member $member, $row): void
    {
        $code  = (string) $row[self::COL_MEMBER_NUMBER];
        $email = $row[self::COL_EMAIL] ?? null;
        $name  = trim(($row[self::COL_LAST_NAME] ?? '') . ($row[self::COL_FIRST_NAME] ?? ''));

        $user = User::where('username', $code)->first();

        if (!$user) {
            $user = User::create([
                'tenant_id'  => $this->tenantId,
                'member_id'  => $member->id,
                'username'   => $code,
                'name'       => $name,
                'email'      => $email ?? $code . '@example.com',
                'password'   => Hash::make('1234'),
                'status'     => 1,
            ]);
        }
    }

    // ============================================================
    // members
    // ============================================================

    private function buildMemberData($row): array
    {
        // organizations は名前で検索（なければnull）
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
            // status_idはExcelに値がないため除外（新規時はprocessRowで設定）
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

        // 自宅住所
        $homePostal  = $row[self::COL_HOME_POSTAL] ?? null;
        $homePref    = $row[self::COL_HOME_PREF] ?? null;
        $homeAddress = $row[self::COL_HOME_ADDRESS] ?? null;
        if ($homePostal || $homePref || $homeAddress) {
            $inserts[] = [
                'member_id'  => $member->id,
                'type'       => 1, // 自宅
                'postal_code'=> $homePostal,
                'address1'   => $homePref,
                'address2'   => $homeAddress,
                'address3'   => null,
                'tel'        => $row[self::COL_MOBILE] ?? null,
                'fax'        => $row[self::COL_HOME_FAX] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // 送付先
        $sendPostal  = $row[self::COL_SEND_POSTAL] ?? null;
        $sendAddress = $row[self::COL_SEND_ADDRESS] ?? null;
        if ($sendPostal || $sendAddress) {
            $inserts[] = [
                'member_id'  => $member->id,
                'type'       => 2, // 送付先
                'postal_code'=> $sendPostal,
                'address1'   => null,
                'address2'   => $sendAddress,
                'address3'   => null,
                'tel'        => null,
                'fax'        => null,
                'created_at' => now(),
                'updated_at' => now(),
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
            $degreeCol     = self::COL_DEGREE_START + ($i * 2);
            $obtainedCol   = $degreeCol + 1;
            $degree        = $row[$degreeCol] ?? null;
            $obtainedAt    = $row[$obtainedCol] ?? null;

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
            $role     = $row[$roleCol] ?? null;
            $startedAt = $row[$startCol] ?? null;
            $endedAt   = $row[$endCol] ?? null;

            if ($role || $startedAt) {
                $inserts[] = [
                    'member_id'  => $member->id,
                    'role'       => $role,
                    'started_at' => $startedAt,
                    'ended_at'   => $endedAt,
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
 
        // 「あり」のみ登録
        if ($hasInstructor !== 'あり') {
            return;
        }
 
        $startYear = $row[self::COL_INSTRUCTOR_FROM] ?? null;
        $endYear   = $row[self::COL_INSTRUCTOR_TO] ?? null;
 
        if (!$startYear || !$endYear) {
            return;
        }
 
        $endDate          = "{$endYear}-12-31";
        $renewalStartDate = "{$endYear}-04-01";
        $renewalEndDate   = $endDate;
 
        $cycleData = [
            'exam_round'         => 0,
            'instructor_no'      => '',
            'start_date'         => "{$startYear}-01-01",
            'end_date'           => $endDate,
            'renewal_start_date' => $renewalStartDate,
            'renewal_end_date'   => $renewalEndDate,
            'total_points'       => 0,
            'conference_count'   => 0,
            'status'             => 'pending',
        ];
 
        $existing = InstructorUpdateCycle::where('member_id', $member->id)
            ->where('start_date', "{$startYear}-01-01")
            ->where('end_date', $endDate)
            ->first();
 
        if ($existing) {
            $existing->update($cycleData);
        } else {
            InstructorUpdateCycle::create(array_merge($cycleData, [
                'member_id' => $member->id,
            ]));
        }
    }

    // ============================================================
    // ヘルパー
    // ============================================================

    private function hasChanges($model, array $data): bool
    {
        foreach ($data as $key => $value) {
            if ((string) $model->{$key} !== (string) $value) {
                return true;
            }
        }
        return false;
    }

    private function toDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        // Excelのシリアル値 or 文字列どちらも対応
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        // 文字列の場合
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveStatus(?string $status): ?int
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