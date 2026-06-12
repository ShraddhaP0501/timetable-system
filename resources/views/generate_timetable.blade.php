<!DOCTYPE html>
<html>
<head>
    <title>Generate Timetable</title>

    <style>
        * { box-sizing: border-box; }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            margin: 0;
            background: #f4f6f9;
            color: #2c3e50;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        h1 { font-size: 26px; margin: 0; color: #1f2937; }

        .back-link {
            text-decoration: none;
            color: #2563eb;
            font-weight: 600;
        }
        .back-link:hover { text-decoration: underline; }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .report-title { font-size: 18px; margin: 0; color: #1f2937; }

        .btn-change {
            padding: 7px 16px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-change:hover { background: #059669; }

        /* Timetable grid: equal-width day columns so cells/dropdowns aren't cramped */
        .grid { table-layout: fixed; }
        .grid th:first-child,
        .grid td:first-child { width: 60px; text-align: center; }
        .grid td { vertical-align: top; }

        /* While editing, cells become clickable */
        .card.editing { box-shadow: 0 0 0 2px #10b981 inset; }
        .card.editing tbody td { cursor: pointer; }
        .card.editing tbody td:hover { background: #ecfdf5; outline: 2px solid #10b981; outline-offset: -2px; }

        /* Subject picker modal */
        .change-popup { width: 440px; text-align: left; }
        .change-list { max-height: 360px; overflow-y: auto; }
        .change-option {
            display: block;
            width: 100%;
            text-align: left;
            padding: 10px 12px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
            color: #1f2937;
        }
        .change-option small { color: #6b7280; }
        .change-option:hover { background: #eef4ff; border-color: #93c5fd; }
        .change-option.active { border-color: #2563eb; background: #eff6ff; }
        .popup-btn-grey { background: #e5e7eb; color: #374151; }
        .popup-btn-grey:hover { background: #d1d5db; }

        /* Custom popup */
        .popup-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.55);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        /* The clash warning must sit ABOVE the subject picker */
        #popupOverlay { z-index: 1100; }
        .popup {
            background: #fff;
            width: 380px;
            max-width: 90%;
            border-radius: 12px;
            padding: 26px 24px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
            animation: popIn 0.15s ease-out;
        }
        @keyframes popIn { from { transform: scale(0.92); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .popup-icon {
            width: 46px; height: 46px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: #fef2f2;
            color: #dc2626;
            font-size: 26px;
            font-weight: bold;
            line-height: 46px;
        }
        .popup-title { margin: 0 0 8px; font-size: 18px; color: #1f2937; }
        .popup-msg { margin: 0 0 20px; color: #4b5563; font-size: 14px; line-height: 1.5; }
        .popup-btn {
            padding: 9px 28px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
        }
        .popup-btn:hover { background: #1d4ed8; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; }

        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #eee; }

        thead th {
            background: #f3f4f6;
            color: #374151;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.4px;
        }

        tbody tr:nth-child(even) { background: #fafbfc; }
        tbody tr:hover { background: #eef4ff; }

        .empty { text-align: center; color: #9ca3af; padding: 18px; }
        .hint  { color: #6b7280; }

        .params { display: flex; align-items: flex-end; gap: 24px; flex-wrap: wrap; }
        .param { display: flex; flex-direction: column; }
        .param label { font-weight: 600; margin-bottom: 6px; color: #374151; }
        .param input {
            width: 140px;
            padding: 9px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 22px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
        }
        .btn:hover { background: #1d4ed8; }
    </style>
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

            {{-- keep the user's per-subject lecture counts when applying parameters --}}
            @foreach($userLectures as $classTitle => $subjectCounts)
                @foreach($subjectCounts as $subjectId => $count)
                    <input type="hidden" name="lectures[{{ $classTitle }}][{{ $subjectId }}]" value="{{ $count }}">
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
                    Placed {{ $report->placed }} of {{ $report->demand }} lectures
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
                                    <td data-period="{{ $p }}" data-day="{{ $d }}"
                                        data-cur-subject="{{ $cell['subject'] ?? '' }}"
                                        data-cur-faculty="{{ $cell['faculty'] ?? '' }}"
                                        onclick="cellClicked(this)"
                                        style="{{ $cell ? '' : 'background:#fafafa;color:#ccc;' }}">
                                        {{-- View mode --}}
                                        <span class="cell-view">
                                            @if($cell)
                                                {{ $cell['subject'] }}
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
