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

    // One multi-select dropdown -> build one report (table) per selected class.
    $selected = array_filter((array) $request->input('classes', []));

    $reports = [];
    foreach ($selected as $classTitle) {
        $reports[] = (object) [
            'classTitle' => $classTitle,
            'records'    => $this->getTimetableData($classTitle),
        ];
    }

        return view('lecture_report', compact(
            'classes',
            'selected',
            'reports'
        ));
    }
    // New screen: opened by the "Generate Timetable" button with the selected classes.
    public function generate(Request $request)
    {
        $selected = array_filter((array) $request->input('classes', []));

        $reports = [];
        foreach ($selected as $classTitle) {
            $reports[] = (object) [
                'classTitle' => $classTitle,
                'records'    => $this->getTimetableData($classTitle),
            ];
        }

        return view('generate_timetable', compact('selected', 'reports'));
    }

    private function getTimetableData($classTitle)
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
                ->get();

            foreach ($subjects as $s) {
                $records[] = (object)[
                    'program' => $program,
                    'semester' => $semester,
                    'subject' => $s->SubjectName,
                    'lecture_week' => $s->lecture_week,
                ];
            }
        }

        return $records;
    }
}