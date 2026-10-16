<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAddress extends Model
{
    // [今回追加] organization_addresses.type
    // 所属先(施設)は今後「病院名+所属+部署」で1件のOrganizationとするため、
    // 住所も実質1件（TYPE_LOCATION）のみを使う運用にする。
    // SHIPPING/BILLINGは既存コード(OrganizationController)との互換のために残すが、
    // 新規データでは使わない想定。
    public const TYPE_LOCATION = 1;
    public const TYPE_SHIPPING = 2;
    public const TYPE_BILLING  = 3;

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
