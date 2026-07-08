<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $businessName }} — Analytics</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background: #eef2f3; color: #1f2937; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        .hero { background: linear-gradient(135deg, #0f766e, #115e59); color: #fff; border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .hero h1 { margin: 0 0 8px; font-size: 1.5rem; }
        .hero p { margin: 4px 0; opacity: 0.92; }
        .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow-x: auto; }
        .card h2 { margin: 0 0 12px; font-size: 1.05rem; color: #0f766e; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .meta-item { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .meta-item span { display: block; font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
        .meta-item strong { display: block; margin-top: 4px; font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.92rem; min-width: 520px; }
        th, td { text-align: left; padding: 10px 12px; border: 1px solid #e5e7eb; }
        th { font-weight: 600; color: #fff; background: #0f766e; white-space: nowrap; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        tbody tr:hover { background: #ecfdf5; }
        .download { display: inline-block; margin-top: 8px; padding: 10px 16px; background: #0f766e; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .meta { font-size: 0.875rem; color: #6b7280; margin-top: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>{{ $businessName }}</h1>
        <p>Performance Analytics — {{ $period }}</p>
        @if($generatedAt)<p>Generated: {{ $generatedAt }}</p>@endif
        @if($expiresAt)<p>Link expires: {{ $expiresAt }}</p>@endif
    </div>

    @if(!empty($metaRows))
        <div class="card">
            <h2>Report Summary</h2>
            <div class="meta-grid">
                @foreach($metaRows as $row)
                    <div class="meta-item">
                        <span>{{ $row['label'] }}</span>
                        <strong>{{ $row['value'] }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @foreach($sections as $section)
        @if(in_array($section['title'], ['Vendor Performance Analytics Report', 'Report Summary'], true))
            @continue
        @endif
        @if(!empty($section['headers']) && !empty($section['rows']))
            <div class="card">
                <h2>{{ $section['title'] }}</h2>
                <table>
                    <thead>
                        <tr>
                            @foreach($section['headers'] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    <div class="card">
        <h2>Download CSV</h2>
        <p>Download the full report as a CSV file (open in Excel or Google Sheets).</p>
        <a class="download" href="{{ $fileUrl }}">Download CSV File</a>
        <p class="meta">Public shared analytics from Tandil.</p>
    </div>
</div>
</body>
</html>
