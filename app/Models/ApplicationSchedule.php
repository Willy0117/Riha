<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSchedule extends Model
{
    protected $fillable = [
        'period_name',
        'application_start',
        'application_end',
        'subleader_start',
        'subleader_end',
        'reviewer_start',
        'reviewer_end',
        'chief_start',
        'chief_end',
        'subleader_notified',
        'reviewer_notified',
        'chief_notified',
    ];

    protected $casts = [
        // [今回修正] 'date' キャストのままだと、JSON化時にISO8601（UTC・Z付き）形式で
        // 出力され、フロント側でタイムゾーン変換により1日ズレることがあったため、
        // 時刻情報を持たない 'Y-m-d' 形式の文字列で返すよう明示する。
        'application_start' => 'date:Y-m-d',
        'application_end'   => 'date:Y-m-d',
        'subleader_start'   => 'date:Y-m-d',
        'subleader_end'     => 'date:Y-m-d',
        'reviewer_start'    => 'date:Y-m-d',
        'reviewer_end'      => 'date:Y-m-d',
        'chief_start'       => 'date:Y-m-d',
        'chief_end'         => 'date:Y-m-d',
        'subleader_notified' => 'boolean',
        'reviewer_notified'  => 'boolean',
        'chief_notified'     => 'boolean',
    ];
}
