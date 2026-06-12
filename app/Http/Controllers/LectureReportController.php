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

        // Days are fixed at 6 (Mon–Sat); lectures/day defaults to 8 but the
        // user can change it.
        $days           = 6;
        $lecturesPerDay = (int) $request->input('lectures_per_day', 8);
        if ($lecturesPerDay < 1) {
            $lecturesPerDay = 8;
        }

        $dayLabels = array_slice(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            0,
            $days
        );

        // User-supplied lectures/week, keyed [classTitle][subjectId] => count.
        $userLectures = (array) $request->input('lectures', []);

        // Shared teacher-busy map across ALL selected classes:
        //   $busy[$period][$day][$facultyId] = true
        // enforces H1 — a teacher can't be in two places in the same slot.
        $busy = [];

        $reports = [];
        foreach ($selected as $classTitle) {
            $records = $this->getTimetableData($classTitle);

            // Override each subject's weekly count with the value the user typed
            // on the Lecture Report (if provided).
            foreach ($records as $r) {
                if (isset($userLectures[$classTitle][$r->subject_id])) {
                    $r->lecture_week = max(0, (int) $userLectures[$classTitle][$r->subject_id]);
                }
            }

            $built   = $this->buildGrid($records, $days, $lecturesPerDay, $busy);

            $reports[] = (object) [
                'classTitle' => $classTitle,
                'grid'       => $built['grid'],
                'placed'     => $built['placed'],
                'demand'     => $built['demand'],
                'subjects'   => $records, // for the in-grid "Change" dropdowns
            ];
        }

        return view('generate_timetable', compact(
            'selected',
            'reports',
            'days',
            'lecturesPerDay',
            'dayLabels',
            'userLectures'
        ));
    }

    /**
     * Place each subject's weekly lectures into a (periods x days) grid.
     *
     * Lectures are interleaved round-robin across subjects so each subject is
     * spread across the week. Each lecture is assigned to the subject's teacher
     * and placed only where:
     *   H2 - the class slot is free, and
     *   H1 - that teacher is not already busy in that (period, day) in ANY class
     *        (the shared $busy map is passed by reference and updated).
     *
     * Returns grid[period][day] => ['subject' => ..., 'faculty' => ...] | null
     */
    private function buildGrid($records, int $days, int $periods, array &$busy)
    {
        // Round-robin queue: one lecture per subject per pass.
        $queues = [];
        foreach ($records as $r) {
            $queues[] = [
                'subject'    => $r->subject,
                'faculty'    => $r->faculty,
                'faculty_id' => $r->faculty_id,
                'remaining'  => (int) $r->lecture_week,
            ];
        }

        $units  = [];
        $active = true;
        while ($active) {
            $active = false;
            foreach ($queues as &$q) {
                if ($q['remaining'] > 0) {
                    $units[] = [
                        'subject'    => $q['subject'],
                        'faculty'    => $q['faculty'],
                        'faculty_id' => $q['faculty_id'],
                    ];
                    $q['remaining']--;
                    $active = true;
                }
            }
            unset($q);
        }

        $demand = count($units);

        // Empty grid.
        $grid = [];
        for ($p = 0; $p < $periods; $p++) {
            $grid[$p] = array_fill(0, $days, null);
        }

        // Place each lecture in the first free slot where its teacher is also free.
        $placed = 0;
        foreach ($units as $unit) {
            $fid = $unit['faculty_id'];

            $done = false;
            for ($p = 0; $p < $periods && !$done; $p++) {
                for ($d = 0; $d < $days && !$done; $d++) {
                    if ($grid[$p][$d] !== null) {
                        continue; // H2: slot already used by this class
                    }
                    if ($fid && isset($busy[$p][$d][$fid])) {
                        continue; // H1: teacher already busy in this slot
                    }

                    $grid[$p][$d] = ['subject' => $unit['subject'], 'faculty' => $unit['faculty']];
                    if ($fid) {
                        $busy[$p][$d][$fid] = true;
                    }
                    $placed++;
                    $done = true;
                }
            }
        }

        return [
            'grid'   => $grid,
            'placed' => $placed,
            'demand' => $demand,
        ];
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
                ->groupBy('ts.SubjectID', 'sm.SubjectName', 'ts.FacultyID', 'u.DisplayName')
                ->selectRaw('ts.SubjectID, sm.SubjectName, ts.FacultyID AS faculty_id, u.DisplayName AS faculty_name, COUNT(*) AS c')
                ->get();

            $bySubject = [];
            foreach ($rows as $r) {
                $sid = $r->SubjectID;
                if (!isset($bySubject[$sid])) {
                    $bySubject[$sid] = [
                        'name'      => $r->SubjectName,
                        'total'     => 0,
                        'facId'     => $r->faculty_id,
                        'facName'   => $r->faculty_name,
                        'bestCount' => -1,
                    ];
                }
                $bySubject[$sid]['total'] += $r->c;
                if ($r->c > $bySubject[$sid]['bestCount']) {
                    $bySubject[$sid]['bestCount'] = $r->c;
                    $bySubject[$sid]['facId']     = $r->faculty_id;
                    $bySubject[$sid]['facName']   = $r->faculty_name;
                }
            }

            foreach ($bySubject as $sid => $info) {
                $records[] = (object)[
                    'program'      => $program,
                    'semester'     => $semester,
                    'subject_id'   => $sid,
                    'subject'      => $info['name'] ?: ('Subject #' . $sid),
                    'faculty'      => $info['facName'] ?: 'Unassigned',
                    'faculty_id'   => $info['facId'],
                    'lecture_week' => $info['total'],
                ];
            }
        }

        return $records;
    }
}