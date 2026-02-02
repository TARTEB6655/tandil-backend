@php use App\Models\Setting; @endphp
<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Email (SMTP)</h1>
                <p class="mt-1 text-sm text-gray-500">Outgoing mail server configuration</p>
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
                <h2 class="text-lg font-semibold text-gray-900">Email Settings (SMTP)</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.settings.email.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="{{ Setting::get('smtp_host', 'smtp.gmail.com') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="{{ Setting::get('smtp_port', '587') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2">SMTP Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" value="{{ Setting::get('smtp_username') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2">SMTP Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" value="{{ Setting::get('smtp_password') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-2">Encryption</label>
                            <select id="smtp_encryption" name="smtp_encryption" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                                <option value="tls" {{ Setting::get('smtp_encryption') === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ Setting::get('smtp_encryption') === 'ssl' ? 'selected' : '' }}>SSL</option>
                            </select>
                        </div>
                        <div>
                            <label for="smtp_from_email" class="block text-sm font-medium text-gray-700 mb-2">From Email</label>
                            <input type="email" id="smtp_from_email" name="smtp_from_email" value="{{ Setting::get('smtp_from_email') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label for="smtp_from_name" class="block text-sm font-medium text-gray-700 mb-2">From Name</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" value="{{ Setting::get('smtp_from_name', $appName) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">Save Email Settings</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
