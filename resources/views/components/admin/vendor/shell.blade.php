@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'mx-auto max-w-[1400px] space-y-6 '.$class]) }}>
    {{ $slot }}
</div>
