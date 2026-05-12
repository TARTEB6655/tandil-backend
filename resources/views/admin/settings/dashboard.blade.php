@php use App\Models\Setting; @endphp
<x-admin-layout>
    <div class="space-y-6 max-w-3xl">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Settings</h1>
                <p class="mt-1 text-sm text-gray-500">System, app configuration, data & privacy, and advanced options</p>
            </div>
            <a href="{{ route('admin.settings.all') }}" class="text-sm font-medium text-emerald-600 hover:text-emerald-700">All Settings →</a>
        </div>

        <!-- Admin User Information Card (matches mobile layout) -->
        <div class="rounded-xl border border-gray-200 bg-amber-50/80 shadow-sm overflow-hidden">
            <div class="flex items-center gap-4 px-4 py-4">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-emerald-600 text-lg font-semibold text-white">
                    {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-900">{{ auth()->user()->name ?? 'Admin User' }}</p>
                    <p class="text-sm text-gray-600">{{ auth()->user()->email }}</p>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">ADMIN-{{ str_pad((string) auth()->id(), 3, '0', STR_PAD_LEFT) }}</p>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <span class="text-sm">{{ session('error') }}</span>
            </div>
        @endif

        <!-- System Settings -->
        <div>
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">System Settings</h2>
            <div class="bg-amber-50/60 rounded-xl border border-amber-100/80 shadow-sm overflow-hidden divide-y divide-amber-100/80">
                <form method="POST" action="{{ route('admin.settings.system.store') }}" id="system-form" class="space-y-0">
                    @csrf
                    <label class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1" /></svg>
                            </span>
                            <div>
                                <p class="font-medium text-gray-900">Push Notifications</p>
                                <p class="text-sm text-gray-500">Enable system notifications</p>
                            </div>
                        </div>
                        <input type="hidden" name="push_notifications_enabled" value="0">
                        <input type="checkbox" name="push_notifications_enabled" value="1" {{ $system['push_notifications_enabled'] ? 'checked' : '' }} onchange="this.form.submit();" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    </label>
                    <label class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            </span>
                            <div>
                                <p class="font-medium text-gray-900">Auto-Assign Tasks</p>
                                <p class="text-sm text-gray-500">Automatically assign tasks to workers</p>
                            </div>
                        </div>
                        <input type="hidden" name="auto_assign_tasks" value="0">
                        <input type="checkbox" name="auto_assign_tasks" value="1" {{ $system['auto_assign_tasks'] ? 'checked' : '' }} onchange="this.form.submit();" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    </label>
                    <label class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            </span>
                            <div>
                                <p class="font-medium text-gray-900">Maintenance Mode</p>
                                <p class="text-sm text-gray-500">Enable maintenance mode</p>
                            </div>
                        </div>
                        <input type="hidden" name="maintenance_mode" value="0">
                        <input type="checkbox" name="maintenance_mode" value="1" {{ $system['maintenance_mode'] ? 'checked' : '' }} onchange="this.form.submit();" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    </label>
                </form>
            </div>
        </div>

        <!-- App Configuration -->
        <div>
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">App Configuration</h2>
            <div class="bg-amber-50/60 rounded-xl border border-amber-100/80 shadow-sm overflow-hidden divide-y divide-amber-100/80">
                <a href="{{ route('admin.settings.theme') }}" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                        </span>
                        <div>
                            <p class="font-medium text-gray-900">Theme Settings</p>
                            <p class="text-sm text-gray-500">Customize app appearance</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <a href="{{ route('admin.settings.language') }}" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" /></svg>
                        </span>
                        <div>
                            <p class="font-medium text-gray-900">Language & Region</p>
                            <p class="text-sm text-gray-500">Change app language</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <a href="{{ route('admin.settings.payment') }}" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </span>
                        <div>
                            <p class="font-medium text-gray-900">Payment Settings</p>
                            <p class="text-sm text-gray-500">Configure payment methods</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <a href="{{ route('admin.settings.client-dashboard') }}" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
                        </span>
                        <div>
                            <p class="font-medium text-gray-900">Customer Dashboard Design</p>
                            <p class="text-sm text-gray-500">Control what customers see on their dashboard</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
            </div>
        </div>

        <!-- Data & Privacy -->
        <div>
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Data & Privacy</h2>
            <div class="bg-amber-50/60 rounded-xl border border-amber-100/80 shadow-sm overflow-hidden divide-y divide-amber-100/80">
                <a href="{{ route('admin.settings.privacy-policy') }}" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        </span>
                        <div>
                            <p class="font-medium text-gray-900">Privacy Policy</p>
                            <p class="text-sm text-gray-500">View privacy policy</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <a href="{{ route('admin.settings.terms') }}" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        </span>
                        <div>
                            <p class="font-medium text-gray-900">Terms of Service</p>
                            <p class="text-sm text-gray-500">View terms and conditions</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <form method="POST" action="{{ route('admin.settings.clear-cache') }}" class="block" onsubmit="return confirm('Clear all app cache?');">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors text-left">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </span>
                            <div>
                                <p class="font-medium text-gray-900">Clear Cache</p>
                                <p class="text-sm text-gray-500">Clear app cache data</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </form>
            </div>
        </div>

        <!-- Advanced -->
        <div>
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Advanced</h2>
            <div class="bg-amber-50/60 rounded-xl border border-amber-100/80 shadow-sm overflow-hidden divide-y divide-amber-100/80">
                <a href="{{ route('admin.settings.debug-logs') }}" class="flex items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                        </span>
                        <div>
                            <p class="font-medium text-gray-900">Debug Logs</p>
                            <p class="text-sm text-gray-500">View logs, errors, and runtime (debug / environment)</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <form method="POST" action="{{ route('admin.settings.export-data') }}" class="block">
                    @csrf
                    <input type="hidden" name="format" value="json">
                    <button type="submit" class="flex w-full items-center justify-between px-4 py-4 hover:bg-gray-50/50 transition-colors text-left">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-emerald-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            </span>
                            <div>
                                <p class="font-medium text-gray-900">Export Data</p>
                                <p class="text-sm text-gray-500">Export system data</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
