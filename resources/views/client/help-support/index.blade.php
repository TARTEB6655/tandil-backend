<x-client-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Help & Support</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">{{ $tagline ?? 'FAQs, contact info, and submit a ticket. Same as API /api/support/help-center.' }}</p>
    </div>
    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 rounded-md"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
    @endif

    <!-- Get Support (same as API get_support: Call, Email, Live Chat, Submit Ticket) -->
    @if(!empty($getSupport))
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
        <h2 class="text-base font-medium text-gray-900 mb-3">{{ $heading ?? 'How can we help you?' }}</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($getSupport as $opt)
                <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 transition-colors">
                    @if($opt['type'] === 'call' && !empty($opt['value']))
                        <a href="tel:{{ preg_replace('/\s+/', '', $opt['value']) }}" class="block">
                            <p class="font-medium text-gray-900">{{ $opt['title'] }}</p>
                            <p class="text-sm text-gray-600">{{ $opt['subtitle'] }}</p>
                        </a>
                    @elseif($opt['type'] === 'email' && !empty($opt['value']))
                        <a href="mailto:{{ $opt['value'] }}" class="block">
                            <p class="font-medium text-gray-900">{{ $opt['title'] }}</p>
                            <p class="text-sm text-gray-600">{{ $opt['subtitle'] }}</p>
                        </a>
                    @elseif($opt['type'] === 'live_chat')
                        <div class="opacity-75">
                            <p class="font-medium text-gray-900">{{ $opt['title'] }}</p>
                            <p class="text-sm text-gray-600">{{ $opt['subtitle'] }} (coming soon)</p>
                        </div>
                    @else
                        <a href="#submit-ticket" class="block scroll-smooth">
                            <p class="font-medium text-indigo-600">{{ $opt['title'] }}</p>
                            <p class="text-sm text-gray-600">{{ $opt['subtitle'] }}</p>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Contact Information -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
        <h2 class="text-base font-medium text-gray-900 mb-3">Contact Information</h2>
        <ul class="space-y-2 text-sm text-gray-600">
            <li><strong>Phone:</strong> {{ $contactInfo['phone'] }}</li>
            <li><strong>Email:</strong> <a href="mailto:{{ $contactInfo['email'] }}" class="text-indigo-600 hover:text-indigo-900">{{ $contactInfo['email'] }}</a></li>
            <li><strong>Hours:</strong> {{ $contactInfo['support_hours'] }}</li>
        </ul>
    </div>

    <!-- FAQs -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
        <h2 class="text-base font-medium text-gray-900 mb-3">Frequently Asked Questions</h2>
        <div class="space-y-3">
            @forelse($faqs as $faq)
                <details class="group border border-gray-200 rounded-lg p-3">
                    <summary class="font-medium text-gray-900 cursor-pointer">{{ $faq->question }}</summary>
                    <p class="mt-2 text-sm text-gray-600">{{ $faq->answer }}</p>
                </details>
            @empty
                <p class="text-sm text-gray-500">No FAQs yet.</p>
            @endforelse
        </div>
    </div>

    <!-- Submit Ticket -->
    <div id="submit-ticket" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
        <h2 class="text-base font-medium text-gray-900 mb-3">Submit a support ticket</h2>
        <form action="{{ route('client.help-support.store') }}" method="POST" class="space-y-4 max-w-xl">
            @csrf
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                <textarea name="message" id="message" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Submit ticket</button>
        </form>
    </div>
</x-client-layout>
