{{-- Shared Tandil brand strip for DomPDF (admin indigo, logo 50px). --}}
@php
    $pdfLogoSrc = \App\Support\PdfBrand::logoDataUri();
    $pdfLogoHeight = \App\Support\PdfBrand::LOGO_HEIGHT_PX;
@endphp
@if ($pdfLogoSrc)
    <div class="pdf-brand-header">
        <img
            src="{{ $pdfLogoSrc }}"
            alt="{{ config('app.name', 'Tandil') }}"
            style="height: {{ $pdfLogoHeight }}px; width: auto; max-width: 160px; object-fit: contain; display: block;"
        />
    </div>
@endif
