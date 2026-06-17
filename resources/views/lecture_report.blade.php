<!DOCTYPE html>
<html>
<head>
    <title>Lecture Report</title>
        <link rel="stylesheet" href="{{ asset('css/lecture_report.css') }}">
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
                        <th>Type</th>
                        <th>Per Week Hours</th>
                        <th>Continuous Hrs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report->records as $row)
                        @php
                            $hasTheory = $row->lecture_week > 0;
                            $hasLab    = $row->lab_week > 0;
                            if (!$hasTheory && !$hasLab) { $hasTheory = true; } // show at least one row
                        @endphp

                        @if($hasTheory)
                            <tr>
                                <td>{{ $row->program }}</td>
                                <td>{{ $row->semester }}</td>
                                <td>{{ $row->subject }}</td>
                                <td><span class="tag tag-theory">Theory</span></td>
                                <td>
                                    <input type="number" min="0" max="60" form="reportForm"
                                           name="counts[{{ $report->classTitle }}][{{ $row->subject_id }}][theory]"
                                           value="{{ $row->lecture_week }}"
                                           style="width:80px; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
                                </td>
                                <td style="color:#9ca3af;">—</td>
                            </tr>
                        @endif

                        @if($hasLab)
                            <tr>
                                <td>{{ $row->program }}</td>
                                <td>{{ $row->semester }}</td>
                                <td>{{ $row->subject }}</td>
                                <td><span class="tag tag-lab">Lab</span></td>
                                <td>
                                    {{-- Per-week lab count (number of lab sessions). --}}
                                    <input type="number" min="0" max="30" form="reportForm"
                                           name="counts[{{ $report->classTitle }}][{{ $row->subject_id }}][lab]"
                                           value="{{ $row->lab_week }}"
                                           style="width:80px; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
                                </td>
                                <td>
                                    {{-- Continuous hours per lab block (default 2). --}}
                                    <input type="number" min="1" max="8" form="reportForm"
                                           name="counts[{{ $report->classTitle }}][{{ $row->subject_id }}][lab_hours]"
                                           value="2"
                                           style="width:80px; padding:6px; border:1px solid #d1d5db; border-radius:4px;">
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="empty">No Data Found</td>
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
