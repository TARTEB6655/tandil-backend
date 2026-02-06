<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\ExclusiveOffer;
use Illuminate\Http\Request;

/**
 * Public API for Exclusive Offers (home screen "Exclusive Offers" section).
 * No authentication required. Returns active, currently valid offers.
 */
class ExclusiveOfferController extends Controller
{
    /**
     * GET /api/exclusive-offers
     * List active exclusive offers (within date range). For "View All" and home carousel.
     */
    public function index(Request $request)
    {
        $query = ExclusiveOffer::query()
            ->active()
            ->current()
            ->ordered();

        $perPage = min((int) $request->query('per_page', 20), 50);
        $offers = $query->paginate($perPage);

        $data = $offers->getCollection()->map(fn ($offer) => $this->offerToArray($offer));
        $offers->setCollection($data);

        return ApiResponse::success('Exclusive offers retrieved successfully.', [
            'data' => $offers->items(),
            'pagination' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }

    /**
     * GET /api/exclusive-offers/{id}
     * Get a single offer by ID (only if active and current).
     */
    public function show($id)
    {
        $offer = ExclusiveOffer::query()
            ->active()
            ->current()
            ->find($id);

        if (! $offer) {
            return ApiResponse::error('Offer not found or no longer available.', 404);
        }

        return ApiResponse::success('Exclusive offer retrieved successfully.', $this->offerToArray($offer));
    }

    private function offerToArray(ExclusiveOffer $offer): array
    {
        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'description' => $offer->description,
            'image_url' => $offer->image_url,
            'discount_type' => $offer->discount_type,
            'discount_value' => $offer->discount_value !== null ? (float) $offer->discount_value : null,
            'applies_to' => $offer->applies_to,
            'start_date' => $offer->start_date?->format('Y-m-d'),
            'end_date' => $offer->end_date?->format('Y-m-d'),
            'sort_order' => $offer->sort_order,
            'created_at' => $offer->created_at?->format('c'),
            'updated_at' => $offer->updated_at?->format('c'),
        ];
    }
}
