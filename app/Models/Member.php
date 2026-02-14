<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'type',
        'last_name',
        'first_name',
        'last_name_kana',
        'first_name_kana',
        'agree',
        'affiliate',
        'agreed_at',
        'status_id',
        'progress_id',
        'agent', 
    ];

    protected $casts = [
        'agent' => 'boolean',
        'agree' => 'boolean',
        'affiliate' => 'boolean',
        'agreed_at' => 'datetime',
    ];

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function progress()
    {
        return $this->belongsTo(Progress::class, 'progress_id'); 
    }

    public function organization()
    {
        return $this->hasOne(Organization::class);
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    public function applicationOrganization()
    {
        return $this->hasOne(ApplicationOrganization::class);
    }

    public function applicationOrganizations()
    {
        return $this->hasMany(ApplicationOrganization::class);
    }

    public function getFullNameAttribute()
    {
        return trim($this->last_name . ' ' . $this->first_name);
    }
    
    public function bankAccount()
    {
        return $this->hasOne(BankAccount::class);
    }

    /* =====================
     |  表示用ラベル
     * ===================== */

    // 法人 / 個人事業主
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'corporation' => '法人',
            'solo' => '個人事業主',
            default => '-',
        };
    }

    // 代理人申請 / 本人申請
    public function getAgentLabelAttribute(): string
    {
        return $this->agent ? '代理人申請' : '本人申請';
    }

}
