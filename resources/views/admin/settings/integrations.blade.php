@php use App\Models\Setting; @endphp
<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Integrations</h1>
                <p class="mt-1 text-sm text-gray-500">WhatsApp, Google Maps, webhooks</p>
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
                <h2 class="text-lg font-semibold text-gray-900">Integrations</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.settings.integrations') }}" class="space-y-4">
                    @csrf
                    <div>
                        <h3 class="text-base font-medium text-gray-900 mb-4">WhatsApp API</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="whatsapp_api_key" class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                                <input type="text" id="whatsapp_api_key" name="whatsapp_api_key" value="{{ Setting::get('whatsapp_api_key') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="whatsapp_phone_number" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="text" id="whatsapp_phone_number" name="whatsapp_phone_number" value="{{ Setting::get('whatsapp_phone_number') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="text-base font-medium text-gray-900 mb-4">Google Maps API</h3>
                        <div>
                            <label for="google_maps_api_key" class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                            <input type="text" id="google_maps_api_key" name="google_maps_api_key" value="{{ Setting::get('google_maps_api_key') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="text-base font-medium text-gray-900 mb-4">Webhooks</h3>
                        <div>
                            <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                            <input type="url" id="webhook_url" name="webhook_url" value="{{ Setting::get('webhook_url') }}" placeholder="https://example.com/webhook" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">Save Integrations</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
