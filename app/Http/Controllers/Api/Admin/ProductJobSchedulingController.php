<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBlockedDate;
use App\Models\ProductTimeSlot;
use Illuminate\Http\Request;

/**
 * Per-product Job Scheduling (admin folder K extension).
 * Admin configures different date/time slots per product; shop product
 * detail booking uses these when present, else global job_time_slots.
 */
class ProductJobSchedulingController extends Controller
{
    /**
     * GET /api/admin/job-scheduling/products/{productId}/time-slots
     */
    public function listTimeSlots(int $productId)
    {
        $product = Product::find($productId);
        if (! $product) {
            return ApiResponse::error('Product not found.', 404);
        }

        $slots = ProductTimeSlot::query()
            ->where('product_id', $productId)
            ->orderByRaw('date IS NULL DESC')
            ->orderBy('date')
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get();

        return ApiResponse::success('Product time slots retrieved successfully.', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'uses_custom_slots' => $slots->isNotEmpty(),
            'slots' => $slots->map(fn (ProductTimeSlot $s) => $this->timeSlotPayload($s))->values(),
        ]);
    }

    /**
     * POST /api/admin/job-scheduling/products/{productId}/time-slots
     *
     * Body (JSON or form-data):
     * - start_time (required, HH:mm)
     * - duration_minutes (optional, default 60)
     * - date (optional Y-m-d) — omit for recurring every bookable day
     * - is_active (optional, default true)
     */
    public function addTimeSlot(Request $request, int $productId)
    {
        $product = Product::find($productId);
        if (! $product) {
            return ApiResponse::error('Product not found.', 404);
        }

        $request->validate([
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'date' => 'nullable|date_format:Y-m-d',
            'is_active' => 'nullable|boolean',
        ]);

        $date = $request->filled('date') ? $request->input('date') : null;
        $start = $request->input('start_time');

        $exists = ProductTimeSlot::query()
            ->where('product_id', $productId)
            ->where('start_time', $start)
            ->when(
                $date === null,
                fn ($q) => $q->whereNull('date'),
                fn ($q) => $q->whereDate('date', $date)
            )
            ->exists();

        if ($exists) {
            return ApiResponse::error('A time slot already exists at this start time for this product/date.', 422);
        }

        $slot = ProductTimeSlot::create([
            'product_id' => $productId,
            'date' => $date,
            'start_time' => $start,
            'duration_minutes' => (int) $request->input('duration_minutes', 60),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'sort_order' => (int) ProductTimeSlot::where('product_id', $productId)->max('sort_order') + 1,
        ]);

        return ApiResponse::success('Product time slot added successfully.', $this->timeSlotPayload($slot), 201);
    }

    /**
     * POST /api/admin/job-scheduling/products/{productId}/time-slots/{id}/toggle
     */
    public function toggleTimeSlot(int $productId, int $id)
    {
        $slot = ProductTimeSlot::query()
            ->where('product_id', $productId)
            ->whereKey($id)
            ->first();

        if (! $slot) {
            return ApiResponse::error('Product time slot not found.', 404);
        }

        $slot->is_active = ! $slot->is_active;
        $slot->save();

        return ApiResponse::success('Product time slot status updated.', $this->timeSlotPayload($slot));
    }

    /**
     * DELETE /api/admin/job-scheduling/products/{productId}/time-slots/{id}
     */
    public function deleteTimeSlot(int $productId, int $id)
    {
        $slot = ProductTimeSlot::query()
            ->where('product_id', $productId)
            ->whereKey($id)
            ->first();

        if (! $slot) {
            return ApiResponse::error('Product time slot not found.', 404);
        }

        $slot->delete();

        return ApiResponse::success('Product time slot deleted successfully.');
    }

    /**
     * GET /api/admin/job-scheduling/products/{productId}/blocked-dates
     */
    public function listBlockedDates(Request $request, int $productId)
    {
        $product = Product::find($productId);
        if (! $product) {
            return ApiResponse::error('Product not found.', 404);
        }

        $query = ProductBlockedDate::query()
            ->where('product_id', $productId)
            ->orderBy('date', 'desc');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        return ApiResponse::success('Product blocked dates retrieved successfully.', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'blocks' => $query->get()->map(fn (ProductBlockedDate $b) => $this->blockedDatePayload($b))->values(),
        ]);
    }

    /**
     * POST /api/admin/job-scheduling/products/{productId}/blocked-dates
     */
    public function addBlockedDate(Request $request, int $productId)
    {
        $product = Product::find($productId);
        if (! $product) {
            return ApiResponse::error('Product not found.', 404);
        }

        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'block_type' => 'required|string|in:full_day,time_slot',
            'time' => 'required_if:block_type,time_slot|nullable|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ]);

        $block = ProductBlockedDate::create([
            'product_id' => $productId,
            'date' => $request->input('date'),
            'block_type' => $request->input('block_type'),
            'time' => $request->input('block_type') === 'time_slot' ? $request->input('time') : null,
            'reason' => $request->input('reason'),
        ]);

        return ApiResponse::success('Product block added successfully.', $this->blockedDatePayload($block), 201);
    }

    /**
     * DELETE /api/admin/job-scheduling/products/{productId}/blocked-dates/{id}
     */
    public function deleteBlockedDate(int $productId, int $id)
    {
        $block = ProductBlockedDate::query()
            ->where('product_id', $productId)
            ->whereKey($id)
            ->first();

        if (! $block) {
            return ApiResponse::error('Product block not found.', 404);
        }

        $block->delete();

        return ApiResponse::success('Product block deleted successfully.');
    }

    private function timeSlotPayload(ProductTimeSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'product_id' => (int) $slot->product_id,
            'date' => $slot->date?->toDateString(),
            'start_time' => $slot->startTimeHi(),
            'end_time' => $slot->endTime(),
            'duration_minutes' => (int) $slot->duration_minutes,
            'is_active' => (bool) $slot->is_active,
            'sort_order' => (int) $slot->sort_order,
            'recurring' => $slot->date === null,
        ];
    }

    private function blockedDatePayload(ProductBlockedDate $block): array
    {
        return [
            'id' => $block->id,
            'product_id' => (int) $block->product_id,
            'date' => $block->date?->toDateString(),
            'block_type' => $block->block_type,
            'time' => $block->timeHi(),
            'reason' => $block->reason,
        ];
    }
}
