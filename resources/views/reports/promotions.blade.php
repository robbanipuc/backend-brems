<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Promotion Report' }}</title>
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
        <div>{{ $subtitle ?? 'Promotion History Report' }}</div>
        <div class="meta">Generated: {{ $generated_at ?? '' }} | By: {{ $generated_by ?? '-' }} | Period: {{ $from_date ?? 'All' }} to {{ $to_date ?? 'Present' }}</div>
    </div>

    @if(!empty($summary))
    <h3>Summary</h3>
    <table>
        <tr><td><strong>Total Promotions</strong></td><td>{{ $summary['total_promotions'] ?? 0 }}</td></tr>
        <tr><td><strong>Unique Employees</strong></td><td>{{ $summary['unique_employees'] ?? 0 }}</td></tr>
        <tr><td><strong>Unique Designations</strong></td><td>{{ $summary['unique_designations'] ?? 0 }}</td></tr>
    </table>
    @endif

    <h3>Promotion List</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>New Designation</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            @forelse($promotions ?? [] as $p)
            <tr>
                <td>{{ is_array($p) ? ($p['promotion_date'] ?? '-') : ($p->promotion_date ?? '-') }}</td>
                <td>
                    @if(is_array($p) && !empty($p['employee']))
                        {{ trim(($p['employee']['first_name'] ?? '') . ' ' . ($p['employee']['last_name'] ?? '')) ?: '-' }}
                    @elseif(is_object($p) && $p->employee)
                        {{ $p->employee->first_name ?? '' }} {{ $p->employee->last_name ?? '' }}
                    @else
                        -
                    @endif
                </td>
                <td>{{ is_array($p) ? (data_get($p, 'new_designation.title', '-')) : ($p->newDesignation->title ?? '-') }}</td>
                <td>{{ is_array($p) ? (data_get($p, 'new_designation.grade', '-')) : ($p->newDesignation->grade ?? '-') }}</td>
            </tr>
            @empty
            <tr><td colspan="4">No promotions found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
