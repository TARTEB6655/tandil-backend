{{-- Small Tandil logo strip for DomPDF reports (same asset as dashboards). --}}
@php
    $pdfLogoSrc = \App\Support\PdfBrand::logoDataUri();
@endphp
@if ($pdfLogoSrc)
    <div class="pdf-brand-header" style="padding:10px 20px 6px; text-align:left;">
        <img
            src="{{ $pdfLogoSrc }}"
            alt="{{ config('app.name', 'Tandil') }}"
            style="height:36px; width:auto; max-width:120px; object-fit:contain;"
        />
    </div>
@endif
