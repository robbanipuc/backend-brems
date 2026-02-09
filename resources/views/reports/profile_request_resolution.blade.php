<!DOCTYPE html>
<html>
<head>
    <title>Profile Request Resolution - #{{ $request->id ?? '' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #006A4E; color: white; width: 35%; }
        .header { text-align: center; margin-bottom: 16px; }
        .logo { color: #006A4E; font-size: 18px; font-weight: bold; }
        .meta { font-size: 9px; color: #666; margin-top: 4px; }
        h3 { margin-top: 14px; font-size: 12pt; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        .status-approved { color: #059669; font-weight: bold; }
        .status-rejected { color: #dc2626; font-weight: bold; }
        .changes-box { background: #f9fafb; padding: 10px; border: 1px solid #e5e7eb; margin: 8px 0; font-size: 9pt; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">BANGLADESH RAILWAY</div>
        <div>Profile Request Resolution Report</div>
        <div class="meta">Generated: {{ $date ?? '' }}</div>
    </div>

    <h3>Request Details</h3>
    <table>
        <tr><th>Request ID</th><td>#{{ $request->id ?? '-' }}</td></tr>
        <tr><th>Request Type</th><td>{{ $request->request_type ?? '-' }}</td></tr>
        <tr><th>Status</th><td><span class="status-{{ $status === 'APPROVED' ? 'approved' : 'rejected' }}">{{ $status ?? '-' }}</span></td></tr>
        <tr><th>Submitted</th><td>{{ isset($request->created_at) ? $request->created_at->format('d M Y H:i') : '-' }}</td></tr>
        @if(isset($request->reviewed_at) && $request->reviewed_at)
        <tr><th>Reviewed At</th><td>{{ $request->reviewed_at->format('d M Y H:i') }}</td></tr>
        @endif
        @if(isset($reviewer) && $reviewer)
        <tr><th>Reviewed By</th><td>{{ $reviewer->name ?? '-' }}</td></tr>
        @endif
    </table>

    <h3>Employee</h3>
    <table>
        <tr><th>Name</th><td>{{ $employee->first_name ?? '' }} {{ $employee->last_name ?? '' }}</td></tr>
        <tr><th>Designation</th><td>{{ $employee->designation->title ?? '-' }}</td></tr>
        <tr><th>Office</th><td>{{ $employee->office->name ?? '-' }}</td></tr>
        @if(!empty($employee->nid_number))
        <tr><th>NID</th><td>{{ $employee->nid_number }}</td></tr>
        @endif
    </table>

    @if(!empty($request->details))
    <h3>Details</h3>
    <p>{{ $request->details }}</p>
    @endif

    @if(!empty($proposed_changes) && is_array($proposed_changes))
    <h3>Requested / Proposed Changes</h3>
    @if(!empty($proposed_changes['document_update']))
    <p><strong>Document / file update</strong></p>
    <table>
        <tr><th>Type</th><td>{{ $proposed_changes['document_update']['type'] ?? '-' }}</td></tr>
        <tr><th>Uploaded at</th><td>{{ isset($proposed_changes['document_update']['uploaded_at']) ? \Carbon\Carbon::parse($proposed_changes['document_update']['uploaded_at'])->format('d M Y H:i') : '-' }}</td></tr>
    </table>
    @endif
    @if(!empty($proposed_changes['pending_documents']) && is_array($proposed_changes['pending_documents']))
    <p><strong>Pending documents</strong></p>
    <table>
        @foreach($proposed_changes['pending_documents'] as $doc)
        <tr><th>Document</th><td>{{ $doc['document_type'] ?? 'File' }}</td></tr>
        @endforeach
    </table>
    @endif
    <div class="changes-box">
        @foreach($proposed_changes as $key => $value)
            @if(in_array($key, ['document_update', 'pending_documents'])) @continue @endif
            <div><strong>{{ is_numeric($key) ? 'Item' : ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                @if(is_array($value))
                    <table style="margin:4px 0; font-size:9pt;"><tbody>
                    @foreach($value as $k => $v)
                    <tr><td style="width:30%;">{{ is_numeric($k) ? '—' : ucfirst(str_replace('_', ' ', $k)) }}</td><td>{{ is_array($v) ? json_encode($v) : $v }}</td></tr>
                    @endforeach
                    </tbody></table>
                @else
                    {{ $value }}
                @endif
            </div>
        @endforeach
    </div>
    @endif

    <p style="margin-top: 20px; font-size: 8pt; color: #666;">This is an official resolution record for the above profile request.</p>
</body>
</html>
