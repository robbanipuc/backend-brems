<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Employee Statistics' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #006A4E; color: white; }
        .header { text-align: center; margin-bottom: 15px; }
        .logo { color: #006A4E; font-size: 18px; font-weight: bold; }
        .meta { font-size: 9px; color: #666; margin-top: 5px; }
        h3 { margin-top: 15px; font-size: 12pt; color: #333; }
        .stats-grid { display: table; width: 100%; margin: 10px 0; }
        .stats-row { display: table-row; }
        .stats-cell { display: table-cell; padding: 8px; border: 1px solid #ddd; width: 50%; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">BANGLADESH RAILWAY</div>
        <div>{{ $subtitle ?? 'Employee Management System' }}</div>
        <div class="meta">Generated: {{ $generated_at ?? date('d M Y, h:i A') }} | By: {{ $generated_by ?? '-' }}</div>
    </div>

    <h3>Overview</h3>
    <table>
        <tr><td><strong>Total Employees</strong></td><td>{{ $stats['total_employees'] ?? 0 }}</td></tr>
        <tr><td><strong>Active Employees</strong></td><td>{{ $stats['active_employees'] ?? 0 }}</td></tr>
    </table>

    @if(!empty($stats['verification_status']))
    <h3>Verification Status</h3>
    <table>
        <tr><th>Status</th><th>Count</th></tr>
        <tr><td>Verified</td><td>{{ $stats['verification_status']['verified'] ?? 0 }}</td></tr>
        <tr><td>Unverified</td><td>{{ $stats['verification_status']['unverified'] ?? 0 }}</td></tr>
    </table>
    @endif

    @if(!empty($stats['by_status']))
    <h3>By Status</h3>
    <table>
        <tr><th>Status</th><th>Count</th></tr>
        @foreach($stats['by_status'] as $status => $count)
        <tr><td>{{ ucfirst($status) }}</td><td>{{ $count }}</td></tr>
        @endforeach
    </table>
    @endif

    @if(!empty($stats['by_gender']))
    <h3>By Gender</h3>
    <table>
        <tr><th>Gender</th><th>Count</th></tr>
        @foreach($stats['by_gender'] as $gender => $count)
        <tr><td>{{ ucfirst($gender ?? 'N/A') }}</td><td>{{ $count }}</td></tr>
        @endforeach
    </table>
    @endif

    @if(!empty($stats['by_designation']) && is_array($stats['by_designation']))
    <h3>By Designation (Top 15)</h3>
    <table>
        <tr><th>Designation</th><th>Grade</th><th>Count</th></tr>
        @foreach($stats['by_designation'] as $row)
        <tr>
            <td>{{ $row['title'] ?? '-' }}</td>
            <td>{{ $row['grade'] ?? '-' }}</td>
            <td>{{ $row['count'] ?? 0 }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    @if(!empty($stats['by_office']) && is_array($stats['by_office']))
    <h3>By Office (Top 15)</h3>
    <table>
        <tr><th>Office</th><th>Code</th><th>Count</th></tr>
        @foreach($stats['by_office'] as $row)
        <tr>
            <td>{{ $row['name'] ?? '-' }}</td>
            <td>{{ $row['code'] ?? '-' }}</td>
            <td>{{ $row['count'] ?? 0 }}</td>
        </tr>
        @endforeach
    </table>
    @endif
</body>
</html>
