<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamDocument extends Model
{
    use HasFactory;

    protected $table = 'exam_documents';

    protected $guarded = ['id'];

    public const TYPE_CHECKLIST    = 'checklist';
    public const TYPE_CERTIFICATE  = 'certificate';  // 様式2
    public const TYPE_RECOME1      = 'recome1';      // 様式3-1（様式2提出者向け）
    public const TYPE_RECOME2      = 'recome2';      // 様式3-2（様式2非提出者向け）

    public const TYPE_LABELS = [
        self::TYPE_CHECKLIST   => '表紙（チェックリスト）',
        self::TYPE_CERTIFICATE => '様式2：主演者・座長経験証明書',
        self::TYPE_RECOME1     => '様式3-1：推薦状',
        self::TYPE_RECOME2     => '様式3-2：推薦状',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
