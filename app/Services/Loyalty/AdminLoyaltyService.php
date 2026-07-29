<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserAddress;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AdminLoyaltyService
{
    public const ACTIVITY_KEYS = [
        'shop_orders',
        'service_orders',
        'memberships',
        'referrals',
        'reviews',
    ];

    /** Settings screen checkboxes only (RN Loyalty settings). */
    public const SETTINGS_ACTIVITY_KEYS = [
        'shop_orders',
        'service_orders',
        'memberships',
    ];

    public function dashboard(): array
    {
        $settings = $this->getSettings();
        $enabled = (bool) $settings['loyalty_system_enabled'];

        return [
            'loyalty_system_enabled' => $enabled,
            'status' => $enabled ? 'Active' : 'Inactive',
            'status_label' => $enabled
                ? 'Active — customers can earn and redeem'
                : 'Inactive — customers cannot earn or redeem',
            'points_per_aed' => (int) $settings['points_per_aed'],
            'activities' => (int) $settings['activities_selected'],
            'expiry_months' => $settings['points_expiry_months'],
        ];
    }

    public function toggleSystem(bool $enabled): array
    {
        Setting::set('loyalty_system_enabled', $enabled ? '1' : '0', 'boolean', 'loyalty');
        Setting::set('loyalty_auto_earn_enabled', $enabled ? '1' : '0', 'boolean', 'loyalty');

        return $this->dashboard();
    }

    public function getSettings(): array
    {
        $allActivities = $this->decodeActivities(Setting::get('loyalty_eligible_activities', null));
        $activities = [];
        foreach (self::SETTINGS_ACTIVITY_KEYS as $key) {
            $activities[$key] = (bool) ($allActivities[$key] ?? false);
        }
        $selected = collect($activities)->filter()->count();
        $enabled = Setting::get('loyalty_system_enabled', Setting::get('loyalty_auto_earn_enabled', '1')) !== '0';
        $pointsPerAed = (int) max(0, (float) Setting::get('loyalty_points_per_aed', '1'));
        $pointsExpiry = Setting::get('loyalty_points_expiry_months', '12');
        $rewardsExpiry = Setting::get('loyalty_rewards_expiry_months', '6');

        $targeting = (string) Setting::get('loyalty_customer_targeting', 'all');
        $targeting = $targeting === 'specific' ? 'specific' : 'all';
        $specificIds = $this->decodeIdList(Setting::get('loyalty_specific_customer_ids', '[]'));
        if ($targeting !== 'specific') {
            $specificIds = [];
        }
        $specificCustomers = $this->customerNameList($specificIds);

        return [
            'loyalty_system_enabled' => $enabled,
            'points_per_aed' => $pointsPerAed,
            'eligible_activities' => $activities,
            'points_expiry_months' => $pointsExpiry === '' || $pointsExpiry === null ? null : (int) $pointsExpiry,
            'rewards_expiry_months' => $rewardsExpiry === '' || $rewardsExpiry === null ? null : (int) $rewardsExpiry,
            'cities' => (string) Setting::get('loyalty_cities', ''),
            'customer_targeting' => $targeting,
            'customer_targeting_label' => $targeting === 'specific' ? 'Specific customer' : 'All customers',
            'specific_customer_ids' => $specificIds,
            'specific_customers' => $specificCustomers,
            'campaign_periods_only' => Setting::get('loyalty_campaign_periods_only', '0') === '1',
            'activities_selected' => $selected,
            'status' => $enabled ? 'Live' : 'Off',
        ];
    }

    public function saveSettings(array $input): array
    {
        if (array_key_exists('loyalty_system_enabled', $input)) {
            $enabled = filter_var($input['loyalty_system_enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $enabled = $enabled ?? (bool) $input['loyalty_system_enabled'];
            Setting::set('loyalty_system_enabled', $enabled ? '1' : '0', 'boolean', 'loyalty');
            Setting::set('loyalty_auto_earn_enabled', $enabled ? '1' : '0', 'boolean', 'loyalty');
        }

        if (array_key_exists('points_per_aed', $input)) {
            Setting::set('loyalty_points_per_aed', (string) max(0, (int) $input['points_per_aed']), 'number', 'loyalty');
        }

        if (array_key_exists('eligible_activities', $input) && is_array($input['eligible_activities'])) {
            $normalized = $this->normalizeActivities($input['eligible_activities']);
            // Persist full key set; settings UI only sends the three core activities.
            Setting::set('loyalty_eligible_activities', json_encode($normalized), 'json', 'loyalty');
        }

        if (array_key_exists('points_expiry_months', $input)) {
            $val = $input['points_expiry_months'];
            Setting::set(
                'loyalty_points_expiry_months',
                ($val === null || $val === '') ? '' : (string) (int) $val,
                'number',
                'loyalty'
            );
        }

        if (array_key_exists('rewards_expiry_months', $input)) {
            $val = $input['rewards_expiry_months'];
            Setting::set(
                'loyalty_rewards_expiry_months',
                ($val === null || $val === '') ? '' : (string) (int) $val,
                'number',
                'loyalty'
            );
        }

        if (array_key_exists('cities', $input)) {
            Setting::set('loyalty_cities', trim((string) $input['cities']), 'text', 'loyalty');
        }

        if (array_key_exists('customer_targeting', $input)) {
            $targeting = $input['customer_targeting'] === 'specific' ? 'specific' : 'all';
            Setting::set('loyalty_customer_targeting', $targeting, 'text', 'loyalty');
        }

        $targetingNow = (string) Setting::get('loyalty_customer_targeting', 'all');
        if (array_key_exists('specific_customer_ids', $input) || array_key_exists('customer_targeting', $input)) {
            $ids = array_key_exists('specific_customer_ids', $input)
                ? array_values(array_map('intval', (array) $input['specific_customer_ids']))
                : $this->decodeIdList(Setting::get('loyalty_specific_customer_ids', '[]'));

            if ($targetingNow !== 'specific') {
                $ids = [];
            }

            Setting::set('loyalty_specific_customer_ids', json_encode($ids), 'json', 'loyalty');
        }

        if (array_key_exists('campaign_periods_only', $input)) {
            $only = filter_var($input['campaign_periods_only'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $only = $only ?? (bool) $input['campaign_periods_only'];
            Setting::set('loyalty_campaign_periods_only', $only ? '1' : '0', 'boolean', 'loyalty');
        }

        return $this->getSettings();
    }

    public function rewardsIndex(): array
    {
        $rewards = LoyaltyReward::query()->orderBy('sort_order')->orderBy('id')->get();
        $active = $rewards->where('is_active', true);
        $startsAt = $active->min('points_required');

        return [
            'summary' => [
                'total' => $rewards->count(),
                'active' => $active->count(),
                'starts_at' => $startsAt !== null ? (int) $startsAt : 0,
            ],
            'rewards' => $rewards->map(fn (LoyaltyReward $r) => $this->formatRewardAdmin($r))->values()->all(),
        ];
    }

    public function createReward(array $data): array
    {
        $reward = LoyaltyReward::query()->create($this->rewardAttributes($data));

        return $this->formatRewardAdmin($reward);
    }

    public function updateReward(LoyaltyReward $reward, array $data): array
    {
        $reward->fill($this->rewardAttributes($data, $reward));
        $reward->save();

        return $this->formatRewardAdmin($reward->fresh());
    }

    public function toggleReward(LoyaltyReward $reward, ?bool $enabled = null): array
    {
        $reward->is_active = $enabled ?? ! $reward->is_active;
        $reward->save();

        return $this->formatRewardAdmin($reward);
    }

    public function deleteReward(LoyaltyReward $reward): void
    {
        $reward->delete();
    }

    public function customersIndex(?string $search = null): array
    {
        $query = User::query()->where('role', 'client');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        $customers = $query->orderByDesc('loyalty_points_balance')->orderBy('name')->limit(200)->get();
        $pointsPool = (int) $customers->sum(fn ($u) => (int) ($u->loyalty_points_balance ?? 0));
        $holders = $customers->filter(fn ($u) => (int) ($u->loyalty_points_balance ?? 0) > 0)->count();

        return [
            'summary' => [
                'visible' => $customers->count(),
                'points_pool' => $pointsPool,
                'holders' => $holders,
            ],
            'customers' => $customers->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'city' => $this->customerCity($u),
                'points' => (int) ($u->loyalty_points_balance ?? 0),
            ])->values()->all(),
        ];
    }

    public function customerPoints(User $customer): array
    {
        $earned = (int) LoyaltyTransaction::query()
            ->where('user_id', $customer->id)
            ->where('type', LoyaltyTransaction::TYPE_EARN)
            ->sum('points');
        $redeemed = (int) LoyaltyTransaction::query()
            ->where('user_id', $customer->id)
            ->where('type', LoyaltyTransaction::TYPE_REDEEM)
            ->sum('points');

        $history = LoyaltyTransaction::query()
            ->where('user_id', $customer->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (LoyaltyTransaction $tx) => $this->formatHistoryItem($tx))
            ->values()
            ->all();

        return [
            'name' => $customer->name,
            'email' => $customer->email,
            'city' => $this->customerCity($customer),
            'balance' => (int) ($customer->loyalty_points_balance ?? 0),
            'earned' => $earned,
            'redeemed' => $redeemed,
            'history' => $history,
        ];
    }

    public function adjustCustomerPoints(User $customer, int $amount, ?string $reason = null): array
    {
        if ($amount === 0) {
            throw new InvalidArgumentException('Amount must not be zero.');
        }

        return DB::transaction(function () use ($customer, $amount, $reason) {
            /** @var User $locked */
            $locked = User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $balance = (int) ($locked->loyalty_points_balance ?? 0);
            $newBalance = $balance + $amount;

            if ($newBalance < 0) {
                throw new InvalidArgumentException('Insufficient points balance for this deduction.');
            }

            $locked->loyalty_points_balance = $newBalance;
            $locked->save();

            $note = trim((string) $reason);
            if ($amount > 0) {
                LoyaltyTransaction::query()->create([
                    'user_id' => $locked->id,
                    'type' => LoyaltyTransaction::TYPE_EARN,
                    'title' => $note !== '' ? $note : 'Manual credit',
                    'points' => abs($amount),
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'transaction_date' => now()->toDateString(),
                ]);
            } else {
                LoyaltyTransaction::query()->create([
                    'user_id' => $locked->id,
                    'type' => LoyaltyTransaction::TYPE_REDEEM,
                    'title' => $note !== '' ? $note : 'Manual deduction',
                    'points' => abs($amount),
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'transaction_date' => now()->toDateString(),
                ]);
            }

            return $this->customerPoints($locked->fresh());
        });
    }

    public function campaignsIndex(): array
    {
        $campaigns = LoyaltyCampaign::query()->orderByDesc('id')->get();
        $live = $campaigns->filter(fn (LoyaltyCampaign $c) => $c->isLive());
        $topBoost = $campaigns->max('multiplier');

        return [
            'summary' => [
                'total' => $campaigns->count(),
                'live' => $live->count(),
                'top_boost' => $topBoost ? rtrim(rtrim(number_format((float) $topBoost, 2, '.', ''), '0'), '.').'x' : '—',
            ],
            'campaigns' => $campaigns->map(fn (LoyaltyCampaign $c) => $this->formatCampaign($c))->values()->all(),
        ];
    }

    public function createCampaign(array $data): array
    {
        $campaign = LoyaltyCampaign::query()->create($this->campaignAttributes($data));

        return $this->formatCampaign($campaign);
    }

    public function updateCampaign(LoyaltyCampaign $campaign, array $data): array
    {
        $campaign->fill($this->campaignAttributes($data, $campaign));
        $campaign->save();

        return $this->formatCampaign($campaign->fresh());
    }

    public function toggleCampaign(LoyaltyCampaign $campaign, ?bool $enabled = null): array
    {
        $campaign->is_enabled = $enabled ?? ! $campaign->is_enabled;
        $campaign->save();

        return $this->formatCampaign($campaign);
    }

    public function deleteCampaign(LoyaltyCampaign $campaign): void
    {
        $campaign->delete();
    }

    public function exportReport(array $filters = []): array
    {
        return $this->reports($filters);
    }

    /**
     * Reports & export screen (matches RN).
     *
     * Filters:
     * - customer_scope: all|specific
     * - specific_customer_ids: int[]
     * - period: week|month|specific
     * - date_from / date_to: Y-m-d (required when period=specific)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function reports(array $filters = []): array
    {
        $normalized = $this->normalizeReportFilters($filters);
        $customerIds = $this->reportCustomerIds($normalized);
        $summary = $this->reportSummary($customerIds, $normalized['date_from'], $normalized['date_to']);
        $health = $this->reportHealth($customerIds);

        return [
            'health' => $health,
            'filters' => [
                'customer_scope' => $normalized['customer_scope'],
                'specific_customer_ids' => $normalized['specific_customer_ids'],
                'specific_customers' => $this->customerNameList($normalized['specific_customer_ids']),
                'period' => $normalized['period'],
                'date_from' => $normalized['date_from'],
                'date_to' => $normalized['date_to'],
            ],
            'summary' => $summary,
            'export' => [
                'format' => 'pdf',
                'ready' => true,
                'label' => 'Export PDF',
            ],
        ];
    }

    /**
     * Build PDF binary for offline analysis (same filters as reports).
     *
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, binary: string}
     */
    public function exportReportPdf(array $filters = []): array
    {
        $report = $this->reports($filters);
        $customers = $this->reportCustomerDetailRows($filters);
        $filename = 'loyalty-report-'.$report['filters']['period'].'-'.now()->format('Ymd-His').'.pdf';

        $binary = Pdf::loadView('admin.loyalty.reports-pdf', [
            'report' => $report,
            'customers' => $customers,
            'generatedAt' => now()->format('d M Y, H:i'),
        ])->setPaper('a4', 'portrait')->output();

        return [
            'filename' => $filename,
            'binary' => $binary,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function reportCustomerDetailRows(array $filters): array
    {
        $normalized = $this->normalizeReportFilters($filters);
        $customerIds = $this->reportCustomerIds($normalized);
        $from = $normalized['date_from'];
        $to = $normalized['date_to'];

        $clientsQuery = User::query()->where('role', 'client')->orderBy('name');
        if ($customerIds !== null) {
            $clientsQuery->whereIn('id', $customerIds);
        }
        $clients = $clientsQuery->get(['id', 'name', 'email', 'loyalty_points_balance']);

        $rows = [];
        foreach ($clients as $client) {
            $earned = (int) LoyaltyTransaction::query()
                ->where('user_id', $client->id)
                ->where('type', LoyaltyTransaction::TYPE_EARN)
                ->whereDate('transaction_date', '>=', $from)
                ->whereDate('transaction_date', '<=', $to)
                ->sum('points');
            $redeemed = (int) LoyaltyTransaction::query()
                ->where('user_id', $client->id)
                ->where('type', LoyaltyTransaction::TYPE_REDEEM)
                ->whereDate('transaction_date', '>=', $from)
                ->whereDate('transaction_date', '<=', $to)
                ->sum('points');
            $rewardsRedeemed = (int) LoyaltyTransaction::query()
                ->where('user_id', $client->id)
                ->where('type', LoyaltyTransaction::TYPE_REDEEM)
                ->whereNotNull('loyalty_reward_id')
                ->whereDate('transaction_date', '>=', $from)
                ->whereDate('transaction_date', '<=', $to)
                ->count();

            $rows[] = [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'points_balance' => (int) ($client->loyalty_points_balance ?? 0),
                'points_earned' => $earned,
                'points_redeemed' => $redeemed,
                'rewards_redeemed' => $rewardsRedeemed,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{customer_scope: string, specific_customer_ids: array<int, int>, period: string, date_from: string, date_to: string}
     */
    private function normalizeReportFilters(array $filters): array
    {
        $scope = (($filters['customer_scope'] ?? 'all') === 'specific') ? 'specific' : 'all';
        $ids = array_values(array_unique(array_map('intval', (array) ($filters['specific_customer_ids'] ?? []))));
        if ($scope !== 'specific') {
            $ids = [];
        }

        $period = (string) ($filters['period'] ?? 'month');
        if (! in_array($period, ['week', 'month', 'specific'], true)) {
            $period = 'month';
        }

        $today = now()->toDateString();
        if ($period === 'week') {
            $from = now()->subDays(6)->toDateString();
            $to = $today;
        } elseif ($period === 'specific') {
            $from = (string) ($filters['date_from'] ?? now()->startOfMonth()->toDateString());
            $to = (string) ($filters['date_to'] ?? $today);
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
        } else {
            $from = now()->startOfMonth()->toDateString();
            $to = $today;
        }

        return [
            'customer_scope' => $scope,
            'specific_customer_ids' => $ids,
            'period' => $period,
            'date_from' => $from,
            'date_to' => $to,
        ];
    }

    /**
     * @param  array{customer_scope: string, specific_customer_ids: array<int, int>}  $normalized
     * @return array<int, int>|null null means all clients
     */
    private function reportCustomerIds(array $normalized): ?array
    {
        if ($normalized['customer_scope'] !== 'specific') {
            return null;
        }

        return $normalized['specific_customer_ids'];
    }

    /**
     * @param  array<int, int>|null  $customerIds
     * @return array<string, mixed>
     */
    private function reportHealth(?array $customerIds): array
    {
        $clients = User::query()->where('role', 'client');
        if ($customerIds !== null) {
            $clients->whereIn('id', $customerIds);
        }

        $outstanding = (int) (clone $clients)->sum('loyalty_points_balance');
        $rewardsRedeemed = LoyaltyTransaction::query()
            ->where('type', LoyaltyTransaction::TYPE_REDEEM)
            ->whereNotNull('loyalty_reward_id');
        if ($customerIds !== null) {
            $rewardsRedeemed->whereIn('user_id', $customerIds);
        }

        $activeCampaigns = LoyaltyCampaign::query()->get()->filter(fn (LoyaltyCampaign $c) => $c->isLive())->count();

        return [
            'outstanding' => $outstanding,
            'redeemed' => (int) $rewardsRedeemed->count(),
            'campaigns' => $activeCampaigns,
            'export_ready' => true,
            'status_label' => 'Export ready',
        ];
    }

    /**
     * @param  array<int, int>|null  $customerIds
     * @return array<string, int>
     */
    private function reportSummary(?array $customerIds, string $from, string $to): array
    {
        $clients = User::query()->where('role', 'client');
        if ($customerIds !== null) {
            $clients->whereIn('id', $customerIds);
        }

        $customersWithPoints = (int) (clone $clients)->where('loyalty_points_balance', '>', 0)->count();
        $pointsOutstanding = (int) (clone $clients)->sum('loyalty_points_balance');

        $txBase = LoyaltyTransaction::query()
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to);
        if ($customerIds !== null) {
            $txBase->whereIn('user_id', $customerIds);
        }

        $pointsEarned = (int) (clone $txBase)->where('type', LoyaltyTransaction::TYPE_EARN)->sum('points');
        $pointsRedeemed = (int) (clone $txBase)->where('type', LoyaltyTransaction::TYPE_REDEEM)->sum('points');
        $rewardsRedeemed = (int) (clone $txBase)
            ->where('type', LoyaltyTransaction::TYPE_REDEEM)
            ->whereNotNull('loyalty_reward_id')
            ->count();

        $activeCampaigns = LoyaltyCampaign::query()->get()->filter(fn (LoyaltyCampaign $c) => $c->isLive())->count();

        return [
            'customers_with_points' => $customersWithPoints,
            'points_outstanding' => $pointsOutstanding,
            'points_earned' => $pointsEarned,
            'points_redeemed' => $pointsRedeemed,
            'rewards_redeemed' => $rewardsRedeemed,
            'active_campaigns' => $activeCampaigns,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function decodeActivities(mixed $raw): array
    {
        $defaults = [
            'shop_orders' => true,
            'service_orders' => true,
            'memberships' => true,
            'referrals' => false,
            'reviews' => false,
        ];

        if ($raw === null || $raw === '') {
            return $defaults;
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        return $this->normalizeActivities($decoded);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    private function normalizeActivities(array $input): array
    {
        $out = [];
        foreach (self::ACTIVITY_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $val = filter_var($input[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $out[$key] = $val ?? (bool) $input[$key];
            } else {
                $out[$key] = in_array($key, $input, true);
            }
        }

        // If input was a list of selected keys only
        if ($this->isList($input)) {
            foreach (self::ACTIVITY_KEYS as $key) {
                $out[$key] = in_array($key, $input, true);
            }
        }

        return $out;
    }

    private function isList(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function rewardAttributes(array $data, ?LoyaltyReward $existing = null): array
    {
        $targeting = ($data['customer_targeting'] ?? $existing?->customer_targeting ?? 'all') === 'specific'
            ? 'specific'
            : 'all';

        return [
            'title' => $data['title'] ?? $existing?->title,
            'description' => $data['description'] ?? $existing?->description,
            'points_required' => (int) ($data['points_required'] ?? $existing?->points_required ?? 0),
            'is_active' => array_key_exists('is_active', $data)
                ? $this->toBool($data['is_active'], true)
                : ($existing?->is_active ?? true),
            'expires_at' => array_key_exists('expires_at', $data)
                ? ($data['expires_at'] ?: null)
                : $existing?->expires_at,
            'cities' => array_key_exists('cities', $data)
                ? (trim((string) $data['cities']) ?: null)
                : $existing?->cities,
            'customer_targeting' => $targeting,
            'specific_customer_ids' => $targeting === 'specific'
                ? array_values(array_map('intval', (array) ($data['specific_customer_ids'] ?? $existing?->specific_customer_ids ?? [])))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRewardAdmin(LoyaltyReward $reward): array
    {
        $cities = trim((string) ($reward->cities ?? ''));
        $targeting = $reward->customer_targeting === 'specific' ? 'specific' : 'all';
        $ids = $targeting === 'specific'
            ? array_values(array_map('intval', (array) ($reward->specific_customer_ids ?? [])))
            : [];

        return [
            'id' => $reward->id,
            'title' => $reward->title,
            'description' => $reward->description,
            'points_required' => (int) $reward->points_required,
            'points_label' => ((int) $reward->points_required).' pts',
            'is_active' => (bool) $reward->is_active,
            'status' => $reward->is_active ? 'Active' : 'Inactive',
            'expires_at' => $reward->expires_at?->format('Y-m-d'),
            'cities' => $cities !== '' ? $cities : 'All cities',
            'customer_targeting' => $targeting,
            'customer_targeting_label' => $targeting === 'specific' ? 'Specific customer' : 'All customers',
            'specific_customer_ids' => $ids,
            'specific_customers' => $this->customerNameList($ids),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function campaignAttributes(array $data, ?LoyaltyCampaign $existing = null): array
    {
        $targeting = ($data['customer_targeting'] ?? $existing?->customer_targeting ?? 'all') === 'specific'
            ? 'specific'
            : 'all';

        $activities = null;
        if (array_key_exists('eligible_activities', $data)) {
            $activities = is_array($data['eligible_activities'])
                ? $this->normalizeActivities($data['eligible_activities'])
                : $this->decodeActivities($data['eligible_activities']);
        } elseif ($existing) {
            $activities = $existing->eligible_activities;
        }

        return [
            'title' => $data['title'] ?? $existing?->title,
            'multiplier' => (float) ($data['multiplier'] ?? $existing?->multiplier ?? 2),
            'start_date' => $data['start_date'] ?? $existing?->start_date?->format('Y-m-d'),
            'end_date' => $data['end_date'] ?? $existing?->end_date?->format('Y-m-d'),
            'cities' => array_key_exists('cities', $data)
                ? (trim((string) $data['cities']) ?: null)
                : $existing?->cities,
            'customer_targeting' => $targeting,
            'specific_customer_ids' => $targeting === 'specific'
                ? array_values(array_map('intval', (array) ($data['specific_customer_ids'] ?? $existing?->specific_customer_ids ?? [])))
                : null,
            'eligible_activities' => $activities,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $existing?->notes,
            'is_enabled' => array_key_exists('is_enabled', $data)
                ? $this->toBool($data['is_enabled'], true)
                : ($existing?->is_enabled ?? true),
        ];
    }

    private function toBool(mixed $value, bool $default = false): bool
    {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCampaign(LoyaltyCampaign $campaign): array
    {
        $cities = trim((string) ($campaign->cities ?? ''));
        $mult = (float) $campaign->multiplier;
        $boost = rtrim(rtrim(number_format($mult, 2, '.', ''), '0'), '.').'x points';
        $targeting = $campaign->customer_targeting === 'specific' ? 'specific' : 'all';
        $ids = $targeting === 'specific'
            ? array_values(array_map('intval', (array) ($campaign->specific_customer_ids ?? [])))
            : [];

        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'multiplier' => $mult,
            'boost_label' => $boost,
            'start_date' => $campaign->start_date?->format('Y-m-d'),
            'end_date' => $campaign->end_date?->format('Y-m-d'),
            'date_range' => ($campaign->start_date?->format('Y-m-d') ?? '').' -> '.($campaign->end_date?->format('Y-m-d') ?? ''),
            'cities' => $cities !== '' ? $cities : 'All cities',
            'customer_targeting' => $targeting,
            'customer_targeting_label' => $targeting === 'specific' ? 'Specific customer' : 'All customers',
            'specific_customer_ids' => $ids,
            'specific_customers' => $this->customerNameList($ids),
            'eligible_activities' => $this->normalizeActivities($campaign->eligible_activities ?? []),
            'notes' => $campaign->notes,
            'is_enabled' => (bool) $campaign->is_enabled,
            'status' => $campaign->isLive() ? 'Active' : ($campaign->is_enabled ? 'Scheduled' : 'Inactive'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHistoryItem(LoyaltyTransaction $tx): array
    {
        $signed = $tx->type === LoyaltyTransaction::TYPE_REDEEM ? -1 * $tx->points : $tx->points;
        $created = $tx->created_at ?? $tx->transaction_date;
        $datetime = $created
            ? $created->timezone(config('app.timezone'))->format('j M Y \a\t g:i A')
            : '';

        return [
            'id' => $tx->id,
            'title' => $tx->title,
            'type' => $tx->type,
            'type_label' => $tx->type === LoyaltyTransaction::TYPE_REDEEM ? 'Redeem' : 'Earn',
            'datetime' => $datetime,
            'points_display' => ($signed > 0 ? '+' : '').$signed,
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<int, int>
     */
    private function decodeIdList(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_map('intval', $raw));
        }

        $decoded = json_decode((string) ($raw ?? '[]'), true);

        return is_array($decoded) ? array_values(array_map('intval', $decoded)) : [];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function customerNameList(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $namesById = User::query()
            ->whereIn('id', $ids)
            ->where('role', 'client')
            ->pluck('name', 'id');

        $names = [];
        foreach ($ids as $id) {
            if (isset($namesById[$id])) {
                $names[] = $namesById[$id];
            }
        }

        return $names;
    }

    private function customerCity(User $user): string
    {
        if (Schema::hasTable('user_addresses')) {
            $city = UserAddress::query()
                ->where('user_id', $user->id)
                ->orderByDesc('is_default')
                ->value('city');
            if ($city) {
                return (string) $city;
            }
        }

        return '—';
    }
}
