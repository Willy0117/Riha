<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberRole extends Model
{
    protected $table = 'member_roles';

    protected $fillable = [
        'member_id',
        'role',
        'started_at',
        'ended_at',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
