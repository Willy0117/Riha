<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseReport extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_detailed' => 'boolean',
    ];

    // [今回修正] 様式5（詳細）の対象は No.1・No.2 固定ではなく、
    // 「10症例のうち任意の2症例」を先生自身が選ぶ（募集要項の記載通り）。
    // 選択できる件数の上限
    public const DETAILED_LIMIT = 2;

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function getVisitTypeLabelAttribute(): ?string
    {
        return match ($this->visit_type) {
            'outpatient' => '外来',
            'inpatient'  => '入院',
            default      => null,
        };
    }
}
