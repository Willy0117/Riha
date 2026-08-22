<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberEducation extends Model
{
    protected $table = 'member_educations';

    protected $fillable = [
        'member_id',
        'school_name',
        'faculty',
        'graduated_at',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
