@php use App\Models\Setting; @endphp
<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Security Settings</h1>
                <p class="mt-1 text-sm text-gray-500">Password policy, 2FA, and login limits</p>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Security Settings</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.settings.security') }}" class="space-y-4">
                    @csrf
                    <div>
                        <h3 class="text-base font-medium text-gray-900 mb-4">Password Policy</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="password_min_length" class="block text-sm font-medium text-gray-700 mb-2">Minimum Password Length</label>
                                <input type="number" id="password_min_length" name="password_min_length" value="{{ Setting::get('password_min_length', 8) }}" min="6" max="32" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="password_require_uppercase" value="1" {{ Setting::get('password_require_uppercase', false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Require uppercase letters</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="password_require_lowercase" value="1" {{ Setting::get('password_require_lowercase', false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Require lowercase letters</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="password_require_numbers" value="1" {{ Setting::get('password_require_numbers', false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Require numbers</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="password_require_symbols" value="1" {{ Setting::get('password_require_symbols', false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Require special characters</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="text-base font-medium text-gray-900 mb-4">Two-Factor Authentication</h3>
                        <label class="flex items-center">
                            <input type="checkbox" name="two_factor_enabled" value="1" {{ Setting::get('two_factor_enabled', false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Enable two-factor authentication</span>
                        </label>
                    </div>
                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="text-base font-medium text-gray-900 mb-4">Login Security</h3>
                        <div>
                            <label for="login_attempts_limit" class="block text-sm font-medium text-gray-700 mb-2">Maximum Login Attempts</label>
                            <input type="number" id="login_attempts_limit" name="login_attempts_limit" value="{{ Setting::get('login_attempts_limit', 5) }}" min="3" max="10" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">Save Security Settings</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
