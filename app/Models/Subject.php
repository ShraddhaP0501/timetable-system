<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'opd_academy_subject';

    protected $primaryKey = 'ASID';

    public $timestamps = false;

    protected $fillable = [
        'BatchID',
        'StandardID',
        'MediumID',
        'DivisionID',
        'SubjectID',
        'AcademyID',
        'GradeType',
        'SubjectCategory',
        'SubjectType',
        'IsActive',
        'IsElectiveSubject',
        'IsDeleted',
        'IsLocked',
        'CreatedBy',
        'CreatedOn',
        'AcademyYearID',
        'switchedBy',
        'switchedOn'
    ];

    protected $casts = [
        'ASID' => 'integer',
        'BatchID' => 'integer',
        'StandardID' => 'integer',
        'MediumID' => 'integer',
        'DivisionID' => 'integer',
        'SubjectID' => 'integer',
        'AcademyID' => 'integer',
        'IsActive' => 'boolean',
        'IsElectiveSubject' => 'boolean',
        'IsDeleted' => 'boolean',
        'IsLocked' => 'boolean',
        'CreatedBy' => 'integer',
        'AcademyYearID' => 'integer',
        'switchedBy' => 'integer',
        'CreatedOn' => 'datetime',
        'switchedOn' => 'datetime',
    ];
}