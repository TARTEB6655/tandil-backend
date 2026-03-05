<x-supervisor-layout>
    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('supervisor.team.index') }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ $member['name'] }}</h1>
            <p class="text-sm text-gray-500">{{ $member['employee_id'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-4">
                @if(!empty($member['profile_picture_url']))
                    <img src="{{ $member['profile_picture_url'] }}" alt="" class="h-16 w-16 rounded-full object-cover">
                @else
                    <div class="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-lg font-medium">
                        {{ strtoupper(substr($member['name'], 0, 2)) }}
                    </div>
                @endif
                <div>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium {{ $member['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $member['status'] }}
                    </span>
                    <p class="mt-2 text-gray-600">Current activity: {{ $member['current_activity'] }}</p>
                    <p class="text-sm text-gray-500">Tasks: {{ $member['tasks_display'] }}</p>
                </div>
            </div>
        </div>
        <dl class="divide-y divide-gray-100">
            <div class="px-6 py-4 flex justify-between">
                <dt class="text-sm text-gray-500">Email</dt>
                <dd class="text-sm text-gray-900">{{ $member['email'] ?? '—' }}</dd>
            </div>
            <div class="px-6 py-4 flex justify-between">
                <dt class="text-sm text-gray-500">Phone</dt>
                <dd class="text-sm text-gray-900">{{ $member['phone'] ?? '—' }}</dd>
            </div>
        </dl>
    </div>
</x-supervisor-layout>
