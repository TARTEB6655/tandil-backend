@php use App\Models\Setting; @endphp
<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Push Notifications</h1>
                <p class="mt-1 text-sm text-gray-500">Firebase server key and sender ID</p>
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
                <h2 class="text-lg font-semibold text-gray-900">Push Notification Settings</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.settings.notification') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="firebase_server_key" class="block text-sm font-medium text-gray-700 mb-2">Firebase Server Key</label>
                        <input type="text" id="firebase_server_key" name="firebase_server_key" value="{{ Setting::get('firebase_server_key') }}" placeholder="AAAA..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="firebase_sender_id" class="block text-sm font-medium text-gray-700 mb-2">Firebase Sender ID</label>
                        <input type="text" id="firebase_sender_id" name="firebase_sender_id" value="{{ Setting::get('firebase_sender_id') }}" placeholder="123456789" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">Save Notification Settings</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
