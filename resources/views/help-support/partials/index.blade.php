<div class="mb-4 sm:mb-6">
    <h1 class="text-lg sm:text-xl font-medium text-gray-900">Help & Support</h1>
    <p class="mt-1 text-xs sm:text-sm text-gray-500">{{ $tagline ?? 'FAQs, contact info, and submit a ticket. Chat with admin on your tickets.' }}</p>
</div>
@if(session('success'))
    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 rounded-md"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
@endif

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
                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-live-chat'))" class="block w-full text-left">
                        <p class="font-medium text-indigo-600">{{ $opt['title'] }}</p>
                        <p class="text-sm text-gray-600">{{ $opt['subtitle'] }} — use the chat button bottom-right</p>
                    </button>
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

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
    <h2 class="text-base font-medium text-gray-900 mb-3">Contact Information</h2>
    <ul class="space-y-2 text-sm text-gray-600">
        <li><strong>Phone:</strong> {{ $contactInfo['phone'] }}</li>
        <li><strong>Email:</strong> <a href="mailto:{{ $contactInfo['email'] }}" class="text-indigo-600 hover:text-indigo-900">{{ $contactInfo['email'] }}</a></li>
        <li><strong>Hours:</strong> {{ $contactInfo['support_hours'] }}</li>
    </ul>
</div>

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

<div id="submit-ticket" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-6">
    <h2 class="text-base font-medium text-gray-900 mb-1">Submit a support ticket</h2>
    <p class="text-xs text-gray-500 mb-3">Required fields: Subject, Email, Description.</p>
    <form action="{{ route($routePrefix . '.help-support.store') }}" method="POST" class="space-y-4 max-w-xl">
        @csrf
        <div>
            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
            <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', auth()->user()->email) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
            <textarea name="description" id="description" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description') }}</textarea>
            @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Submit ticket</button>
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-medium text-gray-900">My Support Tickets</h2>
        <span class="text-xs text-gray-500">{{ $tickets->count() }} total</span>
    </div>
    <div class="space-y-3">
        @forelse($tickets as $ticket)
            <a href="{{ route($routePrefix . '.help-support.show', $ticket->id) }}" class="block border border-gray-200 rounded-lg p-3 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $ticket->subject }}</p>
                        <p class="text-xs text-gray-500 mt-1">#{{ $ticket->ticket_number }} · {{ $ticket->email }}</p>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs font-medium
                        {{ $ticket->status === 'open' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $ticket->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $ticket->status === 'resolved' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $ticket->status === 'closed' ? 'bg-gray-100 text-gray-700' : '' }}
                    ">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</span>
                </div>
                <p class="text-sm text-gray-600 mt-2 line-clamp-2">{{ $ticket->message }}</p>
                <p class="text-xs text-gray-400 mt-2">{{ $ticket->created_at?->diffForHumans() }} · {{ $ticket->replies_count }} replies</p>
            </a>
        @empty
            <p class="text-sm text-gray-500">No tickets yet. Submit your first ticket above.</p>
        @endforelse
    </div>
</div>
