<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Services\OrderExportService;
use App\Services\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderExportController extends Controller
{
    public function __construct(
        protected OrderExportService $exportService,
        protected SimpleXlsxWriter $xlsxWriter
    ) {}

    /**
     * Export orders to CSV or Excel. Query: date_from, date_to, order_status, payment_status, package_id, format=csv|xlsx.
     */
    public function export(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $filters = $this->exportService->filtersFromRequest($request);
        $query = $this->exportService->getQuery($filters);
        $format = strtolower($request->input('format', 'csv'));

        if ($format === 'xlsx') {
            $rows = $this->exportService->buildRows($query);
            $filename = 'orders_' . now()->format('Y-m-d_His') . '.xlsx';
            $content = $this->xlsxWriter->generate($rows);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        $filename = 'orders_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $rows = $this->exportService->buildRows($query);
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generate export and send by email to supplier. Body: email (optional), date_from, date_to, package_id.
     */
    public function sendToSupplier(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'package_id' => 'nullable|integer|exists:packages,id',
        ]);

        $filters = $this->exportService->filtersFromRequest($request);
        $query = $this->exportService->getQuery($filters);
        $rows = $this->exportService->buildRows($query);

        $csv = $this->arrayToCsv($rows);
        $filename = 'orders_' . now()->format('Y-m-d_His') . '.csv';
        $to = $request->input('email') ?: config('mail.supplier_email', config('mail.from.address'));

        try {
            Mail::raw('Please find the orders export attached.', function ($message) use ($to, $filename, $csv) {
                $message->to($to)
                    ->subject('Orders Export - ' . now()->format('Y-m-d H:i'))
                    ->attachData($csv, $filename, ['mime' => 'text/csv']);
            });
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to send email: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Orders export sent to ' . $to . '.', ['email' => $to, 'filename' => $filename]);
    }

    private function arrayToCsv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
