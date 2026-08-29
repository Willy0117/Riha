<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'organization_id',
        'code',
        'first_name',
        'last_name',
        'last_name_kana',
        'first_name_kana',
        'gender',
        'birthdate',
        'position',
        'tel',
        'mobile',
        'fax',
        'email',
        'personal_email',
        'status_id',
        'member_type',
        'joined_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'birthdate'    => 'date',
        'joined_at'    => 'date',
        'withdrawn_at' => 'date',
    ];

    // [今回追加]
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // [今回変更] name・name_kana・full_name・full_address は実カラムではなく、
    // 全てアクセサ経由で計算する
    protected $appends = [
        'name',
        'name_kana',
        'full_name',
        'full_address',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function updateCycles()
    {
        return $this->hasMany(InstructorUpdateCycle::class);
    }
    
    public function latestCycle()
    {
        return $this->hasOne(InstructorUpdateCycle::class)
            ->latestOfMany('end_date');
    }

    public function pdfUploads()
    {
        return $this->hasMany(PdfUpload::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function bankAccount()
    {
        return $this->hasOne(BankAccount::class);
    }
    public function roles()
    {
        return $this->hasMany(MemberRole::class);
    }
    
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function addresses()
    {
        return $this->hasMany(MemberAddress::class);
    }
 
    public function educations()
    {
        return $this->hasMany(MemberEducation::class);
    }
 
    public function degrees()
    {
        return $this->hasMany(MemberDegree::class);
    }
 
    public function committees()
    {
        return $this->hasMany(MemberCommittee::class);
    }    

    /* =====================
     |  表示用ラベル
     * ===================== */

    // [今回追加] name（旧・実カラム）の代替。last_name + first_name を結合して返す
    public function getNameAttribute()
    {
        return $this->getFullNameAttribute();
    }

    // [今回追加] name_kana（旧・実カラム）の代替。last_name_kana + first_name_kana を結合して返す
    public function getNameKanaAttribute()
    {
        return collect([
            $this->last_name_kana,
            $this->first_name_kana,
        ])->filter()->implode('');
    }

    public function getFullNameAttribute()
    {
        return collect([
            $this->last_name,
            $this->first_name,
        ])->filter()->implode('');
    }

    // [今回変更] postal_code/address1〜3 は members から削除したため、
    // member_addresses 側（type=1:自宅 を優先）から組み立てる。
    // [要確認] N+1 対策として、呼び出し側で ->with('addresses') を忘れずに。
    public function getFullAddressAttribute()
    {
        $primary = $this->addresses->firstWhere('type', 1)
            ?? $this->addresses->first();

        if (!$primary) {
            return '';
        }

        return collect([
            $primary->address1,
            $primary->address2,
            $primary->address3,
        ])->filter()->implode('');
    }

}
