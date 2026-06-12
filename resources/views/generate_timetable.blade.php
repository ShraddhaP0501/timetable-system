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
        <form method="GET" action="{{ route('timetable.generate') }}">
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
                <button type="submit" class="btn">Apply</button>
            </div>
        </form>
    </div>

    @forelse($reports as $report)
        <div class="card">
            <div class="card-head">
                <h2 class="report-title">{{ $report->classTitle }}</h2>
                @if($report->demand !== 0)
                    <button type="button" class="btn-change" onclick="toggleEdit(this)">Change</button>
                @endif
            </div>

            @if($report->demand === 0)
                <p class="empty">No timetable data for this class.</p>
            @else
                <p class="hint">
                    Placed {{ $report->placed }} of {{ $report->demand }} periods
                    <small>(each lab = 2 consecutive periods)</small>
                    @if($report->placed < $report->demand)
                        — increase Lectures per Day to fit the rest.
                    @endif
                </p>

                <table class="grid">
                    <thead>
                        <tr>
                            <th>Period</th>
                            @foreach($dayLabels as $day)
                                <th>{{ $day }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @for($p = 0; $p < $lecturesPerDay; $p++)
                            <tr>
                                <td><strong>{{ $p + 1 }}</strong></td>
                                @for($d = 0; $d < $days; $d++)
                                    @php $cell = $report->grid[$p][$d] ?? null; @endphp

                                    {{-- Bottom half of a lab block is covered by the rowspan above --}}
                                    @if($cell && ($cell['lab_part'] ?? null) === 'bottom')
                                        @continue
                                    @endif

                                    <td data-period="{{ $p }}" data-day="{{ $d }}"
                                        data-cur-subject="{{ $cell['subject'] ?? '' }}"
                                        data-cur-faculty="{{ $cell['faculty'] ?? '' }}"
                                        onclick="cellClicked(this)"
                                        @if($cell && ($cell['lab_part'] ?? null) === 'top') rowspan="2" @endif
                                        style="{{ $cell ? '' : 'background:#fafafa;color:#ccc;' }}{{ ($cell['lab_part'] ?? null) === 'top' ? ' vertical-align:middle; background:#fffdf5;' : '' }}">
                                        {{-- View mode --}}
                                        <span class="cell-view">
                                            @if($cell)
                                                {{ $cell['subject'] }}
                                                @if(!empty($cell['is_lab'])) <span class="lab-tag">Lab · 2 hrs</span> @endif
                                                <br><small style="color:#6b7280;">{{ $cell['faculty'] }}</small>
                                            @else
                                                —
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
                        @endfor
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="hint">No classes were selected. Go back and pick classes first.</p>
    @endforelse

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
        td.querySelector('.cell-view').innerHTML = subject
            ? subject + '<br><small style="color:#6b7280;">' + faculty + '</small>'
            : '—';

        closeChange();
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
