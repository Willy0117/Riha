<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 指導士の「第N回」ごとの正しい認定期間を保持するマスターテーブル。
 * MemberImport.php が、備考欄から抽出した exam_round をキーに、
 * この期間を参照して instructor_update_cycles を作成する。
 */
class InstructorCycle extends Model
{
    protected $table = 'instructor_cycles';

    protected $fillable = [
        'exam_round',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
