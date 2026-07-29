<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Loyalty\AdminLoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoyaltyAdminApiController extends Controller
{
    public function __construct(
        private readonly AdminLoyaltyService $loyalty
    ) {
        $this->middleware(['auth:sanctum', 'role:admin']);
    }

    /** GET /api/admin/loyalty */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success('Loyalty control center retrieved.', $this->loyalty->dashboard());
    }

    /** PUT /api/admin/loyalty/toggle */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loyalty_system_enabled' => 'required|boolean',
        ]);

        return ApiResponse::success(
            'Loyalty system updated.',
            $this->loyalty->toggleSystem((bool) $validated['loyalty_system_enabled'])
        );
    }

    /** GET /api/admin/loyalty/settings */
    public function settings(): JsonResponse
    {
        return ApiResponse::success('Loyalty settings retrieved.', $this->loyalty->getSettings());
    }

    /** PUT /api/admin/loyalty/settings */
    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loyalty_system_enabled' => 'sometimes|boolean',
            'points_per_aed' => 'sometimes|integer|min:0|max:1000',
            'eligible_activities' => 'sometimes|array',
            'eligible_activities.shop_orders' => 'sometimes|boolean',
            'eligible_activities.service_orders' => 'sometimes|boolean',
            'eligible_activities.memberships' => 'sometimes|boolean',
            'eligible_activities.referrals' => 'sometimes|boolean',
            'eligible_activities.reviews' => 'sometimes|boolean',
            'points_expiry_months' => 'nullable|integer|min:1|max:120',
            'rewards_expiry_months' => 'nullable|integer|min:1|max:120',
            'cities' => 'nullable|string|max:500',
            'customer_targeting' => 'sometimes|in:all,specific',
            'specific_customer_ids' => 'nullable|array',
            'specific_customer_ids.*' => 'integer|exists:users,id,role,client',
            'campaign_periods_only' => 'sometimes|boolean',
        ]);

        return ApiResponse::success(
            'Loyalty settings saved.',
            $this->loyalty->saveSettings($validated)
        );
    }

    /** GET /api/admin/loyalty/rewards */
    public function rewards(): JsonResponse
    {
        return ApiResponse::success('Rewards retrieved.', $this->loyalty->rewardsIndex());
    }

    /** POST /api/admin/loyalty/rewards */
    public function storeReward(Request $request): JsonResponse
    {
        $validated = $this->validateReward($request);

        return ApiResponse::success('Reward created.', $this->loyalty->createReward($validated), 201);
    }

    /** PUT /api/admin/loyalty/rewards/{id} */
    public function updateReward(Request $request, int $id): JsonResponse
    {
        $reward = LoyaltyReward::query()->findOrFail($id);
        $validated = $this->validateReward($request, false);

        return ApiResponse::success('Reward updated.', $this->loyalty->updateReward($reward, $validated));
    }

    /** POST /api/admin/loyalty/rewards/{id}/toggle */
    public function toggleReward(Request $request, int $id): JsonResponse
    {
        $reward = LoyaltyReward::query()->findOrFail($id);
        $enabled = $request->has('is_active')
            ? $request->boolean('is_active')
            : null;

        return ApiResponse::success('Reward status updated.', $this->loyalty->toggleReward($reward, $enabled));
    }

    /** DELETE /api/admin/loyalty/rewards/{id} */
    public function destroyReward(int $id): JsonResponse
    {
        $reward = LoyaltyReward::query()->findOrFail($id);
        $this->loyalty->deleteReward($reward);

        return ApiResponse::success('Reward deleted.', []);
    }

    /** GET /api/admin/loyalty/customers */
    public function customers(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Loyalty customers retrieved.',
            $this->loyalty->customersIndex($request->query('search'))
        );
    }

    /** GET /api/admin/loyalty/customers/{id} */
    public function customerShow(int $id): JsonResponse
    {
        $customer = User::query()->where('role', 'client')->findOrFail($id);

        return ApiResponse::success('Customer points retrieved.', $this->loyalty->customerPoints($customer));
    }

    /** POST /api/admin/loyalty/customers/{id}/adjust */
    public function customerAdjust(Request $request, int $id): JsonResponse
    {
        $customer = User::query()->where('role', 'client')->findOrFail($id);
        $validated = $request->validate([
            'amount' => 'required|integer|not_in:0',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $payload = $this->loyalty->adjustCustomerPoints(
                $customer,
                (int) $validated['amount'],
                $validated['reason'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Points adjusted.', $payload);
    }

    /** GET /api/admin/loyalty/campaigns */
    public function campaigns(): JsonResponse
    {
        return ApiResponse::success('Campaigns retrieved.', $this->loyalty->campaignsIndex());
    }

    /** POST /api/admin/loyalty/campaigns */
    public function storeCampaign(Request $request): JsonResponse
    {
        $this->normalizeCampaignAliases($request);
        $validated = $this->validateCampaign($request);

        return ApiResponse::success('Campaign created.', $this->loyalty->createCampaign($validated), 201);
    }

    /** PUT /api/admin/loyalty/campaigns/{id} */
    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        $this->normalizeCampaignAliases($request);
        $campaign = LoyaltyCampaign::query()->findOrFail($id);
        $validated = $this->validateCampaign($request, false);

        return ApiResponse::success('Campaign updated.', $this->loyalty->updateCampaign($campaign, $validated));
    }

    /** POST /api/admin/loyalty/campaigns/{id}/toggle */
    public function toggleCampaign(Request $request, int $id): JsonResponse
    {
        $this->normalizeCampaignAliases($request);
        $campaign = LoyaltyCampaign::query()->findOrFail($id);
        $enabled = $request->has('is_enabled')
            ? $request->boolean('is_enabled')
            : null;

        return ApiResponse::success('Campaign status updated.', $this->loyalty->toggleCampaign($campaign, $enabled));
    }

    /** DELETE /api/admin/loyalty/campaigns/{id} */
    public function destroyCampaign(int $id): JsonResponse
    {
        $campaign = LoyaltyCampaign::query()->findOrFail($id);
        $this->loyalty->deleteCampaign($campaign);

        return ApiResponse::success('Campaign deleted.', []);
    }

    /** GET /api/admin/loyalty/reports — Reports & export screen (filters + summary). */
    public function reports(Request $request): JsonResponse
    {
        $filters = $this->validatedReportFilters($request);

        return ApiResponse::success('Loyalty reports retrieved.', $this->loyalty->reports($filters));
    }

    /**
     * GET /api/admin/loyalty/export — Export PDF (default) matching Reports & export UI.
     * Query same filters as /reports (including period=specific + date_from/date_to).
     * Optional format=csv|json.
     */
    public function export(Request $request): JsonResponse|StreamedResponse|\Illuminate\Http\Response
    {
        $filters = $this->validatedReportFilters($request);
        $format = strtolower((string) $request->query('format', 'pdf'));

        if ($format === 'json') {
            return ApiResponse::success('Loyalty report exported.', $this->loyalty->reports($filters));
        }

        if ($format === 'csv') {
            $csv = $this->loyalty->exportReportCsv($filters);

            return response()->streamDownload(function () use ($csv) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fputcsv($out, $csv['headers']);
                foreach ($csv['rows'] as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $csv['filename'], [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $pdf = $this->loyalty->exportReportPdf($filters);

        return response($pdf['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedReportFilters(Request $request): array
    {
        $ids = $request->input('specific_customer_ids', []);
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)), fn ($v) => $v !== '');
            $request->merge(['specific_customer_ids' => $ids]);
        }

        $validated = $request->validate([
            'customer_scope' => 'sometimes|in:all,specific',
            'specific_customer_ids' => 'nullable|array',
            'specific_customer_ids.*' => 'integer|exists:users,id,role,client',
            'period' => 'sometimes|in:week,month,specific',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        if (($validated['period'] ?? 'month') === 'specific') {
            $request->validate([
                'date_from' => 'required|date_format:Y-m-d',
                'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            ]);
            $validated['date_from'] = $request->input('date_from');
            $validated['date_to'] = $request->input('date_to');
        }

        return $validated;
    }

    private function validateReward(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:160',
            'description' => 'nullable|string|max:500',
            'points_required' => ($creating ? 'required' : 'sometimes').'|integer|min:1|max:1000000',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date_format:Y-m-d',
            'cities' => 'nullable|string|max:500',
            'customer_targeting' => 'sometimes|in:all,specific',
            'specific_customer_ids' => 'nullable|array',
            'specific_customer_ids.*' => 'integer|exists:users,id,role,client',
        ]);
    }

    private function validateCampaign(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:160',
            'multiplier' => ($creating ? 'required' : 'sometimes').'|numeric|min:1|max:10',
            'start_date' => ($creating ? 'required' : 'sometimes').'|date_format:Y-m-d',
            'end_date' => ($creating ? 'required' : 'sometimes').'|date_format:Y-m-d|after_or_equal:start_date',
            'cities' => 'nullable|string|max:500',
            'customer_targeting' => 'sometimes|in:all,specific',
            'specific_customer_ids' => 'nullable|array',
            'specific_customer_ids.*' => 'integer|exists:users,id,role,client',
            'eligible_activities' => 'sometimes|array',
            'eligible_activities.shop_orders' => 'sometimes|boolean',
            'eligible_activities.service_orders' => 'sometimes|boolean',
            'eligible_activities.memberships' => 'sometimes|boolean',
            'eligible_activities.referrals' => 'sometimes|boolean',
            'eligible_activities.reviews' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:2000',
            'is_enabled' => 'sometimes|boolean',
        ]);
    }

    /**
     * RN form uses "Active" toggle / 2x chips; normalize aliases before validation.
     */
    private function normalizeCampaignAliases(Request $request): void
    {
        if (! $request->exists('is_enabled') && $request->exists('is_active')) {
            $request->merge(['is_enabled' => $request->boolean('is_active')]);
        }

        if ($request->exists('multiplier') && is_string($request->input('multiplier'))) {
            $raw = strtolower(trim((string) $request->input('multiplier')));
            $raw = rtrim($raw, 'x');
            if (is_numeric($raw)) {
                $request->merge(['multiplier' => (float) $raw]);
            }
        }
    }
}
