<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditRolePoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_category_id',
        'credit_conference_id',
        'credit_role_id',
        'points',
        'requires_session',
    ];

    protected $casts = [
        'requires_session' => 'boolean',
    ];

    public function creditCategory()
    {
        return $this->belongsTo(CreditCategory::class, 'credit_category_id');
    }

    public function creditConference()
    {
        return $this->belongsTo(CreditConference::class, 'credit_conference_id');
    }

    public function creditRole()
    {
        return $this->belongsTo(CreditRole::class, 'credit_role_id');
    }
}