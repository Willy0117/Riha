<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    // [今回追加] MemberController@index()・formatMember() が参照する定数
    public const STATUS_ACTIVE     = 1;
    public const STATUS_SUSPENDED  = 2;
    public const STATUS_WITHDRAWN  = 3;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE    => '通常',
        self::STATUS_SUSPENDED => '休会',
        self::STATUS_WITHDRAWN => '退会',
    ];

    public const GENDER_LABELS = [
        'male'   => '男性',
        'female' => '女性',
        'other'  => 'その他',
    ];

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
        'payment_method',
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
        'status_label',
        'gender_label',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    // [今回修正] update_cycles[0] が常に最新のサイクルを指すよう、id降順を明示する
    public function updateCycles()
    {
        return $this->hasMany(InstructorUpdateCycle::class)->orderByDesc('id');
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

    /**
     * [今回追加] 年会費が「納入済」かどうかを判定する。
     * この会員が持つ年会費請求（annual_fee > 0）の中で最も古い fiscal_year から今年度まで、
     * 1年でも請求書が無い（発行漏れ）、または未納の年度があれば false（未納）を返す。
     * MyPage・事務局一覧など、年会費の納付状況を表示する全画面はこのメソッドに統一する。
     */
    public function isAnnualFeePaid(): bool
    {
        $fees = $this->invoices()->where('annual_fee', '>', 0)->get();

        if ($fees->isEmpty()) {
            return false;
        }

        // [今回修正] このプロジェクトの年度は「12/1〜翌11/30」。
        // fiscal_year は billing_end の年（＝InvoiceImport.phpと同じ基準）を使うため、
        // 「今年度」の判定も同じ基準（12月なら年+1、それ以外はそのまま）に合わせる。
        $today = now();
        $currentFiscalYear = $today->month === 12 ? $today->year + 1 : $today->year;

        $oldestYear = $fees->min('fiscal_year');

        $feesByYear = $fees->keyBy('fiscal_year');

        for ($year = $oldestYear; $year <= $currentFiscalYear; $year++) {
            $fee = $feesByYear->get($year);
            if (!$fee || $fee->status !== 'paid') {
                return false;
            }
        }

        return true;
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

    // [今回追加] status_id に対応する表示ラベル
    public function getStatusLabelAttribute()
    {
        return self::STATUS_LABELS[$this->status_id] ?? '不明';
    }

    // [今回追加] gender に対応する表示ラベル
    public function getGenderLabelAttribute()
    {
        return self::GENDER_LABELS[$this->gender] ?? '';
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
