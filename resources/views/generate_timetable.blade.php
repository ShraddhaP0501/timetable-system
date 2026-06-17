<!DOCTYPE html>
<html>
<head>
    <title>Generate Timetable</title>
    <link rel="stylesheet" href="{{ asset('css/generate_timetable.css') }}">
</head>
<body>

<div class="container">

    <div class="topbar">
        <h1>Generate Timetable</h1>
        <a class="back-link" href="{{ route('lecture.report') }}">&larr; Back to Lecture Report</a>
    </div>

    {{-- Grid parameters --}}
    <div class="card">
        <form id="genForm" method="GET" action="{{ route('timetable.generate') }}">
            {{-- keep the selected classes when applying parameters --}}
            @foreach($selected as $classTitle)
                <input type="hidden" name="classes[]" value="{{ $classTitle }}">
            @endforeach

            {{-- keep the user's per-subject theory/lab counts when applying parameters --}}
            @foreach($userCounts as $classTitle => $subjects)
                @foreach($subjects as $subjectId => $types)
                    @foreach($types as $type => $count)
                        <input type="hidden" name="counts[{{ $classTitle }}][{{ $subjectId }}][{{ $type }}]" value="{{ $count }}">
                    @endforeach
                @endforeach
            @endforeach

            <div class="params">
                <div class="param">
                    <label>Number of Days</label>
                    <input type="number" value="6" disabled>
                </div>
                <div class="param">
                    <label for="lectures_per_day">Lectures per Day</label>
                    <input type="number" id="lectures_per_day" name="lectures_per_day"
                           value="{{ $lecturesPerDay }}" min="1" max="12">
                </div>
                <div class="param">
                    <label for="max_labs_per_day">Max Labs per Day</label>
                    <input type="number" id="max_labs_per_day" name="max_labs_per_day"
                           value="{{ $maxLabsPerDay }}" min="1" max="4">
                </div>
                <div class="param">
                    <label for="lunch_after">Lunch After Period</label>
                    <input type="number" id="lunch_after" name="lunch_after"
                           value="{{ $lunchAfter }}" min="1" max="11">
                </div>
                <button type="submit" class="btn">Apply</button>
            </div>
        </form>
    </div>

    @forelse($reports as $report)
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="report-title">{{ $report->classTitle }}</h2>
                    @if(!empty($report->classTeacher))
                        <small class="ct-line">Class Teacher: <strong>{{ $report->classTeacher }}</strong></small>
                    @endif
                </div>
                @if($report->demand !== 0)
                    <div class="card-actions">
                        <button type="button" class="btn-change" onclick="toggleEdit(this)">Change</button>
                        <form method="POST" action="{{ route('timetable.save') }}" class="save-form" style="display:inline;">
                            @csrf
                            <input type="hidden" name="class_title" value="{{ $report->classTitle }}">
                            <input type="hidden" name="timetable" class="save-data">
                            <button type="button" class="btn-save" onclick="saveTimetable(this)">Save</button>
                        </form>
                    </div>
                @endif
            </div>

            @if($report->demand === 0)
                <p class="empty">No timetable data for this class.</p>
            @else
                <p class="hint">
                    Placed {{ $report->placed }} of {{ $report->demand }} periods
                    <small>(each lab = 2 consecutive periods)</small>
                </p>

                {{-- Per-class controls: shown ONLY when this class didn't fully fit --}}
                @if($report->placed < $report->demand)
                    <div class="fit-box">
                        <span class="fit-msg">⚠ Not all lectures fit for this class. Adjust just this class:</span>
                        <div class="fit-inputs">
                            <label>
                                Lectures/Day
                                <input type="number" form="genForm" min="1" max="14"
                                       name="class_periods[{{ $report->classTitle }}]"
                                       value="{{ $report->periods }}">
                            </label>
                            <label>
                                Max Labs/Day
                                <input type="number" form="genForm" min="1" max="6"
                                       name="class_maxlabs[{{ $report->classTitle }}]"
                                       value="{{ $report->maxLabs }}">
                            </label>
                            <button type="submit" form="genForm" class="btn">Apply</button>
                        </div>
                    </div>
                @else
                    {{-- Class fits: keep any override it already has so it isn't lost on next Apply --}}
                    @if($report->periods != $lecturesPerDay)
                        <input type="hidden" form="genForm" name="class_periods[{{ $report->classTitle }}]" value="{{ $report->periods }}">
                    @endif
                    @if($report->maxLabs != $maxLabsPerDay)
                        <input type="hidden" form="genForm" name="class_maxlabs[{{ $report->classTitle }}]" value="{{ $report->maxLabs }}">
                    @endif
                @endif

                <table class="grid cls-grid">
                    <thead>
                        <tr>
                            <th>Time</th>
                            @foreach($dayLabels as $day)
                                <th>{{ $day }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @for($p = 0; $p < $report->periods; $p++)
                            <tr>
                                @php $pt = $periodTimes[$p] ?? null; @endphp
                                <td class="period-cell">
                                    @if($pt)
                                        <strong>{{ \Illuminate\Support\Str::substr($pt->StartTime, 0, 5) }}–{{ \Illuminate\Support\Str::substr($pt->EndTime, 0, 5) }}</strong>
                                        <br><small>{{ $pt->Description }}</small>
                                    @else
                                        <strong>Period {{ $p + 1 }}</strong>
                                    @endif
                                </td>
                                @for($d = 0; $d < $days; $d++)
                                    @php
                                        $cell = $report->grid[$p][$d] ?? null;
                                        $pt = $periodTimes[$p] ?? null;
                                        $timeLabel = $pt
                                            ? \Illuminate\Support\Str::substr($pt->StartTime, 0, 5) . '–' . \Illuminate\Support\Str::substr($pt->EndTime, 0, 5)
                                            : 'Period ' . ($p + 1);
                                    @endphp

                                    {{-- Bottom half of a lab block is covered by the rowspan above --}}
                                    @if($cell && ($cell['lab_part'] ?? null) === 'bottom')
                                        @continue
                                    @endif

                                    <td class="fac-cell {{ $cell ? 'filled' : 'free' }}{{ !empty($cell['is_lab']) ? ' is-lab' : '' }}{{ !empty($cell['class_teacher']) ? ' is-ct' : '' }}"
                                        data-period="{{ $p }}" data-day="{{ $d }}"
                                        data-day-name="{{ $dayLabels[$d] }}"
                                        data-time="{{ $timeLabel }}"
                                        data-desc="{{ $pt->Description ?? '' }}"
                                        data-cur-subject="{{ $cell['subject'] ?? '' }}"
                                        data-cur-faculty="{{ $cell['faculty'] ?? '' }}"
                                        data-academy-id="{{ $cell['academy_id'] ?? '' }}"
                                        data-academic-year-id="{{ $cell['academic_year_id'] ?? '' }}"
                                        data-is-lab="{{ !empty($cell['is_lab']) ? 1 : 0 }}"
                                        onclick="cellClicked(this)"
                                        @if($cell && ($cell['lab_part'] ?? null) === 'top') rowspan="{{ $cell['lab_span'] ?? 2 }}" @endif>
                                        {{-- View mode --}}
                                        <span class="cell-view">
                                            @if($cell)
                                                <span class="lesson">
                                                    <span class="lesson-class">{{ $cell['subject'] }}</span>
                                                    @if(!empty($cell['is_lab'])) <span class="lab-tag">Lab · {{ $cell['lab_hours'] ?? 2 }} hrs</span> @endif
                                                    @if(!empty($cell['class_teacher'])) <span class="ct-tag">Class Teacher</span> @endif
                                                    <span class="lesson-subject">{{ $cell['faculty'] }}</span>
                                                </span>
                                            @else
                                                <span class="free-dash">—</span>
                                            @endif
                                        </span>

                                        {{-- Hidden data source for the picker modal --}}
                                        <select class="cell-edit" style="display:none;">
                                            <option value="">—</option>
                                            @foreach($report->subjects as $s)
                                                <option value="{{ $s->subject }}"
                                                        data-faculty="{{ $s->faculty }}"
                                                        {{ $cell && $cell['subject'] === $s->subject ? 'selected' : '' }}>
                                                    {{ $s->subject }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endfor
                            </tr>

                            {{-- 30-minute lunch break, same for all classes --}}
                            @if($lunchAfter > 0 && ($p + 1) === $lunchAfter && $lunchAfter < $report->periods)
                                <tr class="lunch-row">
                                    <td colspan="{{ $days + 1 }}">🍴 Lunch Break — 30 min</td>
                                </tr>
                            @endif
                        @endfor
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="hint">No classes were selected. Go back and pick classes first.</p>
    @endforelse

    {{-- Faculty-wise timetables: the same lectures pivoted by teacher --}}
    @if(!empty($facultyGrids))
        <h1 style="margin-top:32px; text-align:center;">Faculty-wise Timetables</h1>

        @foreach($facultyGrids as $facultyName => $fgrid)
            <div class="card">
                <div class="card-head">
                    <h2 class="report-title">{{ $facultyName }}</h2>
                    <div class="card-actions">
                        <button type="button" class="btn-change" onclick="toggleEdit(this)">Change</button>
                        <form method="POST" action="{{ route('timetable.save') }}" class="save-form" style="display:inline;">
                            @csrf
                            <input type="hidden" name="faculty_name" value="{{ $facultyName }}">
                            <input type="hidden" name="timetable" class="save-data">
                            <button type="button" class="btn-save" onclick="saveFacultyTimetable(this)">Save</button>
                        </form>
                    </div>
                </div>

                <table class="grid fac-grid">
                    <thead>
                        <tr>
                            <th>Time</th>
                            @foreach($dayLabels as $day)
                                <th>{{ $day }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @for($p = 0; $p < $lecturesPerDay; $p++)
                            <tr>
                                @php $pt = $periodTimes[$p] ?? null; @endphp
                                <td class="period-cell">
                                    @if($pt)
                                        <strong>{{ \Illuminate\Support\Str::substr($pt->StartTime, 0, 5) }}–{{ \Illuminate\Support\Str::substr($pt->EndTime, 0, 5) }}</strong>
                                        <br><small>{{ $pt->Description }}</small>
                                    @else
                                        <strong>Period {{ $p + 1 }}</strong>
                                    @endif
                                </td>

                                @for($d = 0; $d < $days; $d++)
                                    @php
                                        $cell = $fgrid[$p][$d] ?? null;
                                        $timeLabel = $pt
                                            ? \Illuminate\Support\Str::substr($pt->StartTime, 0, 5) . '–' . \Illuminate\Support\Str::substr($pt->EndTime, 0, 5)
                                            : 'Period ' . ($p + 1);
                                    @endphp

                                    {{-- Bottom half of a lab block is covered by the rowspan above --}}
                                    @if($cell && ($cell['lab_part'] ?? null) === 'bottom')
                                        @continue
                                    @endif

                                    <td class="fac-cell {{ $cell ? 'filled' : 'free' }}{{ !empty($cell['is_lab']) ? ' is-lab' : '' }}"
                                        data-period="{{ $p }}" data-day="{{ $d }}"
                                        data-day-name="{{ $dayLabels[$d] }}"
                                        data-time="{{ $timeLabel }}"
                                        data-desc="{{ $pt->Description ?? '' }}"
                                        data-cur-class="{{ $cell['class'] ?? '' }}"
                                        data-cur-subject="{{ $cell['subject'] ?? '' }}"
                                        data-is-lab="{{ !empty($cell['is_lab']) ? 1 : 0 }}"
                                        onclick="facCellClicked(this)"
                                        @if($cell && ($cell['lab_part'] ?? null) === 'top') rowspan="{{ $cell['lab_span'] ?? 2 }}" @endif>
                                        <span class="cell-view">
                                            @if($cell)
                                                <span class="lesson">
                                                    <span class="lesson-class">{{ $cell['class'] }}</span>
                                                    @if($cell['is_lab']) <span class="lab-tag">Lab · {{ $cell['lab_hours'] ?? 2 }} hrs</span> @endif
                                                    <span class="lesson-subject">{{ $cell['subject'] }}</span>
                                                </span>
                                            @else
                                                <span class="free-dash">—</span>
                                            @endif
                                        </span>

                                        {{-- Picker options: distinct class+subject pairs this teacher handles --}}
                                        <select class="cell-edit" style="display:none;">
                                            <option value="">—</option>
                                            @foreach($facultyOptions[$facultyName] as $o)
                                                <option value="{{ $o['subject'] }}"
                                                        data-class="{{ $o['class'] }}"
                                                        data-lab="{{ $o['is_lab'] ? 1 : 0 }}"
                                                        {{ $cell && $cell['class'] === $o['class'] && $cell['subject'] === $o['subject'] ? 'selected' : '' }}>
                                                    {{ $o['class'] }} — {{ $o['subject'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endfor
                            </tr>

                            {{-- 30-minute lunch break, same for all faculty --}}
                            @if($lunchAfter > 0 && ($p + 1) === $lunchAfter && $lunchAfter < $lecturesPerDay)
                                <tr class="lunch-row">
                                    <td colspan="{{ $days + 1 }}">🍴 Lunch Break — 30 min</td>
                                </tr>
                            @endif
                        @endfor
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

</div>

{{-- Custom popup for teacher-clash warnings --}}
<div id="popupOverlay" class="popup-overlay">
    <div class="popup">
        <div class="popup-icon">!</div>
        <h3 class="popup-title">Faculty not available</h3>
        <p class="popup-msg" id="popupMsg"></p>
        <button type="button" class="popup-btn" onclick="closePopup()">OK</button>
    </div>
</div>

{{-- Subject picker modal (opened by clicking a cell in edit mode) --}}
<div id="changeOverlay" class="popup-overlay">
    <div class="popup change-popup">
        <h3 class="popup-title">Select Subject</h3>
        <div id="changeList" class="change-list"></div>
        <div style="text-align:right; margin-top:14px;">
            <button type="button" class="popup-btn popup-btn-grey" onclick="closeChange()">Cancel</button>
        </div>
    </div>
</div>

<script>
    // Custom warning popup.
    function showPopup(html) {
        document.getElementById('popupMsg').innerHTML = html;
        document.getElementById('popupOverlay').style.display = 'flex';
    }
    function closePopup() {
        document.getElementById('popupOverlay').style.display = 'none';
    }

    // Gather this class's timetable (including any manual edits) and submit it
    // to the Save endpoint, which dumps the data.
    function saveTimetable(btn) {
        const card = btn.closest('.card');
        const lectures = [];
        card.querySelectorAll('td[data-period]').forEach(td => {
            const subject = td.dataset.curSubject;
            if (!subject) return; // skip empty slots
            lectures.push({
                academy_id:       td.dataset.academyId || '',
                academic_year_id: td.dataset.academicYearId || '',
                day:              td.dataset.dayName,
                time:             td.dataset.time,
                description:      td.dataset.desc,
                subject:          subject,
                faculty:          td.dataset.curFaculty || '',
                is_lab:           td.dataset.isLab === '1',
            });
        });
        const form = btn.closest('form');
        form.querySelector('.save-data').value = JSON.stringify(lectures);
        form.submit();
    }

    // Toggle a class card between view and edit mode. In edit mode, clicking a
    // cell opens the subject picker.
    function toggleEdit(btn) {
        const card = btn.closest('.card');
        const editing = card.classList.toggle('editing');
        btn.textContent = editing ? 'Done' : 'Change';
    }

    // --- Subject picker modal ---
    let activeTd = null;

    function cellClicked(td) {
        // Only react while this cell's card is in edit mode.
        if (!td.closest('.card').classList.contains('editing')) return;

        activeTd = td;
        const sel  = td.querySelector('.cell-edit');
        const list = document.getElementById('changeList');
        list.innerHTML = '';

        Array.from(sel.options).forEach(opt => {
            const faculty = opt.getAttribute('data-faculty') || '';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'change-option' + (opt.value === td.dataset.curSubject ? ' active' : '');
            btn.innerHTML = opt.value
                ? '<strong>' + opt.value + '</strong>' + (faculty ? '<br><small>' + faculty + '</small>' : '')
                : '<em>— (clear slot)</em>';
            btn.onclick = () => chooseSubject(opt.value, faculty);
            list.appendChild(btn);
        });

        document.getElementById('changeOverlay').style.display = 'flex';
    }

    function closeChange() {
        document.getElementById('changeOverlay').style.display = 'none';
        activeTd = null;
    }

    function chooseSubject(subject, faculty) {
        const td = activeTd;
        if (!td) return;

        // Teacher availability (H1) check.
        if (subject && faculty) {
            const clashTd = facultyClash(td.dataset.period, td.dataset.day, faculty, td);
            if (clashTd) {
                showPopup(
                    '<strong>' + faculty + '</strong> is already teaching ' +
                    '<strong>' + clashTd.dataset.curSubject + '</strong> in this period. ' +
                    'Pick a different subject or slot.'
                );
                return; // keep the picker open
            }
        }

        // Apply.
        td.dataset.curSubject = subject;
        td.dataset.curFaculty = faculty;
        td.querySelector('.cell-edit').value = subject;
        td.classList.toggle('filled', !!subject);
        td.classList.toggle('free', !subject);
        td.querySelector('.cell-view').innerHTML = subject
            ? '<span class="lesson">' +
                  '<span class="lesson-class">' + subject + '</span>' +
                  (faculty ? '<span class="lesson-subject">' + faculty + '</span>' : '') +
              '</span>'
            : '<span class="free-dash">—</span>';

        closeChange();
    }

    // --- Faculty-grid editing (mirrors the class grid; picks a class+subject) ---
    let facActiveTd = null;

    function facCellClicked(td) {
        if (!td.closest('.card').classList.contains('editing')) return;

        facActiveTd = td;
        const sel  = td.querySelector('.cell-edit');
        const list = document.getElementById('changeList');
        list.innerHTML = '';

        Array.from(sel.options).forEach(opt => {
            const cls = opt.getAttribute('data-class') || '';
            const isActive = opt.value === td.dataset.curSubject && cls === td.dataset.curClass;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'change-option' + (isActive ? ' active' : '');
            btn.innerHTML = opt.value
                ? '<strong>' + cls + '</strong><br><small>' + opt.value + '</small>'
                : '<em>— (clear slot)</em>';
            btn.onclick = () => facChooseSubject(opt.value, cls, opt.getAttribute('data-lab') === '1');
            list.appendChild(btn);
        });

        document.getElementById('changeOverlay').style.display = 'flex';
    }

    function facChooseSubject(subject, cls, isLab) {
        const td = facActiveTd;
        if (!td) return;

        td.dataset.curSubject = subject;
        td.dataset.curClass   = cls;
        td.dataset.isLab      = isLab ? '1' : '0';
        td.classList.toggle('filled', !!subject);
        td.classList.toggle('free', !subject);
        td.classList.toggle('is-lab', !!subject && isLab);
        td.querySelector('.cell-view').innerHTML = subject
            ? '<span class="lesson">' +
                  '<span class="lesson-class">' + cls + '</span>' +
                  (isLab ? '<span class="lab-tag">Lab · 2 hrs</span>' : '') +
                  '<span class="lesson-subject">' + subject + '</span>' +
              '</span>'
            : '<span class="free-dash">—</span>';

        closeChange();
    }

    // Collect a faculty's timetable and POST it to the Save endpoint (dd dump).
    function saveFacultyTimetable(btn) {
        const card = btn.closest('.card');
        const faculty = card.querySelector('input[name="faculty_name"]').value;
        const lectures = [];
        card.querySelectorAll('td[data-period]').forEach(td => {
            const subject = td.dataset.curSubject;
            if (!subject) return; // skip free slots
            lectures.push({
                faculty:     faculty,
                class:       td.dataset.curClass || '',
                day:         td.dataset.dayName,
                time:        td.dataset.time,
                description: td.dataset.desc,
                subject:     subject,
                is_lab:      td.dataset.isLab === '1',
            });
        });
        const form = btn.closest('form');
        form.querySelector('.save-data').value = JSON.stringify(lectures);
        form.submit();
    }

    // Is this faculty already assigned in the same (period, day) in ANY class?
    function facultyClash(period, day, faculty, exceptTd) {
        const cells = document.querySelectorAll(
            'td[data-period="' + period + '"][data-day="' + day + '"]'
        );
        for (const td of cells) {
            if (td !== exceptTd && td.dataset.curFaculty === faculty) {
                return td;
            }
        }
        return null;
    }

</script>

</body>
</html>
