<?php

namespace App\Support;

use App\Models\ApplicationSchedule;
use Illuminate\Support\Collection;

/**
 * 申請（updated_at = 申請日）が、申請受付スケジュールの第何期に当たるかを判定する。
 * 期間が重なる場合は先に始まる期を優先。どの期にも当たらなければ null。
 *
 * あわせて、その期のアサイン/審査/委員長の期限（subleader_end / reviewer_end / chief_end）を
 * 過ぎているかを cycle->overdue（subleader / reviewer / chief の真偽値）に付与する。
 * 期限の「翌日」から超過とする。アサインは審査員が未割当のときだけ超過とする。
 */
class ApplicationPeriod
{
    /** 凡例用の期一覧 [{no, name, start, end, subleader_end, reviewer_end, chief_end}] */
    public static function list(): array
    {
        return ApplicationSchedule::orderBy('application_start')
            ->get(['id', 'period_name', 'application_start', 'application_end',
                   'subleader_end', 'reviewer_end', 'chief_end'])
            ->values()
            ->map(fn ($s, $i) => [
                'no' => $i + 1,
                'name' => $s->period_name,
                'start' => (string) $s->application_start,
                'end' => (string) $s->application_end,
                'subleader_end' => substr((string) $s->subleader_end, 0, 10),
                'reviewer_end' => substr((string) $s->reviewer_end, 0, 10),
                'chief_end' => substr((string) $s->chief_end, 0, 10),
            ])->all();
    }

    /** 各 cycle に application_period（期の番号 1〜）と overdue（期限超過）を付与する */
    public static function annotate(Collection $cycles, array $periods): void
    {
        $today = now()->toDateString();

        foreach ($cycles as $cycle) {
            $date = $cycle->updated_at?->toDateString();
            $cycle->application_period = null;
            $cycle->overdue = ['subleader' => false, 'reviewer' => false, 'chief' => false];

            foreach ($periods as $p) {
                if ($date && $date >= substr($p['start'], 0, 10) && $date <= substr($p['end'], 0, 10)) {
                    $cycle->application_period = $p['no'];
                    $cycle->overdue = [
                        'subleader' => empty($cycle->reviewer_admin_id)
                            && $p['subleader_end'] !== '' && $today > $p['subleader_end'],
                        'reviewer' => $p['reviewer_end'] !== '' && $today > $p['reviewer_end'],
                        'chief' => $p['chief_end'] !== '' && $today > $p['chief_end'],
                    ];
                    break;
                }
            }
        }
    }
}
