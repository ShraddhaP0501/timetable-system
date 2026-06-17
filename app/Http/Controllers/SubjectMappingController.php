<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Subject Mapping + Faculty Assignment.
 *
 * One screen does the whole job with minimum clicks:
 *   1. Pick a Standard chip and a Section (division) tab.
 *   2. A single matrix shows every subject mapped to that class. Set
 *      periods/week and pick the faculty inline.
 *   3. The faculty dropdown is pre-filtered to teachers QUALIFIED for that
 *      subject, so the list stays short and you can't pick wrong.
 *
 * All data is READ from the existing database (same tables/keys the rest of
 * the app uses) — nothing is hard-coded:
 *   opd_academy_standard          -> the standard/semester chips
 *   opd_academy_division          -> the section tabs
 *   opd_academy_subject (+ master)-> subjects mapped to a standard+division
 *   opd_subject_faculty_assignment-> qualified faculty per subject
 *
 * Nothing is WRITTEN: save() just dd()s the payload that would be stored,
 * matching TimetableGeneratorController::save().
 */
class SubjectMappingController extends Controller
{
    /** Same academy the rest of the app is scoped to. */
    private const ACADEMY_ID = 149;
    public function index(Request $request)
    {
        // ---- Standard chips & Section tabs (from DB) ----
        // StandardOrder is all 0 for this academy, so it can't drive the order.
        // Sort by the number in the title (Standard 1..12) ascending; titles
        // without a number (Nursery, Sr. KG, TERF…) fall to the end.
        $standards = DB::table('opd_academy_standard')
            ->where('AcademyID', self::ACADEMY_ID)
            ->where('IsDeleted', 0)
            ->get(['ASID', 'Title'])
            ->sortBy(function ($s) {
                return preg_match('/\d+/', $s->Title, $m)
                    ? sprintf('%03d-%s', (int) $m[0], $s->Title)
                    : 'zzz-' . $s->Title;
            })
            ->values();

        $sections = DB::table('opd_academy_division')
            ->where('AcademyID', self::ACADEMY_ID)
            ->where('IsDeleted', 0)
            ->orderBy('Title')
            ->get(['ADID', 'Title']);

        // ---- Medium chips (from DB) ----
        $mediums = DB::table('opd_academy_medium')
            ->where('AcademyID', self::ACADEMY_ID)
            ->where('IsDeleted', 0)
            ->orderBy('Title')
            ->get(['AMID', 'Title']);

        // Current selection (default to the first standard/division/medium available).
        $standardId = (int) $request->input('standard', optional($standards->first())->ASID);
        $divisionId = (int) $request->input('section',  optional($sections->first())->ADID);
        $mediumId   = (int) $request->input('medium',   optional($mediums->first())->AMID);

        // ---- Subjects mapped to this standard + division + medium (from DB) ----
        $subjectRows = DB::table('opd_academy_subject as a')
            ->leftJoin('opd_subject_master as m', 'm.SMID', '=', 'a.SubjectID')
            ->where('a.AcademyID', self::ACADEMY_ID)
            ->where('a.StandardID', $standardId)
            ->where('a.DivisionID', $divisionId)
            ->where('a.MediumID', $mediumId)
            ->where('a.IsDeleted', 0)
            ->orderBy('m.SubjectName')
            ->get([
                'a.SubjectID',
                'm.SubjectName',
                'm.SubjectCategory',
                'm.IsElective',
                'm.isPractical',
            ]);

        // ---- Qualified faculty per subject (from DB) ----
        // FacultyId/FacultyID resolves to opd_users.UID -> DisplayName, the same
        // mapping ResolvesTimetableData uses. Two sources are merged so this
        // works whichever the academy populates:
        //   (a) opd_subject_faculty_assignment — explicit subject->faculty list
        //   (b) opd_timetable_subject (via opd_academy_timetable) — faculty who
        //       already teach the subject in this standard+division's timetable.
        // SubjectId => [ UID => ['id' => UID, 'name' => DisplayName] ]
        $qualifiedBySubject = [];

        $assignments = DB::table('opd_subject_faculty_assignment as fa')
            ->leftJoin('opd_users as u', 'u.UID', '=', 'fa.FacultyId')
            ->where('fa.AcademyID', self::ACADEMY_ID)
            ->where('fa.SemesterId', $standardId)
            ->where('fa.isDeleted', 0)
            ->get(['fa.SubjectId', 'fa.FacultyId', 'u.DisplayName']);
        foreach ($assignments as $a) {
            if ($a->FacultyId === null) {
                continue;
            }
            $qualifiedBySubject[$a->SubjectId][$a->FacultyId] = [
                'id'   => (int) $a->FacultyId,
                'name' => $a->DisplayName ?: ('Faculty #' . $a->FacultyId),
            ];
        }

        $taught = DB::table('opd_timetable_subject as ts')
            ->join('opd_academy_timetable as t', 't.ATID', '=', 'ts.AcademyTimetableID')
            ->leftJoin('opd_users as u', 'u.UID', '=', 'ts.FacultyID')
            ->where('t.AcademyID', self::ACADEMY_ID)
            ->where('t.StandardID', $standardId)
            ->where('t.DivisionID', $divisionId)
            ->where('t.MediumID', $mediumId)
            ->where('t.IsDeleted', 0)
            ->where('ts.IsDelete', 0)
            ->whereNotNull('ts.FacultyID')
            ->distinct()
            ->get(['ts.SubjectID', 'ts.FacultyID', 'u.DisplayName']);
        foreach ($taught as $a) {
            $qualifiedBySubject[$a->SubjectID][$a->FacultyID] = [
                'id'   => (int) $a->FacultyID,
                'name' => $a->DisplayName ?: ('Faculty #' . $a->FacultyID),
            ];
        }

        // ---- Full faculty list for the academy (the dropdown the user picks
        //      from). Built from the academy's real faculty so every UID is
        //      valid; merged from both faculty sources. UID => DisplayName. ----
        $allFaculty = [];
        foreach ($taught as $a) {
            $allFaculty[(int) $a->FacultyID] = $a->DisplayName ?: ('Faculty #' . $a->FacultyID);
        }
        foreach ($assignments as $a) {
            if ($a->FacultyId !== null) {
                $allFaculty[(int) $a->FacultyId] = $a->DisplayName ?: ('Faculty #' . $a->FacultyId);
            }
        }
        // Pull in everyone who teaches anywhere in this academy (any standard),
        // so the user can assign a faculty even to a subject with no prior link.
        $academyFaculty = DB::table('opd_timetable_subject as ts')
            ->join('opd_academy_timetable as t', 't.ATID', '=', 'ts.AcademyTimetableID')
            ->leftJoin('opd_users as u', 'u.UID', '=', 'ts.FacultyID')
            ->where('t.AcademyID', self::ACADEMY_ID)
            ->where('t.IsDeleted', 0)
            ->where('ts.IsDelete', 0)
            ->whereNotNull('ts.FacultyID')
            ->distinct()
            ->pluck('u.DisplayName', 'ts.FacultyID');
        foreach ($academyFaculty as $uid => $name) {
            $allFaculty[(int) $uid] = $name ?: ('Faculty #' . $uid);
        }
        asort($allFaculty); // alphabetical by name

        // ---- Build one matrix row per subject ----
        $matrix = [];
        foreach ($subjectRows as $sub) {
            $isPractical = (int) $sub->isPractical === 1 || $sub->isPractical === 'Yes';
            $isElective  = (int) $sub->IsElective === 1;

            $category = $isElective
                ? 'Elective'
                : ($sub->SubjectCategory ?: 'General');

            $qualified = array_values($qualifiedBySubject[$sub->SubjectID] ?? []);

            $matrix[] = [
                'subject_id' => (int) $sub->SubjectID,
                'name'       => $sub->SubjectName ?: ('Subject #' . $sub->SubjectID),
                'category'   => $category,
                'practical'  => $isPractical,
                'mapped'     => true,                 // it IS mapped (it's in opd_academy_subject)
                'periods'    => $isPractical ? 2 : 4, // UI default; no period column in DB
                'faculty_id' => $qualified[0]['id'] ?? null,
                'qualified'  => $qualified,
            ];
        }

        // ---- Live per-teacher workload for THIS class ----
        $workload = [];
        foreach ($matrix as $row) {
            if (!$row['mapped'] || $row['faculty_id'] === null) {
                continue;
            }
            $fid = $row['faculty_id'];
            if (!isset($workload[$fid])) {
                $name = 'Faculty #' . $fid;
                foreach ($row['qualified'] as $q) {
                    if ($q['id'] === $fid) {
                        $name = $q['name'];
                        break;
                    }
                }
                $workload[$fid] = ['name' => $name, 'load' => 0];
            }
            $workload[$fid]['load'] += $row['periods'];
        }
        // Bars are scaled to the busiest teacher (no hard-coded max).
        $maxLoad = 0;
        foreach ($workload as $w) {
            $maxLoad = max($maxLoad, $w['load']);
        }

        return view('subject_mapping', compact(
            'standards',
            'sections',
            'mediums',
            'standardId',
            'divisionId',
            'mediumId',
            'matrix',
            'allFaculty',
            'workload',
            'maxLoad'
        ));
    }

