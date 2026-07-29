<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * First-wave alerts when a client order is paid:
 * 1) Supervisor(s) for the resolved area
 * 2) Area manager(s) for that area (or all area managers if none are zone-linked)
 *
 * Technician alerts are second-wave (when a job is offered/assigned), not here.
 */
final class OrderSupervisorNotifier
{
    /**
     * Notify supervisors + area managers when a shop order becomes paid.
     */
    public static function notifySupervisorsForPaidOrder(Order $order, float $total, string $placedBy): void
    {
        try {
            $resolved = self::resolveAreaForOrder($order);
            if (! $resolved) {
                return;
            }

            /** @var Area $area */
            $area = $resolved['area'];
            $recipients = self::firstWaveRecipientsForArea($area);
            if ($recipients->isEmpty()) {
                return;
            }

            $who = $order->payerDisplayName();
            $city = (string) (($resolved['ship']['city'] ?? '') ?: '');
            $country = (string) (($resolved['ship']['country'] ?? '') ?: '');
            $location = $city.($country !== '' ? ", {$country}" : '');

            foreach ($recipients as $user) {
                if (! $user instanceof User) {
                    continue;
                }

                $role = self::staffRole($user);
                $title = $role === 'area_manager'
                    ? 'New Order in Your Region'
                    : 'New Order in Your Area';

                $user->notify(new AdminNotification(
                    $title,
                    "A new order #{$order->id} has been placed in {$area->name}".($location !== '' ? " ({$location})" : '')." by {$who} via {$placedBy} for AED {$total}.",
                    [
                        'type' => 'new_paid_order',
                        'alert_wave' => 1,
                        'order_id' => $order->id,
                        'area_id' => $area->id,
                        'recipient_role' => $role,
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Order supervisor/area-manager notify failed: '.$e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private static function firstWaveRecipientsForArea(Area $area): Collection
    {
        $pivotUserIds = [];
        if (Schema::hasTable('area_supervisor')) {
            $pivotUserIds = DB::table('area_supervisor')
                ->where('area_id', $area->id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $supervisors = collect();
        if ($pivotUserIds !== []) {
            $supervisors = User::query()
                ->whereIn('id', $pivotUserIds)
                ->where(function ($q) {
                    $q->whereRaw('LOWER(role) = ?', ['supervisor'])
                        ->orWhereHas('roles', fn ($r) => $r->whereRaw('LOWER(name) = ?', ['supervisor']));
                })
                ->get();
        }

        $areaManagersOnArea = collect();
        if ($pivotUserIds !== []) {
            $areaManagersOnArea = User::query()
                ->whereIn('id', $pivotUserIds)
                ->where(function ($q) {
                    $q->whereRaw('LOWER(role) = ?', ['area_manager'])
                        ->orWhereHas('roles', fn ($r) => $r->whereRaw('LOWER(name) = ?', ['area_manager']));
                })
                ->get();
        }

        // If no area manager is linked to this zone, still alert all area managers
        // (common when admin only assigned a supervisor to the zone).
        $areaManagers = $areaManagersOnArea->isNotEmpty()
            ? $areaManagersOnArea
            : User::query()
                ->where(function ($q) {
                    $q->whereRaw('LOWER(role) = ?', ['area_manager'])
                        ->orWhereHas('roles', fn ($r) => $r->whereRaw('LOWER(name) = ?', ['area_manager']));
                })
                ->get();

        return $supervisors
            ->concat($areaManagers)
            ->unique('id')
            ->values();
    }

    private static function staffRole(User $user): string
    {
        try {
            if ($user->hasRole('area_manager') || strtolower((string) $user->role) === 'area_manager') {
                return 'area_manager';
            }
            if ($user->hasRole('supervisor') || strtolower((string) $user->role) === 'supervisor') {
                return 'supervisor';
            }
        } catch (\Throwable $e) {
            //
        }

        return strtolower((string) ($user->role ?? 'staff'));
    }

    /**
     * @return array{area: Area, ship: array}|null
     */
    private static function resolveAreaForOrder(Order $order): ?array
    {
        $ship = $order->getShippingAddressForApi();
        if (! is_array($ship)) {
            return null;
        }

        $country = self::normalizeCountry((string) ($ship['country'] ?? ''));
        $city = strtolower(trim((string) ($ship['city'] ?? '')));
        $state = strtolower(trim((string) ($ship['state'] ?? '')));
        $street = strtolower(trim((string) ($ship['street_address'] ?? '')));
        $zip = strtolower(trim((string) ($ship['zip_code'] ?? '')));

        if ($city === '' && $state === '' && $street === '' && $zip === '' && $country === '') {
            return null;
        }

        $areas = Area::with('supervisors')
            ->where('is_active', true)
            ->get()
            ->filter(function (Area $a) {
                if ($a->supervisors->isNotEmpty()) {
                    return true;
                }

                // Area may only have staff via pivot count even if relation empty after filter
                if (! Schema::hasTable('area_supervisor')) {
                    return false;
                }

                return DB::table('area_supervisor')->where('area_id', $a->id)->exists();
            })
            ->values();

        if ($areas->isEmpty()) {
            // Still allow notifying area managers when we can match an active area by location
            // even if no supervisor is linked yet.
            $areas = Area::query()->where('is_active', true)->get();
        }

        if ($areas->isEmpty()) {
            return null;
        }

        if ($country !== '') {
            $countryMatched = $areas->filter(function (Area $area) use ($country) {
                return self::normalizeCountry((string) ($area->country ?? '')) === $country;
            })->values();

            if ($countryMatched->isNotEmpty()) {
                $areas = $countryMatched;
            }
        }

        $matched = $areas->first(function (Area $a) use ($city, $state, $street, $zip) {
            $hay = strtolower(trim((string) ($a->name.' '.($a->location ?? '').' '.($a->description ?? ''))));

            return ($city !== '' && str_contains($hay, $city))
                || ($state !== '' && str_contains($hay, $state))
                || ($street !== '' && str_contains($hay, $street))
                || ($zip !== '' && str_contains($hay, $zip));
        });

        if (! $matched instanceof Area) {
            return null;
        }

        return ['area' => $matched, 'ship' => $ship];
    }

    private static function normalizeCountry(string $country): string
    {
        $raw = strtolower(trim($country));

        return match ($raw) {
            'uae', 'u.a.e', 'ae', 'united arab emirates' => 'uae',
            default => $raw,
        };
    }
}
