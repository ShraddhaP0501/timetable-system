<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LectureReportController extends Controller
{
    public function index(Request $request)
    {
    $classes = DB::table('opd_academy_timetable')
        ->select('classTitle')
        ->where('AcademyID', 1335)
        ->where('IsDeleted', 0)
        ->distinct()
        ->orderBy('classTitle')
        ->get();

        $records = [];

        if ($request->filled('class_title')) {

            $timetables = DB::table('opd_academy_timetable')
                ->where('AcademyID', 1335)
                ->where('classTitle', $request->class_title)
                ->where('IsDeleted', 0)
                ->get();

            foreach ($timetables as $tt) {

                // Class titles look like:
                //   "M.Sc IT - Integrated 2nd Semester DEF"
                //   "Computer 10th Semester (GTU) A"
                // Split on the "Nth Semester" marker:
                //   program  = everything before it   ("M.Sc IT - Integrated")
                //   semester = the marker itself       ("2nd Semester")
                if (preg_match('/^(.*?)\s+(\d+(?:st|nd|rd|th)\s+Semester)\b(.*)$/i', trim($tt->classTitle), $m)) {
                    $program  = trim($m[1]);
                    $semester = trim($m[2]);
                } else {
                    $program  = trim($tt->classTitle);
                    $semester = '';
                }

                // The timetable's slots are split across academic years
                // (e.g. an old NULL year and the current one). Use the latest
                // year that actually has slots so we report the current
                // timetable without double-counting across years.
                $latestYear = DB::table('opd_timetable_subject')
                    ->where('AcademyTimetableID', $tt->ATID)
                    ->where('IsDelete', 0)
                    ->max('AcademicYearID');

                // One row per subject, with the number of lecture slots that
                // subject has in the weekly timetable.
                $subjects = DB::table('opd_timetable_subject as ts')
                    ->leftJoin('opd_subject_master as sm', 'sm.SMID', '=', 'ts.SubjectID')
                    ->where('ts.AcademyTimetableID', $tt->ATID)
                    ->where('ts.IsDelete', 0)
                    ->when(
                        is_null($latestYear),
                        fn ($q) => $q->whereNull('ts.AcademicYearID'),
                        fn ($q) => $q->where('ts.AcademicYearID', $latestYear)
                    )
                    ->groupBy('ts.SubjectID', 'sm.SubjectName')
                    ->selectRaw('ts.SubjectID, sm.SubjectName, COUNT(*) AS lecture_week')
                    ->orderBy('sm.SubjectName')
                    ->get();

                foreach ($subjects as $s) {
                    $records[] = (object) [
                        'program'      => $program,
                        'semester'     => $semester,
                        'subject'      => $s->SubjectName ?: ('Subject #' . $s->SubjectID),
                        'lecture_week' => $s->lecture_week,
                    ];
                }
            }
        }

        return view('lecture_report', compact(
            'classes',
            'records'
        ));
    }
}