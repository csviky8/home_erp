<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 11px; }
        h1 { font-size: 18px; letter-spacing: 1px; color: #0f766e; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th { background: #12372a; color: white; text-align: left; padding: 8px; }
        td { border-bottom: 1px solid #dbe7e1; padding: 7px; }
        tr:nth-child(even) td { background: #f3faf7; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>Generated {{ now()->format('d M Y, H:i') }}</p>
    <table>
        <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>@foreach ($row as $value)<td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($headings) }}">No records found for this report.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
