<!DOCTYPE html>
<html>
<head>
    <title>Employee List</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #006A4E; color: white; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { color: #006A4E; font-size: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">BANGLADESH RAILWAY</div>
        <div>Official Employee Directory</div>
        <div style="font-size: 10px; color: #666; margin-top: 5px;">
            Generated on: {{ $date ?? date('d M Y') }} | Filter: {{ $filter_applied ?? 'All' }} | By: {{ $generated_by ?? '-' }}
        </div>
        <div style="font-size: 10px; color: #666; margin-top: 3px;">Total: {{ $total_count ?? 0 }} employees</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Designation</th>
                <th>Office</th>
                <th>Cadre</th>
                <th>Batch No</th>
                <th>NID</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees ?? [] as $emp)
            <tr>
                <td>{{ $emp->id }}</td>
                <td>{{ $emp->first_name }} {{ $emp->last_name }}</td>
                <td>{{ optional($emp->designation)->title ?? 'N/A' }}</td>
                <td>{{ optional($emp->office)->name ?? 'Unassigned' }}</td>
                <td>{{ $emp->cadre_type ? ucfirst(str_replace('_', ' ', $emp->cadre_type)) : '-' }}</td>
                <td>{{ $emp->batch_no ?? '-' }}</td>
                <td>{{ $emp->nid_number ?? '-' }}</td>
                <td>{{ ucfirst($emp->status ?? '-') }}</td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align: center; padding: 20px;">No employees match the current filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>