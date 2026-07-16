<x-admin-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">CMS Pages</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage Terms &amp; Conditions, Privacy Policy, and Contact Us for <strong>Client</strong> and <strong>Vendor</strong> apps. Save content in any language — the app chooses what to display.
            </p>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            @foreach($pages as $page)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $page->label }}</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $page->slug }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $page->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $page->is_active ? 'Active' : 'Hidden' }}
                        </span>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-4 flex-1">
                        Client + Vendor audiences
                        @if($page->isContactPage())
                            · website, email, WhatsApp, phone
                        @endif
                    </p>

                    <div class="mt-5 flex items-center gap-3">
                        <a href="{{ route('admin.cms-pages.edit', $page->slug) }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            Edit content
                        </a>
                        @if($page->slug === 'privacy-policy')
                            <a href="{{ route('legal.privacy-policy') }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800">Preview</a>
                        @elseif($page->slug === 'terms-and-conditions')
                            <a href="{{ route('legal.terms') }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800">Preview</a>
                        @else
                            <a href="{{ route('legal.contact') }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800">Preview</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
