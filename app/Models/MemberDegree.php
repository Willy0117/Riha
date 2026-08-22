<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberDegree extends Model
{
    protected $table = 'member_degrees';

    protected $fillable = [
        'member_id',
        'degree',
        'obtained_at',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
