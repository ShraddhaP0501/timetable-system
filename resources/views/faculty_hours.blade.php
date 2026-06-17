<!DOCTYPE html>
<html>
<head>
    <title>Faculty Weekly Periods</title>
    <link rel="stylesheet" href="{{ asset('css/subject_mapping.css') }}">
</head>
<body>

<div class="container">

    <h1 style="text-align:center;">Faculty Weekly Periods</h1>
    <p class="demo-note" style="text-align:center;">Step 1 — set how many periods (slots) each faculty can teach per day. <strong>Save</strong> prints the payload (nothing is written yet).</p>

    <form method="POST" action="{{ route('faculty.hours.save') }}">
        @csrf

        <div class="card">
            <div class="card-head card-head--3col">
                <span class="search-box">
                    <input type="text" id="faculty-search" class="search-input"
                           placeholder="Search faculty…" autocomplete="off">
                    <span class="search-ico">🔍</span>
                </span>
                <h2 class="report-title" style="text-align:center;">Periods per day <span class="muted">(Mon–Sat)</span></h2>
                <button type="submit" class="btn" formaction="{{ route('faculty.hours.continue') }}">Continue to Subject Mapping →</button>
            </div>

            <table class="matrix">
                <thead>
                    <tr>
                        <th>Faculty</th>
                        @foreach($days as $day)
                            <th class="col-hours">{{ \Illuminate\Support\Str::substr($day, 0, 3) }}</th>
                        @endforeach
                        <th class="col-hours">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($faculty as $uid => $name)
                        <tr class="matrix-row" data-row data-name="{{ \Illuminate\Support\Str::lower($name) }}">
                            <td class="subj-name">{{ $name }}</td>
                            @foreach($days as $day)
                                <td class="col-hours">
                                    <input type="number" min="0" max="12"
                                           name="hours[{{ $uid }}][{{ $day }}]"
                                           value="{{ $defaultHours }}"
                                           class="periods-input hours-input">
                                </td>
                            @endforeach
                            <td class="col-hours"><span class="row-total">0</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($days) + 2 }}" class="empty">No faculty found for this academy.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="save-row">
                <button type="submit" class="btn">Save Periods</button>
            </div>
        </div>
    </form>

</div>

<script>
    // Live weekly total per faculty row.
    function refreshTotals() {
        document.querySelectorAll('[data-row]').forEach(function (row) {
            var sum = 0;
            row.querySelectorAll('.hours-input').forEach(function (i) {
                sum += parseInt(i.value, 10) || 0;
            });
            var cell = row.querySelector('.row-total');
            if (cell) cell.textContent = sum;
        });
    }
    document.querySelectorAll('.hours-input').forEach(function (i) {
        i.addEventListener('input', refreshTotals);
    });
    refreshTotals();

    // Live search: filter faculty rows by name.
    var search = document.getElementById('faculty-search');
    if (search) {
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            document.querySelectorAll('[data-row]').forEach(function (row) {
                var name = row.getAttribute('data-name') || '';
                row.style.display = (q === '' || name.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
</script>

</body>
</html>
