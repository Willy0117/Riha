<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'member_id',
        'invoice_number',
        'fiscal_year',
        'billing_start',
        'billing_end',
        'invoice_date',
        'due_date',
        'payment_date',
        'invoice_type',
        'invoice_name',
        'payment_method',
        'annual_fee',
        'exam_fee',
        'renewal_fee',
        'seminar_fee',
        'member_adjustment',
        'member_adjustment_temp',
        'invoice_adjustment',
        'tax_amount',
        'total_amount',
        'payment_amount',
        'balance',
        'status',
        'stripe_invoice_id',
        'stripe_payment_intent_id',
        'paid_at',
        'memo_member',
        'memo_admin',
    ];

    protected $casts = [
        'billing_start'          => 'date',
        'billing_end'            => 'date',
        'invoice_date'           => 'date',
        'due_date'               => 'date',
        'payment_date'           => 'date',
        'paid_at'                => 'datetime',
        'fiscal_year'            => 'integer',
        'annual_fee'             => 'integer',
        'exam_fee'               => 'integer',
        'renewal_fee'            => 'integer',
        'seminar_fee'            => 'integer',
        'member_adjustment'      => 'integer',
        'member_adjustment_temp' => 'integer',
        'invoice_adjustment'     => 'integer',
        'tax_amount'             => 'integer',
        'total_amount'           => 'integer',
        'payment_amount'         => 'integer',
        'balance'                => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
