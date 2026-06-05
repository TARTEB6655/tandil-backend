@auth
    @if(auth()->user()->role === 'vendor')
        @php
            $vendorAccount = auth()->user()->vendor;
            $vendorApproved = $vendorAccount?->isApproved() ?? false;
            $linkClass = fn (bool $active) => 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors'
                .($active ? ' bg-gray-100 font-semibold' : '');
        @endphp
        <div>
            <div x-show="$store.sidebar.open"
                 x-cloak
                 x-transition:enter="transition-opacity duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="$store.sidebar.toggle()"
                 class="fixed inset-0 z-40 bg-black bg-opacity-50 max-[991px]:block min-[992px]:hidden"
                 style="display: none;"></div>

            <aside
                class="fixed left-0 top-0 z-50 flex h-screen w-[250px] flex-col overflow-hidden border-r border-gray-200 bg-white transition-transform duration-300 ease-in-out min-[992px]:translate-x-0"
                :class="$store.sidebar.open ? 'translate-x-0 shadow-lg min-[992px]:shadow-none' : '-translate-x-full'"
            >
                <div class="flex flex-shrink-0 items-center justify-between px-3 py-6 sm:px-4 sm:py-8">
                    <a href="{{ $vendorApproved ? route('vendor.dashboard') : route('vendor.onboarding.index') }}" class="flex flex-1 items-center justify-center">
                        <img src="{{ asset('images/logo.png') }}" alt="Tandil" class="h-20 w-auto md:h-24" style="max-width: 160px; object-fit: contain;" onerror="this.style.display='none'" />
                    </a>
                    <button type="button" @click="$store.sidebar.toggle()" class="min-[992px]:hidden flex-shrink-0 rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700" aria-label="Close sidebar">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="sidebar-scroll flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden">
                    <nav class="flex-1 px-3 py-2">
                        @unless($vendorApproved)
                            <p class="px-3 py-1 text-xs font-normal uppercase tracking-wider text-gray-500">Onboarding</p>
                            <div class="mb-2">
                                <a href="{{ route('vendor.application.status') }}" class="{{ $linkClass(request()->routeIs('vendor.application.*')) }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Application
                                </a>
                            </div>
                            <div class="mb-2">
                                <a href="{{ route('vendor.onboarding.index') }}" class="{{ $linkClass(request()->routeIs('vendor.onboarding.*')) }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                    Onboarding
                                </a>
                            </div>
                            <div class="mb-2">
                                <a href="{{ route('vendor.documents.index') }}" class="{{ $linkClass(request()->routeIs('vendor.documents.*')) }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                    Documents
                                </a>
                            </div>
                            <div class="mb-4 border-t border-gray-200 pt-4">
                                <p class="px-3 py-1 text-xs font-normal uppercase tracking-wider text-gray-500">Account</p>
                            </div>
                        @endunless
                        @if($vendorApproved)
                        <div class="mb-2">
                            <a href="{{ route('vendor.dashboard') }}" class="{{ $linkClass(request()->routeIs('vendor.dashboard')) }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                                {{ __('admin.dashboard') }}
                            </a>
                        </div>
                        <div class="mb-2">
                            <a href="{{ route('vendor.products.index') }}" class="{{ $linkClass(request()->routeIs('vendor.products.*')) }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                Products
                            </a>
                        </div>
                        <div class="mb-2">
                            <a href="{{ route('vendor.orders.index') }}" class="{{ $linkClass(request()->routeIs('vendor.orders.*')) }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                Orders
                            </a>
                        </div>
                        <div class="mb-2">
                            <a href="{{ route('vendor.inventory.index') }}" class="{{ $linkClass(request()->routeIs('vendor.inventory.*')) }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                Inventory
                            </a>
                        </div>
                        <div class="mb-2">
                            <a href="{{ route('vendor.profile.show') }}" class="{{ $linkClass(request()->routeIs('vendor.profile.*')) }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                Business Profile
                            </a>
                        </div>
                        @endif

                        <div class="mt-4 border-t border-gray-200 pt-4">
                            <p class="px-3 py-1 text-xs font-normal uppercase tracking-wider text-gray-500">{{ __('admin.account') }}</p>
                            <a href="{{ route('profile.edit') }}" class="{{ $linkClass(request()->routeIs('profile.*')) }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                {{ __('admin.settings') }}
                            </a>
                        </div>
                    </nav>

                    <div class="mt-auto flex-shrink-0 border-t border-gray-200 px-3 py-3">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                {{ __('admin.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>
    @endif
@endauth
