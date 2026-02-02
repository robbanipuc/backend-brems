<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Transfer Report' }}</title>
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
        <div>{{ $subtitle ?? 'Transfer History Report' }}</div>
        <div class="meta">Generated: {{ $generated_at ?? '' }} | By: {{ $generated_by ?? '-' }} | Period: {{ $from_date ?? 'All' }} to {{ $to_date ?? 'Present' }}</div>
    </div>

    @if(!empty($summary))
    <h3>Summary</h3>
    <table>
        <tr><td><strong>Total Transfers</strong></td><td>{{ $summary['total_transfers'] ?? 0 }}</td></tr>
        <tr><td><strong>Transfers In</strong></td><td>{{ $summary['transfers_in'] ?? 0 }}</td></tr>
        <tr><td><strong>Initial Postings</strong></td><td>{{ $summary['initial_postings'] ?? 0 }}</td></tr>
        <tr><td><strong>Unique Employees</strong></td><td>{{ $summary['unique_employees'] ?? 0 }}</td></tr>
    </table>
    @endif

    <h3>Transfer List</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>From Office</th>
                <th>To Office</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transfers ?? [] as $t)
            <tr>
                <td>{{ is_array($t) ? ($t['transfer_date'] ?? '-') : ($t->transfer_date ?? '-') }}</td>
                <td>
                    @if(is_array($t) && !empty($t['employee']))
                        {{ ($t['employee']['first_name'] ?? '') . ' ' . ($t['employee']['last_name'] ?? '') }}
                    @elseif(is_object($t) && $t->employee)
                        {{ $t->employee->first_name ?? '' }} {{ $t->employee->last_name ?? '' }}
                    @else
                        -
                    @endif
                </td>
                <td>{{ is_array($t) ? (data_get($t, 'from_office.name', '-')) : ($t->fromOffice->name ?? '-') }}</td>
                <td>{{ is_array($t) ? (data_get($t, 'to_office.name', '-')) : ($t->toOffice->name ?? '-') }}</td>
            </tr>
            @empty
            <tr><td colspan="4">No transfers found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
