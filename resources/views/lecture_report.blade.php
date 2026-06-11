<!DOCTYPE html>
<html>
<head>
    <title>Lecture Report</title>

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

        h1 {
            font-size: 26px;
            margin: 0 0 24px;
            color: #1f2937;
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #374151;
        }

        .checkbox-list {
            max-height: 260px;
            overflow-y: auto;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 14px;
            background: #fafafa;
        }

        .checkbox-list label {
            display: block;
            font-weight: normal;
            padding: 5px 0;
            cursor: pointer;
        }

        .checkbox-list label:hover { color: #2563eb; }
        .checkbox-list input { margin-right: 8px; }

        .btn {
            margin-top: 16px;
            padding: 10px 22px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
        }
        .btn:hover { background: #1d4ed8; }

        .btn-reset {
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
            margin-left: 10px;
            display: inline-block;
        }
        .btn-reset:hover { background: #d1d5db; }

        .report-title {
            font-size: 18px;
            margin: 0 0 14px;
            color: #1f2937;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

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

        .empty {
            text-align: center;
            color: #9ca3af;
            padding: 18px;
        }

        .hint { color: #6b7280; }
    </style>
</head>
<body>

<div class="container">

    <h1>Lecture Report</h1>

    <form id="reportForm" method="GET" action="{{ route('lecture.report') }}">
        <div class="card">
            <span class="label">Select Classes</span>
            <div class="checkbox-list">
                @foreach($classes as $class)
                    <label>
                        <input type="checkbox" name="classes[]" value="{{ $class->classTitle }}"
                            {{ in_array($class->classTitle, $selected) ? 'checked' : '' }}>
                        {{ $class->classTitle }}
                    </label>
                @endforeach
            </div>
            <button type="submit" class="btn">Show Report</button>
            <a href="{{ route('lecture.report') }}" class="btn btn-reset">Reset</a>
        </div>
    </form>

    {{-- One report card per selected class --}}
    @forelse($reports as $report)
        <div class="card">
            <h2 class="report-title">{{ $report->classTitle }}</h2>

            <table>
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Semester</th>
                        <th>Subject</th>
                        <th>Number of Lecture Week</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report->records as $row)
                        <tr>
                            <td>{{ $row->program }}</td>
                            <td>{{ $row->semester }}</td>
                            <td>{{ $row->subject }}</td>
                            <td>{{ $row->lecture_week }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">No Data Found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p class="hint">Select one or more classes above and click <strong>Show Report</strong>.</p>
    @endforelse

    @if(count($reports))
        <div style="text-align:center; margin-top:10px;">
            <button type="submit" class="btn" form="reportForm"
                    formaction="{{ route('timetable.generate') }}">
                Generate Timetable
            </button>
        </div>
    @endif

</div>

</body>
</html>
