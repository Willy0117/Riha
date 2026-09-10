<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\User;
use App\Models\InstructorUpdateCycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * [今回追加] 1人の会員が複数の instructor_update_cycles を持ってしまっている
 * データ不整合を解消するための1回限りの移行コマンド。
 *
 * 各会員の instructor_update_cycles のうち、1件目（id最小）はそのまま元の会員に残し、
 * 2件目以降は、会員情報を複製した「別会員」を新規作成した上で、そちらに付け替える。
 * 複製先の first_name は「元のfirst_name + 連番（2件目なら2、3件目なら3...）」とする。
 * code は 000001 から順に採番する（既存に000001〜が存在しないことを確認済み）。
 * users も新規作成する（username=新code、email=元と同じ、password固定値）。
 *
 * 使い方：
 *   php artisan instructor-cycles:split-duplicates --dry-run   （確認のみ、DBは変更しない）
 *   php artisan instructor-cycles:split-duplicates             （実行）
 */
class SplitDuplicateInstructorCycles extends Command
{
    protected $signature = 'instructor-cycles:split-duplicates {--dry-run}';

    protected $description = '1人の会員が複数持ってしまっている instructor_update_cycles を、別会員に分離する';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        // [今回修正] code の採番カウンタ。再実行時に既存の 0000xx 番号と衝突しないよう、
        // 既に使われている「0000で始まる6桁コード」の最大値の次から開始する。
        $maxNewCode = Member::where('code', 'REGEXP', '^0000[0-9]{2}$')->max('code');
        $nextCodeNumber = $maxNewCode ? ((int) $maxNewCode + 1) : 1;
        $this->info("採番開始番号: " . str_pad($nextCodeNumber, 6, '0', STR_PAD_LEFT));

        // member_id ごとにグループ化し、2件以上持つ会員だけを対象にする
        $duplicateMemberIds = InstructorUpdateCycle::select('member_id')
            ->groupBy('member_id')
            ->havingRaw('COUNT(*) >= 2')
            ->pluck('member_id');

        $this->info("対象会員数: {$duplicateMemberIds->count()}件");

        if ($isDryRun) {
            $this->warn('--dry-run 指定のため、DBへの変更は行いません。');
        }

        $totalMoved = 0;

        foreach ($duplicateMemberIds as $memberId) {
            $member = Member::find($memberId);
            if (!$member) {
                $this->error("member_id={$memberId} の会員が見つかりません。スキップします。");
                continue;
            }

            // id昇順で取得。1件目（最小id）は元の会員に残し、2件目以降を分離対象にする
            $cycles = InstructorUpdateCycle::where('member_id', $memberId)
                ->orderBy('id')
                ->get();

            $extraCycles = $cycles->slice(1); // 2件目以降

            $suffix = 2; // 2件目の複製から「+2」を付与

            foreach ($extraCycles as $cycle) {
                $newCode = str_pad($nextCodeNumber, 6, '0', STR_PAD_LEFT);

                $this->line(
                    "member_id={$memberId}（code={$member->code}）の cycle_id={$cycle->id}"
                    . " を新会員 code={$newCode}（first_name={$member->first_name}+{$suffix}）へ移行"
                );

                if (!$isDryRun) {
                    DB::transaction(function () use ($member, $cycle, $newCode, $suffix) {
                        // [今回追加] 会員の基本情報だけを複製する（住所・学歴・学位・役職歴・委員歴は複製しない）
                        $newMember = Member::create([
                            'organization_id' => $member->organization_id,
                            'code'            => $newCode,
                            'first_name'      => $member->first_name . '+' . $suffix,
                            'last_name'       => $member->last_name,
                            'last_name_kana'  => $member->last_name_kana,
                            'first_name_kana' => $member->first_name_kana,
                            'gender'          => $member->gender,
                            'birthdate'       => $member->birthdate,
                            'position'        => $member->position,
                            'tel'             => $member->tel,
                            'mobile'          => $member->mobile,
                            'fax'             => $member->fax,
                            // [今回追加] members.email が未設定の会員向けにダミー値を仮設定する
                            'email'           => $member->email ?: "{$newCode}@noemail.local",
                            'personal_email'  => $member->personal_email,
                            'status_id'       => $member->status_id,
                            'member_type'     => $member->member_type,
                            'payment_method'  => $member->payment_method,
                            'joined_at'       => $member->joined_at,
                            'withdrawn_at'    => $member->withdrawn_at,
                        ]);

                        // users も新規作成する
                        User::create([
                            'tenant_id'  => $member->user?->tenant_id ?? 1,
                            'member_id'  => $newMember->id,
                            'username'   => $newCode,
                            'email'      => $newMember->email,
                            'password'   => Hash::make('12345678'),
                        ]);

                        // instructor_update_cycles を新会員に付け替える
                        $cycle->member_id = $newMember->id;
                        $cycle->save();
                    });
                }

                $nextCodeNumber++;
                $suffix++;
                $totalMoved++;
            }
        }

        $this->info("完了。移行件数: {$totalMoved}件");

        return self::SUCCESS;
    }
}
