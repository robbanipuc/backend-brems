<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Office Report' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background-color: #006A4E; color: white; }
        td.numeric, th.numeric { text-align: right; }
        .header { text-align: center; margin-bottom: 10px; }
        .logo { color: #006A4E; font-size: 16px; font-weight: bold; }
        .meta { font-size: 8px; color: #666; margin-top: 3px; }
        h3 { margin-top: 14px; font-size: 11pt; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        .info-table { margin: 8px 0; }
        .info-table td:first-child { font-weight: bold; width: 140px; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">BANGLADESH RAILWAY</div>
        <div>{{ $subtitle ?? 'Office Report' }}</div>
        <div class="meta">Generated: {{ $generated_at ?? '' }} | By: {{ $generated_by ?? '-' }}</div>
    </div>

    <h3>Office Information</h3>
    <table class="info-table">
        <tr><td>Name</td><td>{{ $office->name ?? '-' }}</td></tr>
        <tr><td>Code</td><td>{{ $office->code ?? '-' }}</td></tr>
        <tr><td>Zone</td><td>{{ $office->zone ?? 'Not assigned' }}</td></tr>
        <tr><td>Location</td><td>{{ $office->location ?? '-' }}</td></tr>
        <tr><td>Active Employees</td><td>{{ $office->employees->count() ?? 0 }}</td></tr>
        <tr><td>Admin Status</td><td>{{ $office->has_admin ? 'Has Admin' : 'No Admin' }}</td></tr>
        @if(!empty($office->admin))
        <tr><td>Admin Name</td><td>{{ $office->admin->name ?? '-' }}</td></tr>
        <tr><td>Admin Email</td><td>{{ $office->admin->email ?? '-' }}</td></tr>
        @endif
    </table>

    <h3>Employees ({{ $office->employees->count() ?? 0 }})</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Designation</th>
            </tr>
        </thead>
        <tbody>
            @forelse($office->employees ?? [] as $emp)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ ($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '') }}</td>
                <td>{{ $emp->designation->title ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="3">No employees in this office.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Posts &amp; Vacancies (Sanctioned Strength)</h3>
    <table>
        <thead>
            <tr>
                <th>Designation Name</th>
                <th class="numeric">Total Post</th>
                <th class="numeric">Posted</th>
                <th class="numeric">Vacant</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vacant_rows ?? [] as $row)
            <tr>
                <td>{{ $row['designation_name'] ?? '-' }}</td>
                <td class="numeric">{{ $row['total_posts'] ?? 0 }}</td>
                <td class="numeric">{{ $row['posted'] ?? 0 }}</td>
                <td class="numeric">{{ $row['vacant'] ?? 0 }}</td>
            </tr>
            @empty
            <tr><td colspan="4">No designation posts data.</td></tr>
            @endforelse
            @if(!empty($vacant_totals))
            <tr style="font-weight: bold; background-color: #f5f5f5;">
                <td>Total</td>
                <td class="numeric">{{ $vacant_totals['total_posts'] ?? 0 }}</td>
                <td class="numeric">{{ $vacant_totals['posted'] ?? 0 }}</td>
                <td class="numeric">{{ $vacant_totals['vacant'] ?? 0 }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
