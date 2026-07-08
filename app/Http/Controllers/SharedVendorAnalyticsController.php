<?php

namespace App\Http\Controllers;

use App\Services\Vendor\VendorAnalyticsShareService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SharedVendorAnalyticsController extends Controller
{
    public function __construct(
        private readonly VendorAnalyticsShareService $shares
    ) {}

    /**
     * Public browser view — no login required.
     */
    public function show(string $token): View|Response
    {
        $share = $this->shares->findActiveShare($token);
        if ($share === null) {
            abort(404, 'This analytics link has expired or is invalid.');
        }

        $csv = Storage::disk('public')->get($share->file_path) ?: '';
        $sections = $this->parseCsvSections($csv);
        $vendor = $share->vendor;
        $profile = $vendor?->profile;

        $payload = $this->shares->sharePayload($share);

        return view('shared.vendor-analytics', [
            'businessName' => $profile?->business_name ?? 'Vendor Analytics',
            'period' => ucfirst($share->period),
            'generatedAt' => $share->updated_at?->format('M j, Y g:i A'),
            'expiresAt' => $share->expires_at?->format('M j, Y'),
            'fileUrl' => $payload['file_url'],
            'sections' => $sections,
            'metaRows' => $this->parseMetaRows($csv),
        ]);
    }

    /**
     * CSV file download (Excel-compatible).
     */
    public function download(string $token): BinaryFileResponse
    {
        $share = $this->shares->findActiveShare($token);
        if ($share === null) {
            abort(404, 'This analytics link has expired or is invalid.');
        }

        $filename = 'vendor_analytics_'.$share->period.'_'.now()->format('Y-m-d').'.csv';

        return response()->download(
            Storage::disk('public')->path($share->file_path),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function parseMetaRows(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $meta = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                break;
            }
            $row = str_getcsv($line);
            if (count($row) === 2 && ($row[0] ?? '') !== '' && ($row[1] ?? '') !== '') {
                $meta[] = ['label' => (string) $row[0], 'value' => (string) $row[1]];
            }
        }

        return $meta;
    }

    /**
     * @return list<array{title: string, headers: list<string>, rows: list<list<string>>}>
     */
    private function parseCsvSections(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $rows = array_map(fn (string $line) => str_getcsv($line), $lines);

        $sections = [];
        $currentTitle = 'Report';
        $headers = [];
        $dataRows = [];

        foreach ($rows as $row) {
            if ($row === [null] || $row === []) {
                if ($headers !== [] || $dataRows !== []) {
                    $sections[] = [
                        'title' => $currentTitle,
                        'headers' => $headers,
                        'rows' => $dataRows,
                    ];
                    $headers = [];
                    $dataRows = [];
                }
                continue;
            }

            if (count($row) === 1 && $row[0] !== null && $row[0] !== '') {
                if ($headers !== [] || $dataRows !== []) {
                    $sections[] = [
                        'title' => $currentTitle,
                        'headers' => $headers,
                        'rows' => $dataRows,
                    ];
                    $headers = [];
                    $dataRows = [];
                }
                $currentTitle = (string) $row[0];
                continue;
            }

            if ($headers === []) {
                $headers = array_map(fn ($v) => (string) $v, $row);
                continue;
            }

            $dataRows[] = array_map(fn ($v) => (string) $v, $row);
        }

        if ($headers !== [] || $dataRows !== []) {
            $sections[] = [
                'title' => $currentTitle,
                'headers' => $headers,
                'rows' => $dataRows,
            ];
        }

        return $sections;
    }
}
