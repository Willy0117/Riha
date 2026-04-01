<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'tel',
        'status_id',
        'company_name',
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
        return collect([
            $this->last_name,
            $this->first_name,
        ])->filter()->implode('');
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
