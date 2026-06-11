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

        .report-title { font-size: 18px; margin: 0 0 14px; color: #1f2937; }

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
    </style>
</head>
<body>

<div class="container">

    <div class="topbar">
        <h1>Generate Timetable</h1>
        <a class="back-link" href="{{ route('lecture.report') }}">&larr; Back to Lecture Report</a>
    </div>

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
        <p class="hint">No classes were selected. Go back and pick classes first.</p>
    @endforelse

</div>

</body>
</html>
