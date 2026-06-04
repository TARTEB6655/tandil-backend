@props(['user'])

<div {{ $attributes->merge(['class' => 'hidden lg:flex min-w-0 items-center text-left']) }}>
    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-none truncate max-w-[160px]">
        {{ filled($user->name ?? null) ? $user->name : 'User' }}
    </span>
</div>
