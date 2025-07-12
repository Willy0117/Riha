<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToSaas; // BelongsToSaas トレイトをインポート

class BankAccount extends Model
{
    use HasFactory, BelongsToSaas; // トレイトを使用

    /**
     * モデルに関連付けるテーブル
     * デフォルトではクラス名（BankAccount）の複数形（bank_accounts）が使用されます。
     */
    protected $table = 'bank_accounts';

    /**
     * 複数代入可能な属性
     * 'saas_id' もfillableに含めるのを忘れないでください。
     * 'accountable_type' と 'accountable_id' はポリモーフィックリレーションシップに必要です。
     */
    protected $fillable = [
        'saas_id','accountable_type','accountable_id',
        'bank_name','branch_name','account_type','account_number','account_holder_name',
    ];

    /**
     * Carbonインスタンスに変換する日付属性
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * この銀行口座情報が関連付けられているモデルを取得します。
     * （例: Customer, Department）
     */
    public function accountable()
    {
        return $this->morphTo();
    }

    // 必要に応じて、追加のメソッドやリレーションシップをここに定義できます
}