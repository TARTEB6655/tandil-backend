<?php

namespace App\Console\Commands;

use App\Services\OrderExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOrdersToSupplier extends Command
{
    protected $signature = 'orders:send-to-supplier
                            {--days=7 : Number of days to include (from today backwards)}
                            {--email= : Override supplier email (default from config)}';

    protected $description = 'Export orders for the last N days and send by email to the supplier (for cron/scheduled run)';

    public function handle(OrderExportService $exportService): int
    {
        $days = (int) $this->option('days');
        $days = $days > 0 ? $days : 7;
        $dateTo = now()->format('Y-m-d');
        $dateFrom = now()->subDays($days)->format('Y-m-d');

        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
        $query = $exportService->getQuery($filters);
        $rows = $exportService->buildRows($query);

        $csv = $this->arrayToCsv($rows);
        $filename = 'orders_' . now()->format('Y-m-d_His') . '.csv';
        $to = $this->option('email') ?: config('mail.supplier_email', config('mail.from.address'));

        if (empty($to)) {
            $this->error('No supplier email configured. Set MAIL_SUPPLIER_EMAIL or mail.from.address.');

            return 1;
        }

        try {
            Mail::raw('Scheduled orders export for ' . $dateFrom . ' to ' . $dateTo . '.', function ($message) use ($to, $filename, $csv, $dateFrom, $dateTo) {
                $message->to($to)
                    ->subject('Orders Export (Scheduled) ' . $dateFrom . ' - ' . $dateTo)
                    ->attachData($csv, $filename, ['mime' => 'text/csv']);
            });
        } catch (\Throwable $e) {
            $this->error('Failed to send email: ' . $e->getMessage());

            return 1;
        }

        $this->info('Orders export sent to ' . $to . ' (' . (count($rows) - 1) . ' orders).');

        return 0;
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
