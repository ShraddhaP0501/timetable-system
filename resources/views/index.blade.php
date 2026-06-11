<!DOCTYPE html>
<html>
<head>
    <title>Subject List</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">

    <h2 class="mb-4">Academy Subjects</h2>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>ASID</th>
                <th>BatchID</th>
                <th>StandardID</th>
                <th>MediumID</th>
                <th>DivisionID</th>
                <th>SubjectID</th>
                <th>AcademyID</th>
                <th>GradeType</th>
                <th>SubjectCategory</th>
                <th>SubjectType</th>
                <th>Active</th>
                <th>Elective</th>
            </tr>
        </thead>

        <tbody>

        @forelse($subjects as $subject)

            <tr>
                <td>{{ $subject->ASID }}</td>
                <td>{{ $subject->BatchID }}</td>
                <td>{{ $subject->StandardID }}</td>
                <td>{{ $subject->MediumID }}</td>
                <td>{{ $subject->DivisionID }}</td>
                <td>{{ $subject->SubjectID }}</td>
                <td>{{ $subject->AcademyID }}</td>
                <td>{{ $subject->GradeType }}</td>
                <td>{{ $subject->SubjectCategory }}</td>
                <td>{{ $subject->SubjectType }}</td>
                <td>{{ $subject->IsActive }}</td>
                <td>{{ $subject->IsElectiveSubject }}</td>
            </tr>

        @empty

            <tr>
                <td colspan="12" class="text-center">
                    No Records Found
                </td>
            </tr>

        @endforelse

        </tbody>

    </table>

</div>

</body>
</html>