    /**
     * "Save" the mapping. Does NOT touch the database — it dd()s the exact
     * payload that would be persisted, so the structure can be verified first
     * (same pattern as TimetableGeneratorController::save).
     */
    public function save(Request $request)
    {
        $rows       = (array) $request->input('rows', []);
        $standardId = (int) $request->input('standard');
        $divisionId = (int) $request->input('section');
        $mediumId   = (int) $request->input('medium');

        // Resolve names from the DB for a readable dump.
        $subjectNames = DB::table('opd_subject_master')
            ->whereIn('SMID', array_map('intval', array_keys($rows)) ?: [0])
            ->pluck('SubjectName', 'SMID');

        // Each subject can now have MANY faculty: rows[subjectId][faculty_id][] = [UID, ...]
        $allFacultyIds = [];
        foreach ($rows as $row) {
            foreach ((array) ($row['faculty_id'] ?? []) as $fid) {
                $allFacultyIds[] = (int) $fid;
            }
        }
        $facultyNames = DB::table('opd_users')
            ->whereIn('UID', $allFacultyIds ?: [0])
            ->pluck('DisplayName', 'UID');

        $standardTitle = DB::table('opd_academy_standard')->where('ASID', $standardId)->value('Title');
        $divisionTitle = DB::table('opd_academy_division')->where('ADID', $divisionId)->value('Title');
        $mediumTitle   = DB::table('opd_academy_medium')->where('AMID', $mediumId)->value('Title');

        $mapping = [];
        foreach ($rows as $subjectId => $row) {
            if (empty($row['mapped'])) {
                continue; // only ticked subjects are "taught in this class"
            }
            $fids   = array_values(array_filter(array_map('intval', (array) ($row['faculty_id'] ?? []))));
            $fNames = array_map(fn ($fid) => $facultyNames[$fid] ?? ('Faculty #' . $fid), $fids);

            $mapping[] = [
                'subject_id'       => (int) $subjectId,
                'subject'          => $subjectNames[$subjectId] ?? ('Subject #' . $subjectId),
                'periods_per_week' => (int) ($row['periods'] ?? 0),
                'faculty_ids'      => $fids,
                'faculty'          => $fNames ? implode(', ', $fNames) : 'Unassigned',
            ];
        }

        dd([
            'note'     => 'DEMO — not saved to the database. This is the payload that WOULD be stored.',
            'standard' => $standardTitle . ' (ID ' . $standardId . ')',
            'section'  => $divisionTitle . ' (ID ' . $divisionId . ')',
            'medium'   => $mediumTitle . ' (ID ' . $mediumId . ')',
            'subjects' => count($mapping),
            'mapping'  => $mapping,
        ]);
    }
}
