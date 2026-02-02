@php use App\Models\Setting; @endphp
<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Social Media Links</h1>
                <p class="mt-1 text-sm text-gray-500">Facebook, Twitter, Instagram, and more</p>
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
                <h2 class="text-lg font-semibold text-gray-900">Social Media Links</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.settings.social') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="facebook_url" class="block text-sm font-medium text-gray-700 mb-2">Facebook URL</label>
                            <input type="url" id="facebook_url" name="facebook_url" value="{{ Setting::get('facebook_url') }}" placeholder="https://facebook.com/yourpage" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="twitter_url" class="block text-sm font-medium text-gray-700 mb-2">Twitter URL</label>
                            <input type="url" id="twitter_url" name="twitter_url" value="{{ Setting::get('twitter_url') }}" placeholder="https://twitter.com/yourhandle" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="instagram_url" class="block text-sm font-medium text-gray-700 mb-2">Instagram URL</label>
                            <input type="url" id="instagram_url" name="instagram_url" value="{{ Setting::get('instagram_url') }}" placeholder="https://instagram.com/yourhandle" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="linkedin_url" class="block text-sm font-medium text-gray-700 mb-2">LinkedIn URL</label>
                            <input type="url" id="linkedin_url" name="linkedin_url" value="{{ Setting::get('linkedin_url') }}" placeholder="https://linkedin.com/company/yourcompany" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="youtube_url" class="block text-sm font-medium text-gray-700 mb-2">YouTube URL</label>
                            <input type="url" id="youtube_url" name="youtube_url" value="{{ Setting::get('youtube_url') }}" placeholder="https://youtube.com/channel/yourchannel" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">Save Social Links</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
