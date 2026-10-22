<?php

namespace App\Support;

use App\Models\ApplicationSchedule;
use Illuminate\Support\Collection;

/**
 * 申請（updated_at = 申請日）が、申請受付スケジュールの第何期に当たるかを判定する。
 * 期間が重なる場合は先に始まる期を優先。どの期にも当たらなければ null。
 */
class ApplicationPeriod
{
    /** 凡例用の期一覧 [{no, name, start, end}] */
    public static function list(): array
    {
        return ApplicationSchedule::orderBy('application_start')
            ->get(['id', 'period_name', 'application_start', 'application_end'])
            ->values()
            ->map(fn ($s, $i) => [
                'no' => $i + 1,
                'name' => $s->period_name,
                'start' => (string) $s->application_start,
                'end' => (string) $s->application_end,
            ])->all();
    }

    /** 各 cycle に application_period（期の番号 1〜）を付与する */
    public static function annotate(Collection $cycles, array $periods): void
    {
        foreach ($cycles as $cycle) {
            $date = $cycle->updated_at?->toDateString();
            $cycle->application_period = null;
            foreach ($periods as $p) {
                if ($date && $date >= substr($p['start'], 0, 10) && $date <= substr($p['end'], 0, 10)) {
                    $cycle->application_period = $p['no'];
                    break;
                }
            }
        }
    }
}
