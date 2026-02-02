@php use App\Models\Setting; @endphp
<x-admin-layout>
    <div class="space-y-6 max-w-2xl">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Theme Settings</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Customize app appearance</p>
            </div>
        </div>
        @if(session('success'))
            <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif
        <div class="bg-white dark:bg-gray-800/80 dark:border-gray-600 rounded-xl border border-gray-200 shadow-sm p-6">
            <form method="POST" action="{{ route('admin.settings.theme.store') }}" id="theme-form" onsubmit="(function(){ var r = document.querySelector('input[name=theme]:checked'); if(r){ var v = (r.value || '').toLowerCase(); var el = document.documentElement; if(v==='dark') el.classList.add('dark'); else if(v==='light') el.classList.remove('dark'); else { var m = window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)'); el.classList.toggle('dark', m&&m.matches); } } })();">
                @csrf
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">App theme</label>
                <div class="space-y-2">
                    @foreach($available as $value => $label)
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                            <input type="radio" name="theme" value="{{ $value }}" {{ $current === $value ? 'checked' : '' }} class="text-indigo-600 focus:ring-indigo-500">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="mt-4 px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">Save</button>
            </form>
        </div>
    </div>
</x-admin-layout>
