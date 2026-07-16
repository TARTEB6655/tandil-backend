@php
    $translations = old('translations', $page->translations ?? []);
    $contact = old('contact_details', $page->contact_details ?? []);
    $localeLabels = ['en' => 'English', 'ar' => 'Arabic', 'ur' => 'Urdu'];
@endphp

<x-admin-layout>
    <div class="space-y-6" x-data="{ activeLocale: 'en' }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('admin.cms-pages.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to CMS Pages</a>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100 mt-2">Edit {{ $page->label }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Save content per language. The frontend/app decides which language to show.
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.cms-pages.update', $page->slug) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <div class="flex flex-wrap items-center gap-3 mb-6">
                    @foreach($locales as $locale)
                        <button type="button"
                                @click="activeLocale = '{{ $locale }}'"
                                :class="activeLocale === '{{ $locale }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            {{ $localeLabels[$locale] ?? strtoupper($locale) }}
                        </button>
                    @endforeach
                </div>

                @foreach($locales as $locale)
                    <div x-show="activeLocale === '{{ $locale }}'" x-cloak class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title ({{ strtoupper($locale) }})</label>
                            <input type="text"
                                   name="translations[{{ $locale }}][title]"
                                   value="{{ $translations[$locale]['title'] ?? '' }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content ({{ strtoupper($locale) }})</label>
                            <textarea name="translations[{{ $locale }}][body]"
                                      rows="14"
                                      class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm"
                                      placeholder="HTML or plain text">{{ $translations[$locale]['body'] ?? '' }}</textarea>
                            <p class="mt-2 text-xs text-gray-500">You can use basic HTML tags like &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;, &lt;li&gt;.</p>
                        </div>

                        @if($page->isContactPage())
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Working hours ({{ strtoupper($locale) }})</label>
                                    <input type="text"
                                           name="contact_details[working_hours][{{ $locale }}]"
                                           value="{{ $contact['working_hours'][$locale] ?? '' }}"
                                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                           placeholder="Mon–Sat, 9:00 AM – 6:00 PM">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Service areas ({{ strtoupper($locale) }})</label>
                                    <textarea name="contact_details[service_areas][{{ $locale }}]"
                                              rows="3"
                                              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                              placeholder="Dubai, Sharjah, Abu Dhabi">{{ $contact['service_areas'][$locale] ?? '' }}</textarea>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($page->isContactPage())
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Contact details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
                            <input type="text" name="contact_details[phone]" value="{{ $contact['phone'] ?? '' }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">WhatsApp</label>
                            <input type="text" name="contact_details[whatsapp]" value="{{ $contact['whatsapp'] ?? '' }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                            <input type="email" name="contact_details[email]" value="{{ $contact['email'] ?? '' }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $page->is_active)) class="rounded border-gray-300 text-indigo-600">
                    Page is active (visible in app/website)
                </label>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
