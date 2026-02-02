<!DOCTYPE html>
<html>
<head>
    <title>Employee Profile</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .section-title { background: #006A4E; color: white; padding: 5px; font-weight: bold; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Bangladesh Railway</h2>
        <h3>Official Employee Profile</h3>
    </div>

    <!-- Personal Info -->
    <div class="section-title">Personal Information</div>
    <table>
        <tr>
            <th>Name (English)</th><td>{{ $emp->first_name }} {{ $emp->last_name }}</td>
            <th>Designation</th><td>{{ $emp->designation->title ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>NID</th><td>{{ $emp->nid_number }}</td>
            <th>Mobile</th><td>{{ $emp->personal_info['mobile'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Date of Birth</th><td>{{ $emp->personal_info['dob'] ?? 'N/A' }}</td>
            <th>Blood Group</th><td>{{ $emp->personal_info['blood_group'] ?? 'N/A' }}</td>
        </tr>
    </table>

    <!-- Address -->
    <div class="section-title">Address Information</div>
    <table>
        <tr>
            <th width="20%">Present Address</th>
            <td>
                {{ $emp->address_info['present']['house'] ?? '' }},
                {{ $emp->address_info['present']['road'] ?? '' }},
                {{ $emp->address_info['present']['upazila'] ?? '' }},
                {{ $emp->address_info['present']['district'] ?? '' }}
            </td>
        </tr>
        <tr>
            <th width="20%">Permanent Address</th>
            <td>
                {{ $emp->address_info['permanent']['house'] ?? '' }},
                {{ $emp->address_info['permanent']['road'] ?? '' }},
                {{ $emp->address_info['permanent']['upazila'] ?? '' }},
                {{ $emp->address_info['permanent']['district'] ?? '' }}
            </td>
        </tr>
    </table>

    <!-- Education -->
    <div class="section-title">Academic Records</div>
    <table>
        <thead>
            <tr>
                <th>Exam</th>
                <th>Institute</th>
                <th>Year</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            @foreach($emp->academics as $edu)
            <tr>
                <td>{{ $edu->exam_name }}</td>
                <td>{{ $edu->institute }}</td>
                <td>{{ $edu->passing_year }}</td>
                <td>{{ $edu->result }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>