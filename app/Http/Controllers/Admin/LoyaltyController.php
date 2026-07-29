<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Loyalty\AdminLoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly AdminLoyaltyService $loyalty
    ) {
    }

    public function index(): View
    {
        return view('admin.loyalty.index', [
            'dashboard' => $this->loyalty->dashboard(),
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('loyalty_system_enabled');
        $this->loyalty->toggleSystem($enabled);

        return back()->with('success', 'Loyalty system '.($enabled ? 'enabled' : 'disabled').'.');
    }

    public function settings(): View
    {
        $clients = User::query()->where('role', 'client')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.loyalty.settings', [
            'settings' => $this->loyalty->getSettings(),
            'clients' => $clients,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loyalty_system_enabled' => 'sometimes|boolean',
            'points_per_aed' => 'required|integer|min:0|max:1000',
            'eligible_activities' => 'sometimes|array',
            'points_expiry_months' => 'nullable|integer|min:1|max:120',
            'rewards_expiry_months' => 'nullable|integer|min:1|max:120',
            'cities' => 'nullable|string|max:500',
            'customer_targeting' => 'required|in:all,specific',
            'specific_customer_ids' => 'nullable|array',
            'specific_customer_ids.*' => 'integer|exists:users,id,role,client',
            'campaign_periods_only' => 'sometimes|boolean',
        ]);

        $activities = [
            'shop_orders' => $request->boolean('eligible_activities.shop_orders'),
            'service_orders' => $request->boolean('eligible_activities.service_orders'),
            'memberships' => $request->boolean('eligible_activities.memberships'),
            'referrals' => $request->boolean('eligible_activities.referrals'),
            'reviews' => $request->boolean('eligible_activities.reviews'),
        ];
        $validated['eligible_activities'] = $activities;
        $validated['loyalty_system_enabled'] = $request->boolean('loyalty_system_enabled');
        $validated['campaign_periods_only'] = $request->boolean('campaign_periods_only');
        $validated['specific_customer_ids'] = array_values(array_map('intval', (array) $request->input('specific_customer_ids', [])));

        $this->loyalty->saveSettings($validated);

        return redirect()->route('admin.loyalty.settings')->with('success', 'Loyalty settings saved.');
    }

    public function rewards(): View
    {
        return view('admin.loyalty.rewards.index', $this->loyalty->rewardsIndex());
    }

    public function createReward(): View
    {
        $clients = User::query()->where('role', 'client')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.loyalty.rewards.form', [
            'reward' => null,
            'clients' => $clients,
        ]);
    }

    public function storeReward(Request $request): RedirectResponse
    {
        $data = $this->validatedReward($request);
        $this->loyalty->createReward($data);

        return redirect()->route('admin.loyalty.rewards')->with('success', 'Reward created.');
    }

    public function editReward(int $id): View
    {
        $reward = LoyaltyReward::query()->findOrFail($id);
        $clients = User::query()->where('role', 'client')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.loyalty.rewards.form', [
            'reward' => $reward,
            'clients' => $clients,
        ]);
    }

    public function updateReward(Request $request, int $id): RedirectResponse
    {
        $reward = LoyaltyReward::query()->findOrFail($id);
        $this->loyalty->updateReward($reward, $this->validatedReward($request));

        return redirect()->route('admin.loyalty.rewards')->with('success', 'Reward updated.');
    }

    public function toggleReward(Request $request, int $id): RedirectResponse
    {
        $reward = LoyaltyReward::query()->findOrFail($id);
        $this->loyalty->toggleReward($reward, $request->has('is_active') ? $request->boolean('is_active') : null);

        return back()->with('success', 'Reward status updated.');
    }

    public function destroyReward(int $id): RedirectResponse
    {
        $this->loyalty->deleteReward(LoyaltyReward::query()->findOrFail($id));

        return redirect()->route('admin.loyalty.rewards')->with('success', 'Reward deleted.');
    }

    public function customers(Request $request): View
    {
        $payload = $this->loyalty->customersIndex($request->query('search'));

        return view('admin.loyalty.customers.index', $payload + [
            'search' => (string) $request->query('search', ''),
        ]);
    }

    public function customerShow(int $id): View
    {
        $customer = User::query()->where('role', 'client')->findOrFail($id);

        return view('admin.loyalty.customers.show', [
            'customer' => $customer,
            'points' => $this->loyalty->customerPoints($customer),
        ]);
    }

    public function customerAdjust(Request $request, int $id): RedirectResponse
    {
        $customer = User::query()->where('role', 'client')->findOrFail($id);
        $validated = $request->validate([
            'amount' => 'required|integer|not_in:0',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $this->loyalty->adjustCustomerPoints($customer, (int) $validated['amount'], $validated['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Points adjusted.');
    }

    public function campaigns(): View
    {
        return view('admin.loyalty.campaigns.index', $this->loyalty->campaignsIndex());
    }

    public function createCampaign(): View
    {
        $clients = User::query()->where('role', 'client')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.loyalty.campaigns.form', [
            'campaign' => null,
            'clients' => $clients,
        ]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $this->loyalty->createCampaign($this->validatedCampaign($request));

        return redirect()->route('admin.loyalty.campaigns')->with('success', 'Campaign created.');
    }

    public function editCampaign(int $id): View
    {
        $campaign = LoyaltyCampaign::query()->findOrFail($id);
        $clients = User::query()->where('role', 'client')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.loyalty.campaigns.form', [
            'campaign' => $campaign,
            'clients' => $clients,
        ]);
    }

    public function updateCampaign(Request $request, int $id): RedirectResponse
    {
        $campaign = LoyaltyCampaign::query()->findOrFail($id);
        $this->loyalty->updateCampaign($campaign, $this->validatedCampaign($request));

        return redirect()->route('admin.loyalty.campaigns')->with('success', 'Campaign updated.');
    }

    public function toggleCampaign(Request $request, int $id): RedirectResponse
    {
        $campaign = LoyaltyCampaign::query()->findOrFail($id);
        $this->loyalty->toggleCampaign($campaign, $request->has('is_enabled') ? $request->boolean('is_enabled') : null);

        return back()->with('success', 'Campaign status updated.');
    }

    public function destroyCampaign(int $id): RedirectResponse
    {
        $this->loyalty->deleteCampaign(LoyaltyCampaign::query()->findOrFail($id));

        return redirect()->route('admin.loyalty.campaigns')->with('success', 'Campaign deleted.');
    }

    public function reports(Request $request): View
    {
        $filters = [
            'customer_scope' => $request->query('customer_scope', 'all'),
            'specific_customer_ids' => array_values(array_map('intval', (array) $request->query('specific_customer_ids', []))),
            'period' => $request->query('period', 'month'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $clients = User::query()->where('role', 'client')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.loyalty.reports', [
            'report' => $this->loyalty->reports($filters),
            'clients' => $clients,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'customer_scope' => $request->query('customer_scope', 'all'),
            'specific_customer_ids' => array_values(array_map('intval', (array) $request->query('specific_customer_ids', []))),
            'period' => $request->query('period', 'month'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

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

    private function validatedReward(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:500',
            'points_required' => 'required|integer|min:1|max:1000000',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date_format:Y-m-d',
            'cities' => 'nullable|string|max:500',
            'customer_targeting' => 'required|in:all,specific',
            'specific_customer_ids' => 'nullable|array',
            'specific_customer_ids.*' => 'integer|exists:users,id,role,client',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['specific_customer_ids'] = array_values(array_map('intval', (array) $request->input('specific_customer_ids', [])));

        return $data;
    }

    private function validatedCampaign(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'multiplier' => 'required|numeric|min:1|max:10',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'cities' => 'nullable|string|max:500',
            'customer_targeting' => 'required|in:all,specific',
            'specific_customer_ids' => 'nullable|array',
            'specific_customer_ids.*' => 'integer|exists:users,id,role,client',
            'notes' => 'nullable|string|max:2000',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $data['eligible_activities'] = [
            'shop_orders' => $request->boolean('eligible_activities.shop_orders'),
            'service_orders' => $request->boolean('eligible_activities.service_orders'),
            'memberships' => $request->boolean('eligible_activities.memberships'),
            'referrals' => $request->boolean('eligible_activities.referrals'),
            'reviews' => $request->boolean('eligible_activities.reviews'),
        ];
        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['specific_customer_ids'] = array_values(array_map('intval', (array) $request->input('specific_customer_ids', [])));

        return $data;
    }
}
