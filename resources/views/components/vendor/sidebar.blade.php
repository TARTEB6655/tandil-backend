<aside class="fixed left-0 top-0 z-50 flex h-screen w-[250px] flex-col bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-center py-8 px-4">
        <a href="{{ route('vendor.dashboard') }}">
            <img src="{{ asset('images/logo.png') }}" alt="Tandil" class="h-16 w-auto" onerror="this.style.display='none'" />
        </a>
    </div>
    <nav class="flex-1 px-3 space-y-1">
        <a href="{{ route('vendor.dashboard') }}" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium {{ request()->routeIs('vendor.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
            Dashboard
        </a>
    </nav>
</aside>
