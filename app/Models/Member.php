<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'name',
        'tel',
        'status_id',
        'name_kana',
        'name_prefix',
        'name_suffix',
        'postal_code',
        'address1',
        'address2',
        'address3',
        'mobile',
        'fax',
        'email',
    ];
    
    protected $appends = [
        'full_name',
        'full_address',
    ];

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
    
    public function bankAccount()
    {
        return $this->hasOne(BankAccount::class);
    }

    /* =====================
     |  表示用ラベル
     * ===================== */
    public function getFullNameAttribute()
    {
        return trim(
            ($this->name_prefix ?? '') .
            ($this->name ?? '') .
            ($this->name_suffix ?? '')
        );
    }

    public function documents()
    {
        return $this->hasMany(OrganizationDocument::class);
    }

    public function getFullAddressAttribute()
    {
        return collect([
            $this->address1,
            $this->address2,
            $this->address3,
        ])->filter()->implode('');
    }

}
