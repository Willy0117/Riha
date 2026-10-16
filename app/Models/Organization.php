<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Organization extends Model
{
    // [今回追加] organizations.contract_status（実DB列コメントに準拠）
    public const STATUS_NEW        = 0;
    public const STATUS_ACTIVE     = 1;
    public const STATUS_ENDED      = 2;
    public const STATUS_FREE       = 3;
    public const STATUS_INDIVIDUAL = 4;
    public const STATUS_NO_RENEWAL = 5;

    public const STATUS_LABELS = [
        self::STATUS_NEW        => '新規',
        self::STATUS_ACTIVE     => '契約中',
        self::STATUS_ENDED      => '契約終了',
        self::STATUS_FREE       => '特別枠（請求なし）',
        self::STATUS_INDIVIDUAL => 'ドクター個人契約',
        self::STATUS_NO_RENEWAL => '更新しない',
    ];

    protected $fillable = [
        'member_id',
        'type',
        'position',
        'name',
        'department',
        'section',
        'name_kana',
        'name_prefix',
        'name_suffix',
        'postal_code',
        'address1',
        'address2',
        'address3',
        'last_name',
        'first_name',
        'tel',
        'mobile',
        'fax',
        'email',
        'allow_text_color',
        'allow_background_color',
        // [今回追加] 契約管理関連
        'contract_no',
        'code',
        'url',
        'contract_status',
        'contract_date',
        'new_contract_date',
        'license_issued_at',
        'payment_method',
        'rep_position',
        'rep_last_name',
        'rep_first_name',
    ];

    protected $casts = [
        'contract_date'     => 'date',
        'new_contract_date' => 'date',
        'license_issued_at' => 'date',
        'reminder_sent_at'  => 'datetime',
    ];

    /* ===== 表示用 ===== */

    public function getFullNameAttribute()
    {
        return trim(
            ($this->name_prefix ?? '') .
            ($this->name ?? '') .
            ($this->name_suffix ?? '')
        );
    }

    // [今回追加] contract_statusの表示ラベル
    public function getStatusLabelAttribute()
    {
        return self::STATUS_LABELS[$this->contract_status] ?? '不明';
    }

    public function documents()
    {
        return $this->hasMany(OrganizationDocument::class);
    }

    // [今回追加] Member::class側から見た本来のリレーション先。
    // Member→Organizationに反転済みのため、こちらが正しい方向（1施設に複数の先生）。
    public function members()
    {
        return $this->hasMany(Member::class);
    }

    // [注意] member_id（Organization→Member方向）は反転前の名残。
    // 新規に使う想定はないが、既存データ互換のため残す。
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    // [今回追加] 住所（所在地・送付先・請求先）
    public function addresses()
    {
        return $this->hasMany(OrganizationAddress::class);
    }

    public function locationAddress()
    {
        return $this->hasOne(OrganizationAddress::class)
            ->where('type', OrganizationAddress::TYPE_LOCATION);
    }

    public function shippingAddress()
    {
        return $this->hasOne(OrganizationAddress::class)
            ->where('type', OrganizationAddress::TYPE_SHIPPING);
    }

    public function billingAddress()
    {
        return $this->hasOne(OrganizationAddress::class)
            ->where('type', OrganizationAddress::TYPE_BILLING);
    }

    // [今回追加] 契約履歴（料金マスタ取得・請求書/Stripe発行の重複チェックに使用）
    // [要確認] organization_contracts テーブル自体がまだ実在するか未確認
    public function contracts()
    {
        return $this->hasMany(OrganizationContract::class);
    }

    // [今回追加] 契約申込・招待メール送信用トークンを再生成して保存する
    public function generateRegisterToken(): string
    {
        $token = Str::random(40);
        $this->update(['register_token' => $token]);
        return $token;
    }

    // [今回追加] 一覧画面の検索（キーワードでname/abbrを部分一致検索）
    public function scopeSearch($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('abbr', 'like', "%{$term}%");
        });
    }

    public function getFullAddressAttribute()
    {
        return collect([
            $this->address1,
            $this->address2,
            $this->address3,
        ])->filter()->implode('');
    }

    public function getContactNameAttribute()
    {
        return collect([
            $this->last_name,
            $this->first_name,
        ])->filter()->implode(' ');
    }
}

