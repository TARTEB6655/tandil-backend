<?php

namespace App\Support;

/**
 * Shared DomPDF branding — same logo + indigo palette as the admin dashboard.
 */
final class PdfBrand
{
    /** Admin indigo-600 / indigo-700 */
    public const PRIMARY = '#4f46e5';

    public const PRIMARY_DARK = '#4338ca';

    public const PRIMARY_SOFT = '#eef2ff';

    public const TEXT = '#1f2937';

    public const MUTED = '#6b7280';

    public const BORDER = '#e5e7eb';

    public const LOGO_HEIGHT_PX = 50;

    public static function logoPath(): string
    {
        return public_path('images/logo.png');
    }

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
     * Shared stylesheet for all PDF reports / invoices.
     */
    public static function documentCss(): string
    {
        $p = self::PRIMARY;
        $pd = self::PRIMARY_DARK;
        $ps = self::PRIMARY_SOFT;
        $text = self::TEXT;
        $muted = self::MUTED;
        $border = self::BORDER;
        $h = self::LOGO_HEIGHT_PX;

        return <<<CSS
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: {$text}; line-height: 1.45; background: #fff; }
            .pdf-brand-header { padding: 14px 20px 8px; text-align: left; border-bottom: 1px solid {$border}; }
            .pdf-brand-header img { height: {$h}px; width: auto; max-width: 160px; object-fit: contain; display: block; }
            .header { background: {$p}; color: #fff; padding: 16px 20px; margin-bottom: 16px; }
            .header h1 { font-size: 18px; margin-bottom: 4px; font-weight: bold; }
            .header p { font-size: 11px; opacity: 0.95; margin-top: 2px; }
            .content { padding: 0 20px 20px; }
            .section { margin-bottom: 16px; page-break-inside: avoid; }
            .section-title { font-size: 13px; font-weight: bold; color: {$pd}; border-bottom: 2px solid {$p}; padding-bottom: 4px; margin-bottom: 8px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
            th { background: {$p}; color: #fff; font-weight: bold; text-align: left; padding: 7px 8px; font-size: 10px; }
            td { padding: 7px 8px; border-bottom: 1px solid {$border}; vertical-align: top; }
            tr:nth-child(even) td { background: #f9fafb; }
            .meta-table td { border: none; padding: 4px 8px; background: transparent !important; }
            .meta-label { color: {$muted}; width: 30%; font-weight: bold; }
            .totals td { border: none; background: transparent !important; }
            .totals .amount { text-align: right; font-weight: bold; }
            .notes { background: {$ps}; border: 1px solid {$border}; padding: 10px; }
            .empty { color: {$muted}; font-style: italic; padding: 8px 0; }
            .footer { margin-top: 12px; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid {$border}; padding-top: 8px; }
            .num { text-align: right; }

            /* Plain-text admin/HR reports (GenerateReportJob) */
            .report-body { padding: 8px 20px 24px; }
            .report-title { color: {$pd}; font-size: 16pt; font-weight: bold; margin: 0 0 6px 0; }
            .report-title::after { content: ""; display: block; width: 40%; margin-top: 6px; border-bottom: 3px solid {$p}; }
            .meta { color: {$muted}; font-size: 9pt; margin: 3px 0; }
            .divider { border: none; border-top: 1px solid {$border}; margin: 14px 0; }
            .supervisor-name { color: {$pd}; font-size: 11pt; font-weight: bold; margin: 14px 0 4px 0; }
            .stat-line { color: {$text}; margin: 2px 0 8px 0; padding-left: 8px; }
            .section-head { color: {$p}; font-size: 10pt; font-weight: bold; margin: 12px 0 6px 0; }
            .stat-item { margin: 2px 0; padding-left: 12px; color: {$text}; }
            .metric-line { margin: 4px 0; padding: 6px 10px; background: {$ps}; color: {$pd}; font-weight: bold; }
            .visit-detail { margin: 4px 0; padding: 6px 8px; background: {$ps}; border-left: 3px solid {$p}; font-size: 9pt; }
            .body { margin: 4px 0; }
            .spacer { height: 6px; }
            h1, h2, h3 { page-break-after: avoid; }
        CSS;
    }

    public static function headerHtml(): string
    {
        $src = self::logoDataUri();
        if ($src === null) {
            return '';
        }

        $alt = e((string) config('app.name', 'Tandil'));
        $h = self::LOGO_HEIGHT_PX;

        return '<div class="pdf-brand-header">'
            .'<img src="'.$src.'" alt="'.$alt.'" style="height:'.$h.'px;width:auto;max-width:160px;object-fit:contain;display:block;"/>'
            .'</div>';
    }
}
