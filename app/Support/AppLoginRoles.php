<?php

namespace App\Support;

use App\Models\User;

/**
 * Single source for app login entry labels (role picker + GET /api/auth/app-roles).
 * Slug values match User::LOGIN_PORTALS and Sanctum token resolution.
 */
final class AppLoginRoles
{
    /**
     * @return array<string, array{title: string, subtitle: string, icon: string}>
     */
    public static function bySlug(): array
    {
        return [
            'client' => [
                'title' => 'Client (Customer)',
                'subtitle' => 'Subscribe to plans, receive reports, and purchase agricultural products.',
                'icon' => 'user',
            ],
            'technician' => [
                'title' => 'Worker (Field Technician)',
                'subtitle' => 'Perform watering, planting, cleaning tasks and submit field reports.',
                'icon' => 'leaf',
            ],
            'supervisor' => [
                'title' => 'Supervisor (Team Leader)',
                'subtitle' => 'Manage workers, review reports, and submit final reports to clients.',
                'icon' => 'users',
            ],
            'area_manager' => [
                'title' => 'Area Manager',
                'subtitle' => 'Oversee supervisors and technicians within a defined region.',
                'icon' => 'map',
            ],
            'hr' => [
                'title' => 'HR Manager',
                'subtitle' => 'Manage employee profiles, job IDs, schedules, and staff requirements.',
                'icon' => 'briefcase',
            ],
            'admin' => [
                'title' => 'Admin',
                'subtitle' => 'Full platform administration, users, settings, and support.',
                'icon' => 'shield',
            ],
            'vendor' => [
                'title' => 'Vendor',
                'subtitle' => 'Manage your products, inventory, pricing, and orders.',
                'icon' => 'store',
            ],
        ];
    }

    /**
     * Ordered list for GET /api/auth/app-roles (mobile role picker).
     * Includes explicit `role` + `description` for UI; `title` / `subtitle` kept for compatibility.
     *
     * @return list<array{slug: string, role: string, description: string, title: string, subtitle: string, icon: string}>
     */
    public static function listForApi(): array
    {
        $bySlug = self::bySlug();
        $rows = [];
        foreach (User::LOGIN_PORTALS as $slug) {
            $meta = $bySlug[$slug] ?? ['title' => $slug, 'subtitle' => '', 'icon' => 'user'];
            $title = $meta['title'];
            $subtitle = $meta['subtitle'];
            $rows[] = [
                'slug' => $slug,
                'role' => $title,
                'description' => $subtitle,
                'title' => $title,
                'subtitle' => $subtitle,
                'icon' => $meta['icon'],
            ];
        }

        return $rows;
    }
}
