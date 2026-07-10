@props(['status'])

@php
    $colors = [
        'approved' => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-300',
        'pending' => 'bg-amber-100 text-amber-800 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300',
        'under_review' => 'bg-sky-100 text-sky-800 ring-sky-600/20 dark:bg-sky-900/30 dark:text-sky-300',
        'suspended' => 'bg-orange-100 text-orange-800 ring-orange-600/20 dark:bg-orange-900/30 dark:text-orange-300',
        'rejected' => 'bg-rose-100 text-rose-800 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-300',
        'disabled' => 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300',
        'active' => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'inactive' => 'bg-gray-100 text-gray-700 ring-gray-500/20',
    ];
    $label = is_string($status) ? ucfirst(str_replace('_', ' ', $status)) : ($status?->label() ?? 'Unknown');
    $key = is_string($status) ? $status : ($status?->value ?? 'disabled');
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset '.($colors[$key] ?? $colors['disabled'])]) }}>
    {{ $label }}
</span>
