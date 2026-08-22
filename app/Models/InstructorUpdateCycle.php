<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorUpdateCycle extends Model
{
    protected $fillable = [
        'member_id',
        'instructor_no',
        'exam_round',
        'start_date',
        'end_date',
        'status',
        'reason',
        'renewal_start_date',
        'renewal_end_date',
        'reviewer_admin_id',
        'reviewer_assigned_at',
        'reviewer_judgment',
        'reviewer_judged_at',
        'total_points',
        'conference_count',
    ];

    protected $casts = [
        'start_date'            => 'date',
        'end_date'              => 'date',
        'renewal_start_date'    => 'date',
        'renewal_end_date'      => 'date',
        'reviewer_assigned_at'  => 'datetime',
        'reviewer_judged_at'    => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * 担当審査員（Admin）
     */
    public function reviewerAdmin()
    {
        return $this->belongsTo(Admin::class, 'reviewer_admin_id');
    }



}
