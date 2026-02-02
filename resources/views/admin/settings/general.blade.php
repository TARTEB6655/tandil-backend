@php use App\Models\Setting; @endphp
<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">General & Branding</h1>
                <p class="mt-1 text-sm text-gray-500">Application name, logo, and colors</p>
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
                <h2 class="text-lg font-semibold text-gray-900">Application Settings & Branding</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.settings.app') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <div>
                        <label for="app_name" class="block text-sm font-medium text-gray-700 mb-2">Application Name</label>
                        <input type="text" id="app_name" name="app_name" value="{{ $appName }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    <div>
                        <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                        <div class="flex items-center gap-4">
                            @if($logo)
                                <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-20 w-auto object-contain border border-gray-200 rounded-lg p-2">
                            @endif
                            <div class="flex-1">
                                <input type="file" id="logo" name="logo" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="primary_color" class="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" id="primary_color" name="primary_color" value="{{ $primaryColor }}" class="h-10 w-20 rounded border border-gray-300 cursor-pointer">
                                <input type="text" value="{{ $primaryColor }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
                            </div>
                        </div>
                        <div>
                            <label for="secondary_color" class="block text-sm font-medium text-gray-700 mb-2">Secondary Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" id="secondary_color" name="secondary_color" value="{{ $secondaryColor }}" class="h-10 w-20 rounded border border-gray-300 cursor-pointer">
                                <input type="text" value="{{ $secondaryColor }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
