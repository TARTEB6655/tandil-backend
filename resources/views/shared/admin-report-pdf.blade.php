<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }}</title>
    <style>{!! \App\Support\PdfBrand::documentCss() !!}</style>
</head>
<body>
    @include('partials.pdf-brand-header')
    <div class="header">
        <h1>{{ $documentTitle }}</h1>
        <p>{{ $documentSubtitle }}</p>
        <p>
            @if (!empty($periodLabel))
                Period: {{ $periodLabel }} &nbsp;|&nbsp;
            @endif
            Generated: {{ $generatedAt }}
        </p>
    </div>
    <div class="content report-body">
        {!! $bodyHtml !!}
    </div>
    <div class="footer">
        {{ config('app.name', 'Tandil') }} &mdash; {{ $documentTitle }} &mdash; {{ $generatedAt }}
    </div>
</body>
</html>
