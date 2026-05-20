<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorCycle extends Model
{
    protected $table = 'instructor_cycles';

    protected $fillable = [
        'exam_round',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'exam_round' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}