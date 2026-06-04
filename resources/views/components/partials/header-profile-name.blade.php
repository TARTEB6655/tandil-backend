@props(['user'])

<div {{ $attributes->merge(['class' => 'hidden lg:flex min-w-0 flex-col items-start text-left']) }}>
    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-tight truncate max-w-[160px]">
        {{ filled($user->name ?? null) ? $user->name : 'User' }}
    </span>
</div>
