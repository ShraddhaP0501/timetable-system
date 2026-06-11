<!DOCTYPE html>
<html>
<head>
    <title>Lecture Report</title>

    <style>

        body{
            font-family: Arial, sans-serif;
            padding:20px;
        }

        .form-group{
            margin-bottom:20px;
        }

        select{
            width:350px;
            padding:10px;
        }

        table{
            width:100%;
            border-collapse:collapse;
        }

        table th,
        table td{
            border:1px solid #ddd;
            padding:10px;
            text-align:left;
        }

        table th{
            background:#f2f2f2;
        }

    </style>

</head>
<body>

<h2>Lecture Report</h2>

<form method="GET" action="{{ route('lecture.report') }}">

    <div class="form-group">

        <label>Select Class</label>

        <br><br>

        <select name="class_title" onchange="this.form.submit()">
            <option value="">Select Class</option>

            @foreach($classes as $class)
                <option value="{{ $class->classTitle }}"
                    {{ request('class_title') == $class->classTitle ? 'selected' : '' }}>
                    {{ $class->classTitle }}
                </option>
            @endforeach
        </select>
    </div>

</form>

<table>

    <thead>

    <tr>
        <th>PROGRAM</th>
        <th>SEMESTER</th>
        <th>SUBJECT</th>
        <th>NUMBER OF LECTURE WEEK</th>
    </tr>

    </thead>

    <tbody>

    @forelse($records as $row)

        <tr>
        <td>{{ $row->program }}</td>
        <td>{{ $row->semester }}</td>
        <td>{{ $row->subject }}</td>
        <td>{{ $row->lecture_week }}</td>

        </tr>

    @empty

        <tr>
            <td colspan="4" align="center">
                No Data Found
            </td>
        </tr>

    @endforelse

    </tbody>

</table>

</body>
</html>