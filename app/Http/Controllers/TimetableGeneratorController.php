<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTimetableData;
use Illuminate\Http\Request;

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

        $dayLabels = array_slice(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            0,
            $days
        );

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

        $reports = [];
        foreach ($selected as $classTitle) {
            $records = $this->getTimetableData($classTitle);

            // Override each subject's theory/lab counts with the values the
            // user typed on the Lecture Report (if provided).
            foreach ($records as $r) {
                if (isset($userCounts[$classTitle][$r->subject_id]['theory'])) {
                    $r->lecture_week = max(0, (int) $userCounts[$classTitle][$r->subject_id]['theory']);
                }
                if (isset($userCounts[$classTitle][$r->subject_id]['lab'])) {
                    $r->lab_week = max(0, (int) $userCounts[$classTitle][$r->subject_id]['lab']);
                }
            }

            // Use this class's own override if the user set one, else the global value.
            $periods = isset($classPeriods[$classTitle]) && (int) $classPeriods[$classTitle] > 0
                ? (int) $classPeriods[$classTitle]
                : $lecturesPerDay;
            $maxLabs = isset($classMaxLabs[$classTitle]) && (int) $classMaxLabs[$classTitle] > 0
                ? (int) $classMaxLabs[$classTitle]
                : $maxLabsPerDay;

            $built = $this->buildGrid($records, $days, $periods, $busy, $maxLabs);

            $reports[] = (object) [
                'classTitle' => $classTitle,
                'grid'       => $built['grid'],
                'placed'     => $built['placed'],
                'demand'     => $built['demand'],
                'periods'    => $periods,
                'maxLabs'    => $maxLabs,
                'subjects'   => $records, // for the in-grid "Change" dropdowns
            ];
        }

        return view('generate_timetable', compact(
            'selected',
            'reports',
            'days',
            'lecturesPerDay',
            'dayLabels',
            'userCounts',
            'maxLabsPerDay'
        ));
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
    private function buildGrid($records, int $days, int $periods, array &$busy, int $maxLabsPerDay = 2)
    {
        // Empty grid.
        $grid = [];
        for ($p = 0; $p < $periods; $p++) {
            $grid[$p] = array_fill(0, $days, null);
        }

        // Build interleaved unit lists (round-robin across subjects for spread).
        $theory = $this->interleaveUnits($records, false); // 1 period each
        $labs   = $this->interleaveUnits($records, true);   // 2 consecutive periods each

        // Each lab takes 2 periods; theory takes 1.
        $demand = count($theory) + (count($labs) * 2);
        $placed = 0;

        // At most $maxLabsPerDay lab sessions per day for a class.
        $labsOnDay = array_fill(0, $days, 0);

        // --- Place labs first (harder: need two consecutive free periods) ---
        foreach ($labs as $unit) {
            $fid  = $unit['faculty_id'];
            $done = false;
            for ($d = 0; $d < $days && !$done; $d++) {
                // Cap labs per day.
                if ($labsOnDay[$d] >= $maxLabsPerDay) {
                    continue;
                }
                for ($p = 0; $p < $periods - 1 && !$done; $p++) {
                    // Both periods of the block must be free (H2)...
                    if ($grid[$p][$d] !== null || $grid[$p + 1][$d] !== null) {
                        continue;
                    }
                    // ...and the teacher free in BOTH (H1).
                    if ($fid && (isset($busy[$p][$d][$fid]) || isset($busy[$p + 1][$d][$fid]))) {
                        continue;
                    }

                    // Top half spans both rows; bottom half is skipped in the view.
                    $grid[$p][$d] = [
                        'subject'  => $unit['subject'],
                        'faculty'  => $unit['faculty'],
                        'is_lab'   => true,
                        'lab_part' => 'top',
                    ];
                    $grid[$p + 1][$d] = [
                        'subject'  => $unit['subject'],
                        'faculty'  => $unit['faculty'],
                        'is_lab'   => true,
                        'lab_part' => 'bottom',
                    ];
                    if ($fid) {
                        $busy[$p][$d][$fid]     = true;
                        $busy[$p + 1][$d][$fid] = true;
                    }
                    $labsOnDay[$d]++;
                    $placed += 2;
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

                    $grid[$p][$d] = ['subject' => $unit['subject'], 'faculty' => $unit['faculty'], 'is_lab' => false];
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

    /** Round-robin interleave of one type (theory or lab) across subjects. */
    private function interleaveUnits($records, bool $lab)
    {
        $queues = [];
        foreach ($records as $r) {
            $n = $lab ? (int) ($r->lab_week ?? 0) : (int) $r->lecture_week;
            if ($n > 0) {
                $queues[] = [
                    'subject'    => $r->subject,
                    'faculty'    => $r->faculty,
                    'faculty_id' => $r->faculty_id,
                    'remaining'  => $n,
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

        return $units;
    }
}
