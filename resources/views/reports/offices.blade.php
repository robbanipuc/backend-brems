<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Office Report' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background-color: #006A4E; color: white; }
        .header { text-align: center; margin-bottom: 10px; }
        .logo { color: #006A4E; font-size: 16px; font-weight: bold; }
        .meta { font-size: 8px; color: #666; margin-top: 3px; }
        h3 { margin-top: 12px; font-size: 11pt; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">BANGLADESH RAILWAY</div>
        <div>{{ $subtitle ?? 'Office Directory Report' }}</div>
        <div class="meta">Generated: {{ $generated_at ?? '' }} | By: {{ $generated_by ?? '-' }}</div>
    </div>

    @if(!empty($summary))
    <h3>Summary</h3>
    <table>
        <tr><td><strong>Total Offices</strong></td><td>{{ $summary['total_offices'] ?? 0 }}</td></tr>
        <tr><td><strong>Offices with Admin</strong></td><td>{{ $summary['offices_with_admin'] ?? 0 }}</td></tr>
        <tr><td><strong>Offices without Admin</strong></td><td>{{ $summary['offices_without_admin'] ?? 0 }}</td></tr>
        <tr><td><strong>Total Employees</strong></td><td>{{ $summary['total_employees'] ?? 0 }}</td></tr>
        <tr><td><strong>Total Verified</strong></td><td>{{ $summary['total_verified'] ?? 0 }}</td></tr>
        <tr><td><strong>Total Unverified</strong></td><td>{{ $summary['total_unverified'] ?? 0 }}</td></tr>
    </table>
    @endif

    <h3>Office List</h3>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Parent</th>
                <th>Employees</th>
                <th>Verified</th>
                <th>Admin</th>
            </tr>
        </thead>
        <tbody>
            @forelse($offices ?? [] as $office)
            <tr>
                <td>{{ is_array($office) ? ($office['name'] ?? '-') : ($office->name ?? '-') }}</td>
                <td>{{ is_array($office) ? ($office['code'] ?? '-') : ($office->code ?? '-') }}</td>
                <td>{{ is_array($office) ? (data_get($office, 'parent.name', '-')) : ($office->parent->name ?? '-') }}</td>
                <td>{{ is_array($office) ? ($office['total_employees'] ?? 0) : ($office->total_employees ?? 0) }}</td>
                <td>{{ is_array($office) ? ($office['verified_employees'] ?? 0) : ($office->verified_employees ?? 0) }}</td>
                <td>{{ is_array($office) ? ($office['admin_name'] ?? '-') : ($office->admin_name ?? '-') }}</td>
            </tr>
            @empty
            <tr><td colspan="6">No offices found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
