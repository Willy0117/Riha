<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAddress extends Model
{
    protected $table = 'member_addresses';

    protected $fillable = [
        'member_id',
        'type',
        'postal_code',
        'address1',
        'address2',
        'address3',
        'tel',
        'fax',
    ];

    protected $casts = [
        'type' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
