<?php

namespace App\Support;

use App\Models\Vendor;

/**
 * Resolves admin dashboard URLs from stored notification payloads.
 */
final class AdminNotificationTargetUrl
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolve(array $data): ?string
    {
        $meta = self::meta($data);
        $entity = $meta['entity'] ?? null;

        if ($entity === 'support_chat' && ! empty($meta['session_id'])) {
            return route('admin.support-chat.show', $meta['session_id']);
        }

        if ($entity === 'support_ticket' && ! empty($meta['ticket_id'])) {
            return route('admin.support-tickets.show', $meta['ticket_id']);
        }

        if (in_array($entity, ['vendor', 'vendor_application'], true) && ! empty($meta['vendor_id'])) {
            $vendorId = (int) $meta['vendor_id'];
            if (! Vendor::withTrashed()->whereKey($vendorId)->exists()) {
                return null;
            }

            return route('admin.vendors.show', $vendorId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function meta(array $data): array
    {
        if (is_array($data['meta'] ?? null)) {
            return $data['meta'];
        }

        // Legacy rows that stored entity fields at the top level.
        $legacyKeys = ['entity', 'vendor_id', 'session_id', 'ticket_id', 'action'];
        $legacy = array_intersect_key($data, array_flip($legacyKeys));

        return $legacy !== [] ? $legacy : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function missingVendorApplication(array $data): bool
    {
        $meta = self::meta($data);
        $entity = $meta['entity'] ?? null;

        if (! in_array($entity, ['vendor', 'vendor_application'], true) || empty($meta['vendor_id'])) {
            return false;
        }

        return ! Vendor::withTrashed()->whereKey((int) $meta['vendor_id'])->exists();
    }
}
