@php
    use App\Models\Setting;
    $appName = Setting::get('app_name', config('app.name'));
    $logo = Setting::get('logo');
    $primaryColor = Setting::get('primary_color', '#6366f1');
    $secondaryColor = Setting::get('secondary_color', '#8b5cf6');
    $sections = [
        ['id' => 'general', 'label' => 'General & Branding', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['id' => 'contact', 'label' => 'Contact', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['id' => 'social', 'label' => 'Social', 'icon' => 'M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z'],
        ['id' => 'email', 'label' => 'Email (SMTP)', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['id' => 'notifications', 'label' => 'Push Notifications', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1'],
        ['id' => 'security', 'label' => 'Security', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
        ['id' => 'integrations', 'label' => 'Integrations', 'icon' => 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z'],
    ];
@endphp

<x-admin-layout>
    <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">
        <!-- In-page navigation (sticky on large screens) -->
        <aside class="lg:w-52 flex-shrink-0 order-2 lg:order-1">
            <div class="lg:sticky lg:top-24 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">On this page</h3>
                <nav class="flex flex-wrap gap-1 lg:flex-col lg:flex-nowrap">
                    @foreach($sections as $s)
                        <a href="#{{ $s['id'] }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $s['icon'] }}" /></svg>
                            {{ $s['label'] }}
                        </a>
                    @endforeach
                    <a href="{{ route('admin.settings.email-templates') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Email Templates
                    </a>
                </nav>
            </div>
        </aside>

        <div class="flex-1 min-w-0 space-y-6 order-1 lg:order-2">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <h1 class="text-2xl font-semibold text-gray-900">Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your application settings, branding, and integrations</p>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Application Settings & Branding -->
        <div id="general" class="scroll-mt-28 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    </span>
                    Application Settings & Branding
                </h2>
            </div>
            <div class="p-6">
            <form method="POST" action="{{ route('admin.settings.app') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                
                <!-- App Name -->
                <div>
                    <label for="app_name" class="block text-sm font-medium text-gray-700 mb-2">Application Name</label>
                    <input type="text" 
                           id="app_name" 
                           name="app_name" 
                           value="{{ $appName }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           required>
                </div>

                <!-- Logo Upload -->
                <div>
                    <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                    <div class="flex items-center gap-4">
                        @if($logo)
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="h-20 w-auto object-contain border border-gray-200 rounded-lg p-2">
                        @endif
                        <div class="flex-1">
                            <input type="file" 
                                   id="logo" 
                                   name="logo" 
                                   accept="image/*"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                        </div>
                    </div>
                </div>

                <!-- Colors -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="primary_color" class="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" 
                                   id="primary_color" 
                                   name="primary_color" 
                                   value="{{ $primaryColor }}"
                                   class="h-10 w-20 rounded border border-gray-300 cursor-pointer">
                            <input type="text" 
                                   value="{{ $primaryColor }}"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   readonly>
                        </div>
                    </div>
                    <div>
                        <label for="secondary_color" class="block text-sm font-medium text-gray-700 mb-2">Secondary Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" 
                                   id="secondary_color" 
                                   name="secondary_color" 
                                   value="{{ $secondaryColor }}"
                                   class="h-10 w-20 rounded border border-gray-300 cursor-pointer">
                            <input type="text" 
                                   value="{{ $secondaryColor }}"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   readonly>
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">
                    Save Changes
                </button>
            </form>
            </div>
        </div>

        <!-- Contact Information -->
        <div id="contact" class="scroll-mt-28 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </span>
                    Contact Information
                </h2>
            </div>
            <div class="p-6">
            <form method="POST" action="{{ route('admin.settings.contact') }}" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                        <input type="email" 
                               id="contact_email" 
                               name="contact_email" 
                               value="{{ Setting::get('contact_email') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                        <input type="text" 
                               id="contact_phone" 
                               name="contact_phone" 
                               value="{{ Setting::get('contact_phone') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label for="contact_address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea id="contact_address" 
                              name="contact_address" 
                              rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ Setting::get('contact_address') }}</textarea>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">
                    Save Contact Info
                </button>
            </form>
            </div>
        </div>

        <!-- Social Links -->
        <div id="social" class="scroll-mt-28 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" /></svg>
                    </span>
                    Social Media Links
                </h2>
            </div>
            <div class="p-6">
            <form method="POST" action="{{ route('admin.settings.social') }}" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="facebook_url" class="block text-sm font-medium text-gray-700 mb-2">Facebook URL</label>
                        <input type="url" 
                               id="facebook_url" 
                               name="facebook_url" 
                               value="{{ Setting::get('facebook_url') }}"
                               placeholder="https://facebook.com/yourpage"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="twitter_url" class="block text-sm font-medium text-gray-700 mb-2">Twitter URL</label>
                        <input type="url" 
                               id="twitter_url" 
                               name="twitter_url" 
                               value="{{ Setting::get('twitter_url') }}"
                               placeholder="https://twitter.com/yourhandle"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="instagram_url" class="block text-sm font-medium text-gray-700 mb-2">Instagram URL</label>
                        <input type="url" 
                               id="instagram_url" 
                               name="instagram_url" 
                               value="{{ Setting::get('instagram_url') }}"
                               placeholder="https://instagram.com/yourhandle"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="linkedin_url" class="block text-sm font-medium text-gray-700 mb-2">LinkedIn URL</label>
                        <input type="url" 
                               id="linkedin_url" 
                               name="linkedin_url" 
                               value="{{ Setting::get('linkedin_url') }}"
                               placeholder="https://linkedin.com/company/yourcompany"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="youtube_url" class="block text-sm font-medium text-gray-700 mb-2">YouTube URL</label>
                        <input type="url" 
                               id="youtube_url" 
                               name="youtube_url" 
                               value="{{ Setting::get('youtube_url') }}"
                               placeholder="https://youtube.com/channel/yourchannel"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">
                    Save Social Links
                </button>
            </form>
            </div>
        </div>

        <!-- Email Settings -->
        <div id="email" class="scroll-mt-28 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </span>
                    Email Settings (SMTP)
                </h2>
            </div>
            <div class="p-6">
            <form method="POST" action="{{ route('admin.settings.email.store') }}" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                        <input type="text" 
                               id="smtp_host" 
                               name="smtp_host" 
                               value="{{ Setting::get('smtp_host', 'smtp.gmail.com') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               required>
                    </div>
                    <div>
                        <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2">SMTP Port</label>
                        <input type="number" 
                               id="smtp_port" 
                               name="smtp_port" 
                               value="{{ Setting::get('smtp_port', '587') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               required>
                    </div>
                    <div>
                        <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2">SMTP Username</label>
                        <input type="text" 
                               id="smtp_username" 
                               name="smtp_username" 
                               value="{{ Setting::get('smtp_username') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               required>
                    </div>
                    <div>
                        <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2">SMTP Password</label>
                        <input type="password" 
                               id="smtp_password" 
                               name="smtp_password" 
                               value="{{ Setting::get('smtp_password') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               required>
                    </div>
                    <div>
                        <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-2">Encryption</label>
                        <select id="smtp_encryption" 
                                name="smtp_encryption" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                required>
                            <option value="tls" {{ Setting::get('smtp_encryption') === 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ Setting::get('smtp_encryption') === 'ssl' ? 'selected' : '' }}>SSL</option>
                        </select>
                    </div>
                    <div>
                        <label for="smtp_from_email" class="block text-sm font-medium text-gray-700 mb-2">From Email</label>
                        <input type="email" 
                               id="smtp_from_email" 
                               name="smtp_from_email" 
                               value="{{ Setting::get('smtp_from_email') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               required>
                    </div>
                    <div>
                        <label for="smtp_from_name" class="block text-sm font-medium text-gray-700 mb-2">From Name</label>
                        <input type="text" 
                               id="smtp_from_name" 
                               name="smtp_from_name" 
                               value="{{ Setting::get('smtp_from_name', $appName) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               required>
                    </div>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">
                    Save Email Settings
                </button>
            </form>
            </div>
        </div>

        <!-- Notification Settings -->
        <div id="notifications" class="scroll-mt-28 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1" /></svg>
                    </span>
                    Push Notification Settings
                </h2>
            </div>
            <div class="p-6">
            <form method="POST" action="{{ route('admin.settings.notification') }}" class="space-y-4">
                @csrf
                
                <div>
                    <label for="firebase_server_key" class="block text-sm font-medium text-gray-700 mb-2">Firebase Server Key</label>
                    <input type="text" 
                           id="firebase_server_key" 
                           name="firebase_server_key" 
                           value="{{ Setting::get('firebase_server_key') }}"
                           placeholder="AAAA..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="firebase_sender_id" class="block text-sm font-medium text-gray-700 mb-2">Firebase Sender ID</label>
                    <input type="text" 
                           id="firebase_sender_id" 
                           name="firebase_sender_id" 
                           value="{{ Setting::get('firebase_sender_id') }}"
                           placeholder="123456789"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">
                    Save Notification Settings
                </button>
            </form>
            </div>
        </div>

        <!-- Email Templates -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </span>
                    Email Templates
                </h2>
                <a href="{{ route('admin.settings.email-templates') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium shadow-sm hover:shadow">
                    Manage Templates
                </a>
            </div>
            <div class="p-6">
            <p class="text-sm text-gray-500">Edit email templates for order confirmations, user registrations, and more.</p>
            </div>
        </div>

        <!-- Security Settings -->
        <div id="security" class="scroll-mt-28 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </span>
                    Security Settings
                </h2>
            </div>
            <div class="p-6">
            <form method="POST" action="{{ route('admin.settings.security') }}" class="space-y-4">
                @csrf
                
                <div>
                    <h3 class="text-base font-medium text-gray-900 mb-4">Password Policy</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="password_min_length" class="block text-sm font-medium text-gray-700 mb-2">Minimum Password Length</label>
                            <input type="number" 
                                   id="password_min_length" 
                                   name="password_min_length" 
                                   value="{{ Setting::get('password_min_length', 8) }}"
                                   min="6" max="32"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="password_require_uppercase" 
                                       value="1"
                                       {{ Setting::get('password_require_uppercase', false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Require uppercase letters</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="password_require_lowercase" 
                                       value="1"
                                       {{ Setting::get('password_require_lowercase', false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Require lowercase letters</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="password_require_numbers" 
                                       value="1"
                                       {{ Setting::get('password_require_numbers', false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Require numbers</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="password_require_symbols" 
                                       value="1"
                                       {{ Setting::get('password_require_symbols', false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Require special characters</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-200">
                    <h3 class="text-base font-medium text-gray-900 mb-4">Two-Factor Authentication</h3>
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="two_factor_enabled" 
                               value="1"
                               {{ Setting::get('two_factor_enabled', false) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">Enable two-factor authentication</span>
                    </label>
                </div>

                <div class="pt-4 border-t border-gray-200">
                    <h3 class="text-base font-medium text-gray-900 mb-4">Login Security</h3>
                    <div>
                        <label for="login_attempts_limit" class="block text-sm font-medium text-gray-700 mb-2">Maximum Login Attempts</label>
                        <input type="number" 
                               id="login_attempts_limit" 
                               name="login_attempts_limit" 
                               value="{{ Setting::get('login_attempts_limit', 5) }}"
                               min="3" max="10"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">
                    Save Security Settings
                </button>
            </form>
            </div>
        </div>

        <!-- Integrations -->
        <div id="integrations" class="scroll-mt-28 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" /></svg>
                    </span>
                    Integrations
                </h2>
            </div>
            <div class="p-6">
            <form method="POST" action="{{ route('admin.settings.integrations') }}" class="space-y-4">
                @csrf
                
                <div>
                    <h3 class="text-base font-medium text-gray-900 mb-4">WhatsApp API</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="whatsapp_api_key" class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                            <input type="text" 
                                   id="whatsapp_api_key" 
                                   name="whatsapp_api_key" 
                                   value="{{ Setting::get('whatsapp_api_key') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="whatsapp_phone_number" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="text" 
                                   id="whatsapp_phone_number" 
                                   name="whatsapp_phone_number" 
                                   value="{{ Setting::get('whatsapp_phone_number') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-200">
                    <h3 class="text-base font-medium text-gray-900 mb-4">Google Maps API</h3>
                    <div>
                        <label for="google_maps_api_key" class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                        <input type="text" 
                               id="google_maps_api_key" 
                               name="google_maps_api_key" 
                               value="{{ Setting::get('google_maps_api_key') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-200">
                    <h3 class="text-base font-medium text-gray-900 mb-4">Webhooks</h3>
                    <div>
                        <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                        <input type="url" 
                               id="webhook_url" 
                               name="webhook_url" 
                               value="{{ Setting::get('webhook_url') }}"
                               placeholder="https://example.com/webhook"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm hover:shadow">
                    Save Integrations
                </button>
            </form>
            </div>
        </div>
        </div>
    </div>

    {{-- Scroll to section when landing with hash (e.g. /admin/settings#email) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var hash = window.location.hash;
            if (hash) {
                var el = document.getElementById(hash.slice(1));
                if (el) {
                    setTimeout(function() {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                }
            }
        });
    </script>
</x-admin-layout>
