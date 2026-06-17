<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Faculty Weekly Periods (step 1, before Subject Mapping).
 *
 * Set how many periods (slots) each faculty member can teach per day of the
 * week (Mon–Sat). Capacity is measured in PERIODS, not clock hours, because
 * period length varies (30–45 min) and the exact slots aren't known until the
 * timetable is generated. This is the entry screen; a "Continue" button leads
 * to the Subject Mapping screen, where assigned periods draw down from this.
 *
 * Faculty are READ from the academy's real data (opd_timetable_subject ->
 * opd_users), same source the Subject Mapping screen uses. Nothing is WRITTEN:
 * save() dd()s the payload that would be stored.
 */
class FacultyHoursController extends Controller
{
    /** Same academy the rest of the app is scoped to. */
    private const ACADEMY_ID = 149;

    /** Working week shown as columns. */
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /** Default periods pre-filled per day. */
    private const DEFAULT_PERIODS = 6;

    public function index()
    {
        // Academy faculty (UID => DisplayName), alphabetical — same derivation
        // as SubjectMappingController's faculty list.
        $rows = DB::table('opd_timetable_subject as ts')
            ->join('opd_academy_timetable as t', 't.ATID', '=', 'ts.AcademyTimetableID')
            ->leftJoin('opd_users as u', 'u.UID', '=', 'ts.FacultyID')
            ->where('t.AcademyID', self::ACADEMY_ID)
            ->where('t.IsDeleted', 0)
            ->where('ts.IsDelete', 0)
            ->whereNotNull('ts.FacultyID')
            ->distinct()
            ->get(['ts.FacultyID', 'u.DisplayName']);

        $faculty = [];
        foreach ($rows as $r) {
            $faculty[(int) $r->FacultyID] = $r->DisplayName ?: ('Faculty #' . $r->FacultyID);
        }
        asort($faculty);

        return view('faculty_hours', [
            'faculty'      => $faculty,
            'days'         => self::DAYS,
            'defaultHours' => self::DEFAULT_PERIODS,
        ]);
    }

    /**
     * Store each faculty's weekly TOTAL hours in the session and go to the
     * Subject Mapping screen, where assigned periods draw down from it.
     * (Session, not DB — keeps the demo's "nothing is written" promise while
     * still carrying the numbers between the two screens.)
     */
    public function proceed(Request $request)
    {
        $hours  = (array) $request->input('hours', []);
        $totals = [];
        foreach ($hours as $uid => $perDay) {
            $totals[(int) $uid] = array_sum(array_map('intval', (array) $perDay));
        }
        session(['faculty_hours' => $totals]);

        return redirect()->route('subject.mapping');
    }

    /**
     * "Save" the weekly hours. Does NOT touch the database — it dd()s the
     * payload that would be stored (same pattern as the other screens).
     */
    public function save(Request $request)
    {
        $hours = (array) $request->input('hours', []);

        $facultyNames = DB::table('opd_users')
            ->whereIn('UID', array_map('intval', array_keys($hours)) ?: [0])
            ->pluck('DisplayName', 'UID');

        $payload = [];
        foreach ($hours as $uid => $perDay) {
            $perDay = array_map('intval', (array) $perDay);
            $payload[] = [
                'faculty_id'  => (int) $uid,
                'faculty'     => $facultyNames[$uid] ?? ('Faculty #' . $uid),
                'per_day'     => $perDay,
                'weekly_total' => array_sum($perDay),
            ];
        }

        dd([
            'note'    => 'DEMO — not saved to the database. This is the payload that WOULD be stored.',
            'faculty' => count($payload),
            'hours'   => $payload,
        ]);
    }
}
