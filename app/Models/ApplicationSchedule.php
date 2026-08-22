<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSchedule extends Model
{
    protected $fillable = [
        'period_name',
        'application_start',
        'application_end',
        'subleader_start',
        'subleader_end',
        'reviewer_start',
        'reviewer_end',
        'chief_start',
        'chief_end',
        'subleader_notified',
        'reviewer_notified',
        'chief_notified',
    ];

    protected $casts = [
        'application_start' => 'date',
        'application_end'   => 'date',
        'subleader_start'   => 'date',
        'subleader_end'     => 'date',
        'reviewer_start'    => 'date',
        'reviewer_end'      => 'date',
        'chief_start'       => 'date',
        'chief_end'         => 'date',
        'subleader_notified' => 'boolean',
        'reviewer_notified'  => 'boolean',
        'chief_notified'     => 'boolean',
    ];
}
