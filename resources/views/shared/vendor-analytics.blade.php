<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $businessName }} — Analytics</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f4f6f8; color: #1f2937; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px 48px; }
        .hero { background: linear-gradient(135deg, #0f766e, #115e59); color: #fff; border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .hero h1 { margin: 0 0 8px; font-size: 1.5rem; }
        .hero p { margin: 4px 0; opacity: 0.9; }
        .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .card h2 { margin: 0 0 12px; font-size: 1.1rem; color: #0f766e; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e5e7eb; }
        th { font-weight: 600; color: #374151; background: #f9fafb; }
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

    @foreach($sections as $section)
        @if(!empty($section['headers']) || !empty($section['rows']))
            <div class="card">
                <h2>{{ $section['title'] }}</h2>
                @if(!empty($section['headers']))
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
                @endif
            </div>
        @endif
    @endforeach

    <div class="card">
        <h2>Download</h2>
        <p>Download the full analytics report as CSV (Excel-compatible).</p>
        <a class="download" href="{{ $fileUrl }}">Download CSV</a>
        <p class="meta">This is a public shared link from Tandil Vendor Analytics.</p>
    </div>
</div>
</body>
</html>
