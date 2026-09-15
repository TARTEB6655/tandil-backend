<?php

namespace App\Support;

/**
 * Shared brand assets for DomPDF / report documents.
 * Uses the same logo as Laravel dashboards: public/images/logo.png
 */
final class PdfBrand
{
    public static function logoPath(): string
    {
        return public_path('images/logo.png');
    }

    /**
     * DomPDF-safe data URI (works on Windows/Linux without file:// quirks).
     */
    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();
        if (! is_file($path)) {
            return null;
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    /**
     * Small top brand strip HTML for DomPDF documents.
     */
    public static function headerHtml(): string
    {
        $src = self::logoDataUri();
        if ($src === null) {
            return '';
        }

        $alt = e((string) config('app.name', 'Tandil'));

        return '<div class="pdf-brand-header" style="padding:10px 20px 6px;text-align:left;">'
            .'<img src="'.$src.'" alt="'.$alt.'" style="height:36px;width:auto;max-width:120px;object-fit:contain;"/>'
            .'</div>';
    }
}
