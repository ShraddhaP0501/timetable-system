<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTimetableData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LectureReportController extends Controller
{
    use ResolvesTimetableData;

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
}
