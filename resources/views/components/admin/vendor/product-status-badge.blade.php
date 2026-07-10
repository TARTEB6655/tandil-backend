@props(['status'])

@php
    $key = is_string($status) ? $status : ($status?->value ?? 'unknown');
    $label = is_string($status) ? ucfirst(str_replace('_', ' ', $status)) : ($status?->label() ?? 'Unknown');

    $customLabels = [
        'disabled_by_admin' => 'Disabled by Admin',
        'pending_review' => 'Pending Review',
        'out_of_stock' => 'Out of Stock',
    ];
    $label = $customLabels[$key] ?? $label;

    $colors = [
        'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300',
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300',
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300',
        'pending_review' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300',
        'under_review' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-950/40 dark:text-sky-300',
        'suspended' => 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-950/40 dark:text-orange-300',
        'rejected' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300',
        'disabled' => 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-400',
        'disabled_by_admin' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300',
        'inactive' => 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-400',
        'draft' => 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-400',
        'out_of_stock' => 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-950/40 dark:text-orange-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($colors[$key] ?? $colors['inactive'])]) }}>
    {{ $label }}
</span>
