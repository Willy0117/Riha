<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    // 既存モデル群（Member等）と同じく、Controller側でバリデーション済みの配列を
    // そのままcreate()に渡す運用に合わせ、$guardedはidのみとする。
    protected $guarded = ['id'];

    protected $casts = [
        'birthdate'             => 'date',
        'member_joined_at_snapshot' => 'date',
        'reviewer_assigned_at'  => 'datetime',
        'reviewer_judged_at'    => 'datetime',
        'applied_at'            => 'datetime',
        'certified_at'          => 'datetime',
        'chief_flagged_items'   => 'array',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECT    = 'reject';
    public const STATUS_CERTIFIED = 'certified';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT     => '下書き',
        self::STATUS_SUBMITTED => '提出済み（アサイン待ち）',
        self::STATUS_PENDING   => '審査中',
        self::STATUS_APPROVED  => '承認（委員長）',
        self::STATUS_REJECT    => '不合格',
        self::STATUS_CERTIFIED => '認定済み',
    ];

    public const ROUTE_LABELS = [
        'A' => '様式2提出（主演者・座長経験あり）',
        'B' => '様式2非提出（自験例報告書提出）',
    ];

    // ── リレーション ──

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function reviewerAdmin()
    {
        return $this->belongsTo(Admin::class, 'reviewer_admin_id');
    }

    public function caseReports()
    {
        return $this->hasMany(CaseReport::class)->orderBy('case_no');
    }

    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    // ── アクセサ ──

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getRouteLabelAttribute(): string
    {
        return self::ROUTE_LABELS[$this->route] ?? $this->route;
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->last_name ?? '') . ' ' . ($this->first_name ?? ''));
    }

    // ルートAは様式2提出者。自験例報告書（case_reports）の提出自体が不要。
    public function getRequiresCaseReportsAttribute(): bool
    {
        return $this->route === 'B';
    }
}
