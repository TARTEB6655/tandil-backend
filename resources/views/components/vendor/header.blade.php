@php
    $user = auth()->user();
    $vendor = $user?->vendor;
    $headerInitial = $user->name ? mb_strtoupper(mb_substr(trim($user->name), 0, 1)) : 'V';
    $headerProfilePic = $user->profile_picture_url ?? null;
    $businessName = $vendor?->profile?->business_name ?? $user->name;
@endphp

<header class="sticky top-0 z-40 border-b border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <button type="button" @click="$store.sidebar.toggle()" class="max-[991px]:inline-flex min-[992px]:hidden rounded-lg p-2.5 text-gray-600 hover:bg-gray-100" aria-label="Toggle sidebar">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-gray-900">{{ $businessName }}</p>
                <p class="truncate text-xs text-gray-500">Vendor · {{ ucfirst($vendor?->status ?? 'pending') }}</p>
            </div>
        </div>

        <div class="flex flex-shrink-0 items-center gap-2 sm:gap-3" x-data="{ open: false }">
            <div class="relative">
                <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-100">
                    @if($headerProfilePic)
                        <img src="{{ $headerProfilePic }}" alt="" class="h-8 w-8 rounded-full border border-gray-200 object-cover" />
                    @else
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">{{ $headerInitial }}</div>
                    @endif
                    <span class="hidden max-w-[140px] truncate text-sm font-medium text-gray-800 lg:inline">{{ $user->name }}</span>
                    <svg class="hidden h-4 w-4 text-gray-400 lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="open" x-cloak @click.away="open = false" x-transition class="absolute right-0 z-50 mt-2 w-56 rounded-xl border border-gray-100 bg-white py-2 shadow-lg" style="display: none;">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <p class="text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $user->email }}</p>
                    </div>
                    <a href="{{ route('vendor.profile.show') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50" @click="open = false">Business profile</a>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50" @click="open = false">Account settings</a>
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 mt-1">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
