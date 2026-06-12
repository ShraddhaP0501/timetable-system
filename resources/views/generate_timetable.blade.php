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

        .cell-edit { width: 100%; padding: 4px; }

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

                <table>
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

                                        {{-- Edit mode (hidden until "Change") --}}
                                        <select class="cell-edit" style="display:none;" onchange="updateCell(this)">
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

<script>
    // Toggle a single class card between view and edit (Change) mode.
    function toggleEdit(btn) {
        const card = btn.closest('.card');
        const editing = card.classList.toggle('editing');
        card.querySelectorAll('.cell-view').forEach(el => el.style.display = editing ? 'none' : '');
        card.querySelectorAll('.cell-edit').forEach(el => el.style.display = editing ? '' : 'none');
        btn.textContent = editing ? 'Done' : 'Change';
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

    // Replace a cell's subject + faculty when a dropdown changes.
    function updateCell(sel) {
        const td = sel.closest('td');
        const view = td.querySelector('.cell-view');
        const subject = sel.value;
        const faculty = sel.options[sel.selectedIndex].getAttribute('data-faculty') || '';

        // Check teacher availability (H1) before applying.
        if (subject && faculty) {
            const clashTd = facultyClash(td.dataset.period, td.dataset.day, faculty, td);
            if (clashTd) {
                alert(
                    'Faculty "' + faculty + '" is not available in this slot.\n' +
                    'Already teaching "' + clashTd.dataset.curSubject + '" at the same period/day.'
                );
                sel.value = td.dataset.curSubject; // revert the dropdown
                return;
            }
        }

        // Apply the change.
        td.dataset.curSubject = subject;
        td.dataset.curFaculty = faculty;
        if (subject) {
            view.innerHTML = subject + '<br><small style="color:#6b7280;">' + faculty + '</small>';
        } else {
            view.innerHTML = '—';
        }
    }
</script>

</body>
</html>
