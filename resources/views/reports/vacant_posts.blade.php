<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Vacant Posts Report' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background-color: #006A4E; color: white; }
        td.numeric, th.numeric { text-align: right; }
        .header { text-align: center; margin-bottom: 10px; }
        .logo { color: #006A4E; font-size: 16px; font-weight: bold; }
        .meta { font-size: 8px; color: #666; margin-top: 3px; }
        h3 { margin-top: 12px; font-size: 11pt; }
        .office-name { font-weight: bold; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">BANGLADESH RAILWAY</div>
        <div>{{ $subtitle ?? 'Vacant Posts (Sanctioned Strength)' }}</div>
        <div class="meta">Generated: {{ $generated_at ?? '' }} | By: {{ $generated_by ?? '-' }}</div>
    </div>

    <div class="office-name">{{ $office['name'] ?? '' }} ({{ $office['code'] ?? '' }})</div>

    <table>
        <thead>
            <tr>
                <th>Designation Name</th>
                <th class="numeric">Total Post</th>
                <th class="numeric">Posted</th>
                <th class="numeric">Vacant Post</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows ?? [] as $row)
            <tr>
                <td>{{ $row['designation_name'] ?? '-' }}</td>
                <td class="numeric">{{ $row['total_posts'] ?? 0 }}</td>
                <td class="numeric">{{ $row['posted'] ?? 0 }}</td>
                <td class="numeric">{{ $row['vacant'] ?? 0 }}</td>
            </tr>
            @empty
            <tr><td colspan="4">No designations found.</td></tr>
            @endforelse
            @if(!empty($totals))
            <tr style="font-weight: bold; background-color: #f5f5f5;">
                <td>Total</td>
                <td class="numeric">{{ $totals['total_posts'] ?? 0 }}</td>
                <td class="numeric">{{ $totals['posted'] ?? 0 }}</td>
                <td class="numeric">{{ $totals['vacant'] ?? 0 }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
