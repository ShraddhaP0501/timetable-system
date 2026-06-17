<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTimetableData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetableGeneratorController extends Controller
{
    use ResolvesTimetableData;

    // Opened by the "Generate Timetable" button with the selected classes.
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

        $maxLabsPerDay = (int) $request->input('max_labs_per_day', 2);
        if ($maxLabsPerDay < 1) {
            $maxLabsPerDay = 2;
        }

        // 30-minute lunch break shown after this period number, same for every
        // class. Lectures aren't placed in it and labs won't straddle it.
        $lunchAfter = (int) $request->input('lunch_after', 4);
        if ($lunchAfter < 1) {
            $lunchAfter = 4;
        }

        $dayLabels = array_slice(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            0,
            $days
        );

        // Real period times for the grid's left column, from opd_academy_timestamp.
        // Deduped by start time and ordered, so period index N -> the Nth slot.
        $periodTimes = DB::table('opd_academy_timestamp')
            ->where('AcademyID', 1335)
            ->where('IsDeleted', 0)
            ->where('IsBreak', 0)
            ->orderBy('StartTime')
            ->get(['StartTime', 'EndTime', 'Description'])
            ->unique('StartTime')
            ->values();

        // User-supplied counts, keyed [classTitle][subjectId][theory|lab] => count.
        $userCounts = (array) $request->input('counts', []);

        // Optional per-class overrides (only set for classes the user adjusts),
        // keyed [classTitle] => value.
        $classPeriods = (array) $request->input('class_periods', []);
        $classMaxLabs = (array) $request->input('class_maxlabs', []);

        // Shared teacher-busy map across ALL selected classes:
        //   $busy[$period][$day][$facultyId] = true
        // enforces H1 — a teacher can't be in two places in the same slot.
        $busy = [];

        // Shared teacher load per day across ALL classes:
        //   $teacherLoad[$facultyId][$day] = periods booked that day
        // used to guarantee every faculty keeps >= 1 free period each day.
        $teacherLoad = [];

        $reports = [];
        foreach ($selected as $classTitle) {
            $records = $this->getTimetableData($classTitle);

            // The class teacher of this class (from opd_faculty_master). They get
            // a "Class Teacher" slot in the first period of every day.
            $classTeacher = $this->getClassTeacher($classTitle);

            // Override each subject's theory/lab counts with the values the
            // user typed on the Lecture Report (if provided).
            foreach ($records as $r) {
                if (isset($userCounts[$classTitle][$r->subject_id]['theory'])) {
                    $r->lecture_week = max(0, (int) $userCounts[$classTitle][$r->subject_id]['theory']);
                }
                if (isset($userCounts[$classTitle][$r->subject_id]['lab'])) {
                    $r->lab_week = max(0, (int) $userCounts[$classTitle][$r->subject_id]['lab']);
                }
                // Continuous hours per lab block (default 2).
                $r->lab_hours = isset($userCounts[$classTitle][$r->subject_id]['lab_hours'])
                    ? max(1, (int) $userCounts[$classTitle][$r->subject_id]['lab_hours'])
                    : 2;
            }

            // Use this class's own override if the user set one, else the global value.
            $periods = isset($classPeriods[$classTitle]) && (int) $classPeriods[$classTitle] > 0
                ? (int) $classPeriods[$classTitle]
                : $lecturesPerDay;
            $maxLabs = isset($classMaxLabs[$classTitle]) && (int) $classMaxLabs[$classTitle] > 0
                ? (int) $classMaxLabs[$classTitle]
                : $maxLabsPerDay;

            $built = $this->buildGrid($records, $days, $periods, $busy, $maxLabs, $lunchAfter, $teacherLoad, $classTeacher);

            $reports[] = (object) [
                'classTitle' => $classTitle,
                'grid'       => $built['grid'],
                'placed'     => $built['placed'],
                'demand'     => $built['demand'],
                'periods'    => $periods,
                'maxLabs'    => $maxLabs,
                'subjects'   => $records, // for the in-grid "Change" dropdowns
                'classTeacher' => $classTeacher['name'] ?? null,
            ];
        }

        // Pivot the per-class grids into per-faculty grids. Each cell already
        // carries the faculty, and H1 guarantees a teacher is never booked in
        // two classes in the same (period, day) — so this pivot is lossless:
        //   $facultyGrids[facultyName][period][day] = ['class','subject','is_lab','lab_part']
        $facultyGrids = [];
        foreach ($reports as $report) {
            foreach ($report->grid as $p => $dayRow) {
                foreach ($dayRow as $d => $cell) {
                    if (!$cell) {
                        continue;
                    }
                    $faculty = $cell['faculty'] ?: 'Unassigned';
                    $facultyGrids[$faculty][$p][$d] = [
                        'class'    => $report->classTitle,
                        'subject'  => $cell['subject'],
                        'is_lab'   => !empty($cell['is_lab']),
                        'lab_part' => $cell['lab_part'] ?? null,
                    ];
                }
            }
        }
        ksort($facultyGrids); // alphabetical teacher order

        // Distinct class+subject pairs each teacher CAN teach (every subject they
        // hold across the selected classes, not just the slots already placed) —
        // used to populate the "Change" picker in their faculty grid.
        $facultyOptions = [];
        $addOpt = function (&$opts, $class, $subject, $isLab) {
            $key = $class . '|' . $subject;
            if (!isset($opts[$key])) {
                $opts[$key] = ['class' => $class, 'subject' => $subject, 'is_lab' => $isLab];
            }
        };

        // (a) Every subject each teacher has in each selected class.
        foreach ($reports as $report) {
            foreach ($report->subjects as $r) {
                $name = $r->faculty ?: 'Unassigned';
                $isLab = (int) ($r->lab_week ?? 0) > 0 && (int) $r->lecture_week === 0;
                $addOpt($facultyOptions[$name], $report->classTitle, $r->subject, $isLab);
            }
        }

        // (b) Make sure anyone who appears in a faculty grid (e.g. a class
        // teacher pinned to a slot) also has at least their own options.
        foreach ($facultyGrids as $facultyName => $fgrid) {
            foreach ($fgrid as $row) {
                foreach ($row as $c) {
                    $addOpt($facultyOptions[$facultyName], $c['class'], $c['subject'], $c['is_lab']);
                }
            }
        }

        foreach ($facultyOptions as $name => $opts) {
            $facultyOptions[$name] = array_values($opts);
        }

        return view('generate_timetable', compact(
            'selected',
            'reports',
            'days',
            'lecturesPerDay',
            'dayLabels',
            'userCounts',
            'maxLabsPerDay',
            'lunchAfter',
            'periodTimes',
            'facultyGrids',
            'facultyOptions'
        ));
    }

    /**
     * Save a single class's timetable. For now it does NOT write to the
     * database — it just dumps the data that WOULD be stored, so we can verify
     * the structure first.
     */
    public function save(Request $request)
    {
        $lectures = json_decode($request->input('timetable'), true) ?? [];

        dd([
            'class_title'   => $request->input('class_title'),
            'faculty_name'  => $request->input('faculty_name'),
            'total_entries' => count($lectures),
            'lectures'      => $lectures,
        ]);
    }

    /**
     * Place each subject's weekly lectures into a (periods x days) grid.
     *
     * Theory lectures take 1 period; lab sessions take 2 consecutive periods.
     * Placement enforces:
     *   H2 - the class slot(s) must be free, and
     *   H1 - the teacher must be free in that (period, day) in ANY class
     *        (the shared $busy map is passed by reference and updated).
     *
     * Returns grid[period][day] => ['subject' => ..., 'faculty' => ..., 'is_lab' => bool] | null
     */
    private function buildGrid($records, int $days, int $periods, array &$busy, int $maxLabsPerDay = 2, int $lunchAfter = 0, array &$teacherLoad = [], $classTeacher = null)
    {
        // A teacher may be booked at most this many periods per day, so at
        // least one period stays free for them.
        $maxTeacherPerDay = max(1, $periods - 1);

        // Empty grid.
        $grid = [];
        for ($p = 0; $p < $periods; $p++) {
            $grid[$p] = array_fill(0, $days, null);
        }

        $placed = 0;

        // --- Pin the class teacher to the FIRST period of every day ---
        // The class teacher has no subject of their own in the data, so they are
        // given ONE subject of this class (prefer one with theory lectures). This
        // runs before everything else so it always wins period 0. The shared
        // $busy map keeps H1 intact across classes.
        $ctPrePlaced = 0;
        $ctName = $classTeacher['name'] ?? null;
        $ctUid  = $classTeacher['uid'] ?? null;

        if ($ctName) {
            // Choose one subject of this class for the class teacher.
            $ctSubject = null;
            foreach ($records as $r) {
                if ($ctSubject === null) {
                    $ctSubject = $r;
                }
                if ((int) $r->lecture_week > 0) {
                    $ctSubject = $r; // prefer a subject that has theory lectures
                    break;
                }
            }

            if ($ctSubject) {
                for ($d = 0; $d < $days; $d++) {
                    // Class slot must be free and the teacher free in that slot (H1/H2).
                    if ($grid[0][$d] !== null) {
                        continue;
                    }
                    if ($ctUid && isset($busy[0][$d][$ctUid])) {
                        continue;
                    }

                    $grid[0][$d] = [
                        'subject'          => $ctSubject->subject,
                        'faculty'          => $ctName,
                        'academy_id'       => $ctSubject->academy_id ?? null,
                        'academic_year_id' => $ctSubject->academic_year_id ?? null,
                        'is_lab'           => false,
                        'class_teacher'    => true,
                    ];
                    if ($ctUid) {
                        $busy[0][$d][$ctUid]     = true;
                        $teacherLoad[$ctUid][$d] = ($teacherLoad[$ctUid][$d] ?? 0) + 1;
                    }
                    $placed++;
                    $ctPrePlaced++;
                }

                // Don't re-place these lectures in the normal theory fill: reduce
                // the chosen subject's remaining theory count by what we pinned.
                $ctSubject->lecture_week = max(0, (int) $ctSubject->lecture_week - $ctPrePlaced);
            }
        }

        // Build the theory unit list (round-robin across subjects for spread).
        $theory = $this->interleaveUnits($records, false); // 1 period each

        // Build lab blocks. A lab subject runs `lab_week` sessions per week, and
        // each session is a continuous block of `lab_hours` periods (default 2).
        $labs     = [];
        $labHours = 0;
        foreach ($records as $r) {
            $count = (int) ($r->lab_week ?? 0);
            $hours = max(1, (int) ($r->lab_hours ?? 2));
            for ($i = 0; $i < $count; $i++) {
                $labs[] = [
                    'subject'          => $r->subject,
                    'faculty'          => $r->faculty,
                    'faculty_id'       => $r->faculty_id,
                    'academy_id'       => $r->academy_id ?? null,
                    'academic_year_id' => $r->academic_year_id ?? null,
                    'hours'            => $hours,
                ];
                $labHours += $hours;
            }
        }

        // Theory takes 1 period each; each lab block takes its own hours. Pinned
        // first-periods count too.
        $demand = $ctPrePlaced + count($theory) + $labHours;

        // At most $maxLabsPerDay lab sessions per day for a class.
        $labsOnDay = array_fill(0, $days, 0);

        // --- Place labs first (harder: need several consecutive free periods) ---
        foreach ($labs as $unit) {
            $fid   = $unit['faculty_id'];
            $hours = $unit['hours'];
            $done  = false;
            for ($d = 0; $d < $days && !$done; $d++) {
                // Cap labs per day.
                if ($labsOnDay[$d] >= $maxLabsPerDay) {
                    continue;
                }
                // Keep >= 1 free period for the teacher (the block uses $hours periods).
                if ($fid && (($teacherLoad[$fid][$d] ?? 0) + $hours) > $maxTeacherPerDay) {
                    continue;
                }
                for ($p = 0; $p <= $periods - $hours && !$done; $p++) {
                    // The continuous block must not straddle the lunch break,
                    // which sits between period ($lunchAfter - 1) and $lunchAfter.
                    if ($lunchAfter > 0 && $p < $lunchAfter && ($p + $hours - 1) >= $lunchAfter) {
                        continue;
                    }
                    // Every period of the block must be free for the class (H2)
                    // and the teacher free in all of them (H1).
                    $fits = true;
                    for ($k = 0; $k < $hours; $k++) {
                        if ($grid[$p + $k][$d] !== null) {
                            $fits = false;
                            break;
                        }
                        if ($fid && isset($busy[$p + $k][$d][$fid])) {
                            $fits = false;
                            break;
                        }
                    }
                    if (!$fits) {
                        continue;
                    }

                    // First cell spans the whole block; the rest are skipped in the view.
                    for ($k = 0; $k < $hours; $k++) {
                        $grid[$p + $k][$d] = [
                            'subject'          => $unit['subject'],
                            'faculty'          => $unit['faculty'],
                            'academy_id'       => $unit['academy_id'],
                            'academic_year_id' => $unit['academic_year_id'],
                            'is_lab'           => true,
                            'lab_part'         => $k === 0 ? 'top' : 'bottom',
                            'lab_span'         => $hours,
                            'lab_hours'        => $hours,
                        ];
                        if ($fid) {
                            $busy[$p + $k][$d][$fid] = true;
                        }
                    }
                    if ($fid) {
                        $teacherLoad[$fid][$d] = ($teacherLoad[$fid][$d] ?? 0) + $hours;
                    }
                    $labsOnDay[$d]++;
                    $placed += $hours;
                    $done = true;
                }
            }
        }

        // --- Place theory lectures (single period) ---
        foreach ($theory as $unit) {
            $fid  = $unit['faculty_id'];
            $done = false;
            for ($p = 0; $p < $periods && !$done; $p++) {
                for ($d = 0; $d < $days && !$done; $d++) {
                    if ($grid[$p][$d] !== null) {
                        continue; // H2
                    }
                    if ($fid && isset($busy[$p][$d][$fid])) {
                        continue; // H1
                    }
                    // Keep >= 1 free period for the teacher that day.
                    if ($fid && (($teacherLoad[$fid][$d] ?? 0) + 1) > $maxTeacherPerDay) {
                        continue;
                    }

                    $grid[$p][$d] = [
                        'subject'          => $unit['subject'],
                        'faculty'          => $unit['faculty'],
                        'academy_id'       => $unit['academy_id'],
                        'academic_year_id' => $unit['academic_year_id'],
                        'is_lab'           => false,
                    ];
                    if ($fid) {
                        $busy[$p][$d][$fid]    = true;
                        $teacherLoad[$fid][$d] = ($teacherLoad[$fid][$d] ?? 0) + 1;
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

    /** Round-robin interleave of one type (theory or lab) across subjects. */
    private function interleaveUnits($records, bool $lab)
    {
        $queues = [];
        foreach ($records as $r) {
            $n = $lab ? (int) ($r->lab_week ?? 0) : (int) $r->lecture_week;
            if ($n > 0) {
                $queues[] = [
                    'subject'          => $r->subject,
                    'faculty'          => $r->faculty,
                    'faculty_id'       => $r->faculty_id,
                    'academy_id'       => $r->academy_id ?? null,
                    'academic_year_id' => $r->academic_year_id ?? null,
                    'remaining'        => $n,
                ];
            }
        }

        $units  = [];
        $active = true;
        while ($active) {
            $active = false;
            foreach ($queues as &$q) {
                if ($q['remaining'] > 0) {
                    $units[] = [
                        'subject'          => $q['subject'],
                        'faculty'          => $q['faculty'],
                        'faculty_id'       => $q['faculty_id'],
                        'academy_id'       => $q['academy_id'],
                        'academic_year_id' => $q['academic_year_id'],
                    ];
                    $q['remaining']--;
                    $active = true;
                }
            }
            unset($q);
        }

        return $units;
    }
}
