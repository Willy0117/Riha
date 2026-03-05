<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Application extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'organization_id',
        'application_date',
        'delivery_date',
        'vigil_datetime',
        'funeral_datetime',
        'staff_name',
        'last_name',
        'first_name',
        'deceased_furigana',
        'age_at_death',
        'gender',
        'spouse_status',
        'children_count',
        'grandchildren_count',
        'chief_mourner_name',
        'relationship_to_deceased',
        'traits',
        'special_notes',
        'text_color',
        'bg_color',
        'remarks',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'application_date'   => 'date',
        'delivery_date'      => 'date',
        'vigil_datetime'     => 'datetime',
        'funeral_datetime'   => 'datetime',
        'traits'             => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function getFullNameAttribute()
    {
        return $this->last_name . ' ' . $this->first_name;
    }

    public function getOrderCodeAttribute()
    {
        return 'P' . str_pad($this->id, 8, '0', STR_PAD_LEFT);
    }
    /*
    |--------------------------------------------------------------------------
    | Global Scope（SaaS安全対策）
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where(
                    'organization_id',
                    Auth::user()->organization_id
                );
            }
        });
    }
        // JSON化時に自動で fullname を含める
    protected $appends = ['fullname', 'order_code'];
}