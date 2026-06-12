<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Shared loader used by both the Lecture Report and the Timetable Generator.
 * Returns one record per subject for a class, with the primary teacher and the
 * theory/lab weekly counts.
 */
trait ResolvesTimetableData
{
    /**
     * Resolve the class teacher for a class. The flag lives on
     * opd_faculty_master.IsClassTeacher, matched to the class by
     * AcademyID + StandardID + DivisionID. That table has no UID, so we map to
     * opd_users by email to get the faculty UID used elsewhere.
     *
     * Only an exact match with IsClassTeacher = 1 produces a class teacher;
     * otherwise returns ['name' => null, 'uid' => null].
     */
    protected function getClassTeacher($classTitle)
    {
        $class = DB::table('opd_academy_timetable')
            ->where('AcademyID', 1335)
            ->where('IsDeleted', 0)
            ->where('classTitle', $classTitle)
            ->first(['StandardID', 'DivisionID']);

        if (!$class) {
            return ['name' => null, 'uid' => null];
        }

        $fm = DB::table('opd_faculty_master')
            ->where('AcademyID', 1335)
            ->where('StandardID', $class->StandardID)
            ->where('DivisionID', $class->DivisionID)
            ->where('IsClassTeacher', 1)
            ->where('IsDeleted', 0)
            ->first(['FirstName', 'MiddleName', 'LastName', 'EmailAddress']);

        if (!$fm) {
            return ['name' => null, 'uid' => null];
        }

        $name = trim(preg_replace('/\s+/', ' ', $fm->FirstName . ' ' . $fm->MiddleName . ' ' . $fm->LastName));

        $uid = null;
        if ($fm->EmailAddress) {
            $uid = DB::table('opd_users')
                ->where('email', $fm->EmailAddress)
                ->orWhere('EmailID', $fm->EmailAddress)
                ->value('UID');
        }

        return ['name' => $name ?: null, 'uid' => $uid];
    }

    protected function getTimetableData($classTitle)
    {
        $records = [];

        $timetables = DB::table('opd_academy_timetable')
            ->where('AcademyID', 1335)
            ->where('classTitle', $classTitle)
            ->where('IsDeleted', 0)
            ->get();

        foreach ($timetables as $tt) {

            if (preg_match('/^(.*?)\s+(\d+(?:st|nd|rd|th)\s+Semester)\b(.*)$/i', trim($tt->classTitle), $m)) {
                $program  = trim($m[1]);
                $semester = trim($m[2]);
            } else {
                $program  = trim($tt->classTitle);
                $semester = '';
            }

            $latestYear = DB::table('opd_timetable_subject')
                ->where('AcademyTimetableID', $tt->ATID)
                ->where('IsDelete', 0)
                ->max('AcademicYearID');

            // Count per (subject, faculty) so we can total the lectures AND
            // pick each subject's primary teacher (the one who teaches it most).
            $rows = DB::table('opd_timetable_subject as ts')
                ->leftJoin('opd_subject_master as sm', 'sm.SMID', '=', 'ts.SubjectID')
                ->leftJoin('opd_users as u', 'u.UID', '=', 'ts.FacultyID')
                ->where('ts.AcademyTimetableID', $tt->ATID)
                ->where('ts.IsDelete', 0)
                ->when(
                    is_null($latestYear),
                    fn ($q) => $q->whereNull('ts.AcademicYearID'),
                    fn ($q) => $q->where('ts.AcademicYearID', $latestYear)
                )
                ->groupBy('ts.SubjectID', 'sm.SubjectName', 'ts.FacultyID', 'u.DisplayName', 'ts.Is_practical')
                ->selectRaw('ts.SubjectID, sm.SubjectName, ts.FacultyID AS faculty_id, u.DisplayName AS faculty_name, ts.Is_practical AS is_practical, COUNT(*) AS c')
                ->get();

            $bySubject = [];
            foreach ($rows as $r) {
                $sid = $r->SubjectID;
                if (!isset($bySubject[$sid])) {
                    $bySubject[$sid] = [
                        'name'      => $r->SubjectName,
                        'lecture'   => 0, // theory slots  (Is_practical = No)
                        'lab'       => 0, // practical slots (Is_practical = Yes)
                        'facId'     => $r->faculty_id,
                        'facName'   => $r->faculty_name,
                        'bestCount' => -1,
                    ];
                }

                if ($r->is_practical === 'Yes') {
                    $bySubject[$sid]['lab'] += $r->c;
                } else {
                    $bySubject[$sid]['lecture'] += $r->c;
                }

                if ($r->c > $bySubject[$sid]['bestCount']) {
                    $bySubject[$sid]['bestCount'] = $r->c;
                    $bySubject[$sid]['facId']     = $r->faculty_id;
                    $bySubject[$sid]['facName']   = $r->faculty_name;
                }
            }

            foreach ($bySubject as $sid => $info) {
                $records[] = (object)[
                    'academy_id'       => 1335,
                    'academic_year_id' => $latestYear,
                    'program'      => $program,
                    'semester'     => $semester,
                    'subject_id'   => $sid,
                    'subject'      => $info['name'] ?: ('Subject #' . $sid),
                    'faculty'      => $info['facName'] ?: 'Unassigned',
                    'faculty_id'   => $info['facId'],
                    'lecture_week' => $info['lecture'],
                    'lab_week'     => $info['lab'],
                ];
            }
        }

        return $records;
    }
}
