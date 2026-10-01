<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Notifications\AdminNotification;
use App\Services\VisitPhotoService;

final class OrderClientReportService
{
  /** @var list<string> */
    public const CLIENT_VISIBLE_REPORT_STATUSES = ['sent_to_client'];

    public function findVisitForOrder(Order $order): ?Visit
    {
        $visit = Visit::query()->where('order_id', $order->id)->first();
        if ($visit) {
            return $visit;
        }

        return Visit::query()
            ->where('notes', 'like', '%[SHOP-ORDER:'.$order->id.']%')
            ->first();
    }

    public function findReportForOrder(Order $order): ?Report
    {
        $visit = $this->findVisitForOrder($order);
        if ($visit === null) {
            return null;
        }

        return Report::query()->where('visit_id', $visit->id)->first();
    }

    public function isReportVisibleToClient(?Report $report): bool
    {
        if ($report === null) {
            return false;
        }

        return in_array((string) $report->status, self::CLIENT_VISIBLE_REPORT_STATUSES, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceReportMetaForOrder(Order $order): array
    {
        $report = $this->findReportForOrder($order);
        $available = $this->isReportVisibleToClient($report);
        $orderStatus = strtolower((string) ($order->order_status ?? 'pending'));

        return [
            'available' => $available,
            'report_id' => $available ? $report->id : null,
            'status' => $report?->status,
            'pending_message' => $available
                ? null
                : 'When your supervisor finalizes the visit report, it will appear here.',
            'can_view_report' => $available,
            'can_mark_delivered' => $available && $orderStatus === 'completed',
        ];
    }

    public function releasePhotosToClient(Visit $visit): void
    {
        VisitPhoto::query()
            ->where('visit_id', $visit->id)
            ->update(['show_on_client_app' => true]);
    }

    public function notifyClientReportReady(Order $order, Report $report): void
    {
        $client = $this->resolveOrderClient($order);
        if ($client === null) {
            return;
        }

        $orderNumber = $order->publicOrderNumberDigits() ?: (string) $order->id;
        $title = 'Service report ready';
        $message = sprintf('Order %s job is complete. Please check the report.', $orderNumber);
        $meta = [
            'type' => 'order_report_ready',
            'order_id' => $order->id,
            'report_id' => $report->id,
            'visit_id' => $report->visit_id,
            'order_number' => $order->publicOrderNumber(),
        ];

        $client->notify(new AdminNotification($title, $message, $meta));
    }

    public function notifySubscriptionClientReportReady(Visit $visit, Report $report): void
    {
        $client = $visit->subscription?->client;
        if (! $client instanceof User) {
            return;
        }

        $title = 'Service report ready';
        $message = 'Your job is complete. Please check the report.';
        $meta = [
            'type' => 'order_report_ready',
            'report_id' => $report->id,
            'visit_id' => $report->visit_id,
        ];

        $client->notify(new AdminNotification($title, $message, $meta));
    }

    public function resolveOrderClient(Order $order): ?User
    {
        if ($order->user_id) {
            return User::query()->find($order->user_id);
        }

        $email = strtolower(trim((string) ($order->guest_email ?? '')));
        if ($email === '') {
            return null;
        }

        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    public function resolveOrderForVisit(Visit $visit): ?Order
    {
        if ($visit->order_id) {
            return Order::query()->find((int) $visit->order_id);
        }

        if (preg_match('/\[SHOP-ORDER:(\d+)\]/', (string) ($visit->notes ?? ''), $matches)) {
            $orderId = (int) ($matches[1] ?? 0);

            return $orderId > 0 ? Order::query()->find($orderId) : null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatReportForClient(Report $report): array
    {
        $report->loadMissing([
            'visit.technician:id,name,email,phone,profile_picture',
            'visit.photos',
            'visit.area:id,name,location',
            'supervisor:id,name,email,phone',
        ]);

        $visit = $report->visit;
        $photoService = app(VisitPhotoService::class);
        $photos = ($visit?->photos ?? collect())
            ->filter(fn ($photo) => (bool) $photo->show_on_client_app)
            ->values();

        $beforePhotos = $photos->where('type', 'before')->map(fn ($photo) => $photoService->toApiItem($photo))->values()->all();
        $afterPhotos = $photos->where('type', 'after')->map(fn ($photo) => $photoService->toApiItem($photo))->values()->all();
        $fieldPhotos = $photos->map(fn ($photo) => $photoService->toApiItem($photo))->values()->all();

        $recommendedProducts = [];
        $productIds = array_values(array_filter(array_map('intval', (array) ($report->recommended_products ?? []))));
        if ($productIds !== []) {
            $recommendedProducts = Product::query()
                ->whereIn('id', $productIds)
                ->get(['id', 'name', 'price', 'job_duration'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'job_duration' => $product->job_duration,
                ])
                ->values()
                ->all();
        }

        $recommendations = array_values(array_filter(
            (array) ($report->recommendations ?? []),
            fn ($item) => is_string($item) && trim($item) !== ''
        ));

        $technician = $visit?->technician;

        return [
            'id' => $report->id,
            'report_id' => $report->id,
            'visit_id' => $report->visit_id,
            'status' => $report->status,
            'technician_notes' => $report->technician_notes,
            'field_notes' => $report->technician_notes,
            'supervisor_notes' => $report->supervisor_notes,
            'notes' => $report->notes,
            'recommendations' => $recommendations,
            'recommended_products' => $recommendedProducts,
            'before_photos' => $beforePhotos,
            'after_photos' => $afterPhotos,
            'field_photos' => $fieldPhotos,
            'submitted_at' => $report->approved_at?->format('c') ?? $report->updated_at?->format('c'),
            'field_worker' => $technician ? [
                'id' => $technician->id,
                'name' => $technician->name,
                'email' => $technician->email,
                'phone' => $technician->phone ?? null,
                'profile_picture_url' => $technician->profile_picture_url ?? null,
            ] : null,
            'supervisor' => $report->supervisor ? [
                'id' => $report->supervisor->id,
                'name' => $report->supervisor->name,
            ] : null,
            'visit_information' => [
                'location' => $visit?->area?->location ?? $visit?->area?->name,
                'service_name' => $this->serviceNameFromVisitNotes((string) ($visit?->notes ?? '')),
                'scheduled_date' => $visit?->scheduled_date,
                'completed_at' => $visit?->completed_at?->format('c'),
            ],
        ];
    }

    private function serviceNameFromVisitNotes(string $notes): ?string
    {
        $parts = array_values(array_filter(array_map('trim', explode('|', $notes)), fn ($part) => $part !== ''));
        $service = $parts[1] ?? $parts[0] ?? null;
        if ($service && preg_match('/^(.+?)\s+Visit\s*$/i', $service, $matches)) {
            return trim($matches[1]);
        }

        return $service;
    }
}
