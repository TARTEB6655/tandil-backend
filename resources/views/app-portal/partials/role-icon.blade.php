@php
    $iconType = $type ?? 'user';
    $cls = 'h-5 w-5';
@endphp
<div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#2d4a3e]/30 bg-white text-[#2d4a3e]">
    @switch($iconType)
        @case('leaf')
            <svg class="{{ $cls }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c4.5 3 7.5 7.5 7.5 12a4.5 4.5 0 01-9 0c0-3 1.5-6 4.5-8.5A22 22 0 0012 3z" />
            </svg>
            @break
        @case('users')
            <svg class="{{ $cls }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            @break
        @case('map')
            <svg class="{{ $cls }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503-15.672a1.125 1.125 0 00-1.128 0l-3.59 2.062A1.125 1.125 0 006 9.607v7.788c0 .323.155.625.418.813l3.59 2.062a1.125 1.125 0 001.128 0l3.59-2.062a1.125 1.125 0 00.418-.813V9.607c0-.323-.155-.625-.418-.813l-3.59-2.062z" />
            </svg>
            @break
        @case('briefcase')
            <svg class="{{ $cls }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.25 2.25 0 00-1.423-2.082M20.25 14.15l-1.423-2.082M20.25 14.15l.59-.492a2.25 2.25 0 00-1.423-2.082m0 0l-1.423-2.082M4.5 14.15l1.423-2.082M4.5 14.15l-.59-.492a2.25 2.25 0 011.423-2.082m0 0l1.423-2.082m0 0h9.308" />
            </svg>
            @break
        @case('shield')
            <svg class="{{ $cls }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
            @break
        @default
            <svg class="{{ $cls }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            @break
    @endswitch
</div>
