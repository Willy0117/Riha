<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAddress extends Model
{
    protected $fillable = [
        'organization_id',
        'type',
        'name',
        'postal_code',
        'address1',
        'address2',
        'address3',
        'tel',
        'fax',
        'email',
    ];

    protected $casts = [
        'type' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
