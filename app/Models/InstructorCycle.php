<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 指導士の「第N回」ごとの正しい認定期間を保持するマスターテーブル。
 * MemberImport.php が、備考欄から抽出した exam_round をキーに、
 * この期間を参照して instructor_update_cycles を作成する。
 *
 * [今回追加] application_start/application_end：その回の受験申込の受付期間。
 * My Page側の受験申込画面は、現在日時がこの期間に該当する回を「今募集中の回」として扱う。
 */
class InstructorCycle extends Model
{
    protected $table = 'instructor_cycles';

    protected $fillable = [
        'exam_round',
        'application_start',
        'application_end',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'application_start' => 'date',
        'application_end' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * [今回修正] 現在日時が募集期間内にある回を返す（無ければ null）。
     * application_start は必須（未設定なら「いつから募集か不明」として対象外）。
     * application_end が NULL の場合は「締切がまだ決まっていない」とみなし、
     * application_start を過ぎていれば募集中として扱う。
     * 同時に複数回が並行して募集されない前提のため、最新1件を返す。
     */
    public static function currentlyRecruiting(): ?self
    {
        $today = now()->toDateString();

        return self::whereNotNull('application_start')
            ->whereDate('application_start', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('application_end')
                    ->orWhereDate('application_end', '>=', $today);
            })
            ->orderByDesc('exam_round')
            ->first();
    }
}
