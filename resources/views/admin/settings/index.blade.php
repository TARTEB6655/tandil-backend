@php
    use App\Models\Setting;
    $appName = Setting::get('app_name', config('app.name'));
    $logo = Setting::get('logo');
    $primaryColor = Setting::get('primary_color', '#6366f1');
    $secondaryColor = Setting::get('secondary_color', '#8b5cf6');
@endphp

<x-admin-layout>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <h1 class="text-xl font-medium text-gray-900">Settings</h1>
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
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Application Settings & Branding</h2>
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

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                    Save Changes
                </button>
            </form>
        </div>

        <!-- Contact Information -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Contact Information</h2>
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
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                    Save Contact Info
                </button>
            </form>
        </div>

        <!-- Social Links -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Social Media Links</h2>
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
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                    Save Social Links
                </button>
            </form>
        </div>

        <!-- Payment Settings -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Payment Gateway Settings</h2>
            <form method="POST" action="{{ route('admin.settings.payment') }}" class="space-y-4">
                @csrf
                
                <div>
                    <label for="payment_gateway" class="block text-sm font-medium text-gray-700 mb-2">Payment Gateway</label>
                    <select id="payment_gateway" 
                            name="payment_gateway" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            required>
                        <option value="stripe" {{ Setting::get('payment_gateway') === 'stripe' ? 'selected' : '' }}>Stripe</option>
                        <option value="paymob" {{ Setting::get('payment_gateway') === 'paymob' ? 'selected' : '' }}>PayMob</option>
                        <option value="ccavenue" {{ Setting::get('payment_gateway') === 'ccavenue' ? 'selected' : '' }}>CCAvenue</option>
                        <option value="tap" {{ Setting::get('payment_gateway') === 'tap' ? 'selected' : '' }}>Tap Payments</option>
                    </select>
                </div>
                <div>
                    <label for="api_key" class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                    <input type="text" 
                           id="api_key" 
                           name="api_key" 
                           value="{{ Setting::get('payment_api_key') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           required>
                </div>
                <div>
                    <label for="api_secret" class="block text-sm font-medium text-gray-700 mb-2">API Secret</label>
                    <input type="password" 
                           id="api_secret" 
                           name="api_secret" 
                           value="{{ Setting::get('payment_api_secret') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           required>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                    Save Payment Settings
                </button>
            </form>
        </div>

        <!-- Email Settings -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Email Settings (SMTP)</h2>
            <form method="POST" action="{{ route('admin.settings.email') }}" class="space-y-4">
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
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                    Save Email Settings
                </button>
            </form>
        </div>

        <!-- Notification Settings -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Push Notification Settings</h2>
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
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                    Save Notification Settings
                </button>
            </form>
        </div>

        <!-- Email Templates -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-medium text-gray-900">Email Templates</h2>
                <a href="{{ route('admin.settings.email-templates') }}" 
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                    Manage Templates
                </a>
            </div>
            <p class="text-sm text-gray-500">Edit email templates for order confirmations, user registrations, and more.</p>
        </div>

        <!-- Security Settings -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Security Settings</h2>
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

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                    Save Security Settings
                </button>
            </form>
        </div>

        <!-- Integrations -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Integrations</h2>
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

                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                    Save Integrations
                </button>
            </form>
        </div>
    </div>
</x-admin-layout>
