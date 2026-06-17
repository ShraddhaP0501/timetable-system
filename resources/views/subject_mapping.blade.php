<!DOCTYPE html>
<html>
<head>
    <title>Subject Mapping &amp; Faculty</title>
    <link rel="stylesheet" href="{{ asset('css/subject_mapping.css') }}">
</head>
<body>

<div class="container">

    <h1 style="text-align: center;">Subject Mapping &amp; Faculty Assignment</h1>
    <p class="demo-note" style="text-align: center;">Loaded live from the database. <strong>Save</strong> prints the payload that would be stored (nothing is written yet).</p>

    {{-- ============ Step 1: pick a Standard ============ --}}
    <div class="card">
        <span class="label">Standard</span>
        <div class="chip-row">
            @foreach($standards as $s)
                <a class="chip {{ (int) $s->ASID === $standardId ? 'chip-active' : '' }}"
                   href="{{ route('subject.mapping', ['standard' => $s->ASID]) }}">
                    {{ $s->Title }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- ============ Step 3: the matrix ============ --}}
    <form method="POST" action="{{ route('subject.mapping.save') }}">
        @csrf
        <input type="hidden" name="section" value="{{ $divisionId }}">

        @php
            $totalSubjects = count($matrix);
            $assignedCount = collect($matrix)->filter(fn ($r) => $r['faculty_id'] !== null)->count();
            $missingCount  = $totalSubjects - $assignedCount;
        @endphp

        <div class="card">
            <div class="card-head">
                <h2 class="report-title">{{ optional($standards->firstWhere('ASID', $standardId))->Title }}</h2>
                @if($totalSubjects)
                    <div class="status-line">
                        <span class="pill pill-total" id="pill-total">{{ $totalSubjects }} subjects</span>
                        <span class="pill pill-ok" id="pill-ok">{{ $assignedCount }} assigned</span>
                        <span class="pill pill-warn" id="pill-warn" style="{{ $missingCount ? '' : 'display:none' }}">{{ $missingCount }} need a teacher</span>
                    </div>
                @endif
            </div>

            <table class="matrix">
                <thead>
                    <tr>
                        <th class="col-map">Taught?</th>
                        <th>Subject</th>
                        <th class="col-cat">Category</th>
                        <th class="col-periods">Periods / week</th>
                        <th>Faculty <span class="muted">(★ = qualified for this subject)</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($matrix as $row)
                        <tr class="matrix-row {{ $row['mapped'] ? '' : 'row-off' }} {{ $row['mapped'] && $row['faculty_id'] === null ? 'row-unassigned' : '' }}" data-row>
                            <td class="col-map">
                                <input type="checkbox"
                                       name="rows[{{ $row['subject_id'] }}][mapped]" value="1"
                                       {{ $row['mapped'] ? 'checked' : '' }}
                                       data-toggle>
                            </td>
                            <td class="subj-name">
                                {{ $row['name'] }}
                                @if($row['practical']) <span class="tag tag-practical">Lab</span> @endif
                            </td>
                            <td class="col-cat">
                                <span class="tag tag-{{ \Illuminate\Support\Str::slug($row['category']) }}">{{ $row['category'] }}</span>
                            </td>
                            <td class="col-periods">
                                <input type="number" min="0" max="12"
                                       name="rows[{{ $row['subject_id'] }}][periods]"
                                       value="{{ $row['periods'] }}"
                                       class="periods-input" data-field
                                       {{ $row['mapped'] ? '' : 'disabled' }}>
                            </td>
                            <td>
                                <div class="ms">
                                    <button type="button" class="ms-control" data-field
                                            {{ $row['mapped'] ? '' : 'disabled' }}>
                                        <span class="ms-label ms-placeholder">Select faculty</span>
                                        <span class="ms-caret">▾</span>
                                    </button>
                                    <div class="ms-menu">
                                        @foreach($allFaculty as $uid => $name)
                                            @php $isQual = in_array($uid, array_column($row['qualified'], 'id'), true); @endphp
                                            <label class="ms-option">
                                                <input type="checkbox"
                                                       name="rows[{{ $row['subject_id'] }}][faculty_id][]"
                                                       value="{{ $uid }}" data-field
                                                       {{ $row['faculty_id'] === $uid ? 'checked' : '' }}
                                                       {{ $row['mapped'] ? '' : 'disabled' }}>
                                                <span>{{ $name }}{{ $isQual ? ' ★' : '' }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No subjects mapped for this standard &amp; section.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="save-row">
                <button type="submit" class="btn">Save Mapping</button>
            </div>
        </div>
    </form>

    {{-- ============ Live faculty workload (this class) — rendered by JS ============ --}}
    <div class="card" id="workload-card" style="display:none">
        <h2 class="report-title">Faculty Workload <span class="muted">(this class)</span></h2>
        <div class="workload-grid" id="workload-grid"></div>
        <p class="hint">Periods of every subject a teacher is assigned in this class, summed live. Bars are relative to the busiest teacher here.</p>
    </div>

</div>

<script>
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-row]'));

    // Recompute the status pills + amber "needs a teacher" highlight live,
    // reflecting whatever the user has selected in the browser right now.
    function refreshStatus() {
        var total = rows.length, assigned = 0, missing = 0;
        rows.forEach(function (row) {
            var cb  = row.querySelector('[data-toggle]');
            var picked = row.querySelectorAll('.ms-menu input:checked').length;
            var mapped = cb ? cb.checked : true;
            if (mapped && picked > 0) {
                assigned++;
                row.classList.remove('row-unassigned');
            } else if (mapped) {
                missing++;
                row.classList.add('row-unassigned');
            } else {
                row.classList.remove('row-unassigned'); // unticked rows aren't "missing"
            }
        });
        var pt = document.getElementById('pill-total');
        var po = document.getElementById('pill-ok');
        var pw = document.getElementById('pill-warn');
        if (pt) pt.textContent = total + ' subjects';
        if (po) po.textContent = assigned + ' assigned';
        if (pw) {
            pw.textContent = missing + ' need a teacher';
            pw.style.display = missing ? '' : 'none';
        }
        refreshWorkload();
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Recompute the Faculty Workload card from the live selections: every
    // teacher ticked on a (taught) subject gets that subject's periods, summed.
    var workloadCard = document.getElementById('workload-card');
    var workloadGrid = document.getElementById('workload-grid');
    function refreshWorkload() {
        if (!workloadGrid) return;
        var totals = {};
        rows.forEach(function (row) {
            var cb = row.querySelector('[data-toggle]');
            if (cb && !cb.checked) return; // skip untaught subjects
            var pin = row.querySelector('.periods-input');
            var p = parseInt(pin && pin.value, 10) || 0;
            row.querySelectorAll('.ms-menu input:checked').forEach(function (c) {
                var name = c.parentNode.querySelector('span').textContent.replace(' ★', '').trim();
                totals[name] = (totals[name] || 0) + p;
            });
        });
        var names = Object.keys(totals);
        if (!names.length) { workloadCard.style.display = 'none'; return; }
        workloadCard.style.display = '';
        var max = Math.max.apply(null, names.map(function (n) { return totals[n]; }));
        names.sort(function (a, b) { return totals[b] - totals[a]; });
        workloadGrid.innerHTML = names.map(function (n) {
            var pct = max > 0 ? Math.round(totals[n] / max * 100) : 0;
            return '<div class="workload-item"><div class="workload-top"><span>' + esc(n) +
                   '</span><span class="muted">' + totals[n] + ' periods</span></div>' +
                   '<div class="bar"><div class="bar-fill" style="width:' + pct + '%"></div></div></div>';
        }).join('');
    }

    // Ticking a subject enables its periods + faculty fields; unticking greys them.
    document.querySelectorAll('[data-toggle]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var row = cb.closest('[data-row]');
            row.classList.toggle('row-off', !cb.checked);
            row.querySelectorAll('[data-field]').forEach(function (f) {
                f.disabled = !cb.checked;
            });
            refreshStatus();
        });
    });

    // Changing periods updates the workload totals.
    document.querySelectorAll('.periods-input').forEach(function (inp) {
        inp.addEventListener('input', refreshWorkload);
    });

    // ---- Multi-select faculty (checkbox dropdown) ----
    document.querySelectorAll('.ms').forEach(function (ms) {
        var control = ms.querySelector('.ms-control');
        var label   = ms.querySelector('.ms-label');
        var menu    = ms.querySelector('.ms-menu');

        function updateLabel() {
            var picked = Array.prototype.slice.call(menu.querySelectorAll('input:checked'));
            if (picked.length === 0) {
                label.textContent = 'Select faculty';
                label.classList.add('ms-placeholder');
            } else {
                label.textContent = picked.map(function (c) {
                    return c.parentNode.querySelector('span').textContent.replace(' ★', '').trim();
                }).join(', ');
                label.classList.remove('ms-placeholder');
            }
        }

        control.addEventListener('click', function (e) {
            e.stopPropagation();
            if (control.disabled) return;
            // close other open menus
            document.querySelectorAll('.ms.open').forEach(function (m) { if (m !== ms) m.classList.remove('open'); });
            ms.classList.toggle('open');
        });
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
        menu.addEventListener('change', function () { updateLabel(); refreshStatus(); });

        updateLabel();
    });

    // Click outside closes any open dropdown.
    document.addEventListener('click', function () {
        document.querySelectorAll('.ms.open').forEach(function (m) { m.classList.remove('open'); });
    });

    refreshStatus(); // sync on load
</script>

</body>
</html>
