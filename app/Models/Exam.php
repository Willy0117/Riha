<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $table = 'exams';

    // 既存モデル群（Member等）と同じく、Controller側でバリデーション済みの配列を
    // そのままcreate()に渡す運用に合わせ、$guardedはidのみとする。
    protected $guarded = ['id'];

    protected $casts = [
        'birthdate'             => 'date',
        'member_joined_at_snapshot' => 'date',
        'reviewer_assigned_at'  => 'datetime',
        'reviewer_judged_at'    => 'datetime',
        'applied_at'            => 'datetime',
        'exam_result_recorded_at' => 'datetime',
        'certified_at'          => 'datetime',
        'chief_flagged_items'   => 'array',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECT    = 'reject';
    public const STATUS_CERTIFIED = 'certified';

    // [今回修正] status は「書類審査を通過し受験資格が認められたか」を表すのみ。
    // 試験本番（CBT）の合否は exam_result で別途管理する。
    public const STATUS_LABELS = [
        self::STATUS_DRAFT     => '下書き',
        self::STATUS_SUBMITTED => '提出済み（アサイン待ち）',
        self::STATUS_PENDING   => '書類審査中',
        self::STATUS_APPROVED  => '受験資格あり（受験許可）',
        self::STATUS_REJECT    => '受験資格なし',
        self::STATUS_CERTIFIED => '指導士認定済み',
    ];

    public const EXAM_RESULT_PENDING = 'pending';
    public const EXAM_RESULT_PASS    = 'pass';
    public const EXAM_RESULT_FAIL    = 'fail';

    public const EXAM_RESULT_LABELS = [
        self::EXAM_RESULT_PENDING => '未受験・結果未登録',
        self::EXAM_RESULT_PASS    => '合格',
        self::EXAM_RESULT_FAIL    => '不合格',
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

    public function instructorCycle()
    {
        return $this->belongsTo(InstructorCycle::class, 'exam_round', 'exam_round');
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
        return $this->hasMany(ExamDocument::class);
    }

    // ── アクセサ ──

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getExamResultLabelAttribute(): string
    {
        return self::EXAM_RESULT_LABELS[$this->exam_result] ?? $this->exam_result;
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
