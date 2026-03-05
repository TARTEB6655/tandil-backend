<x-supervisor-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">My Team</h1>
        <p class="mt-1 text-sm text-gray-500">Technicians in your zones (linked at setup).</p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3">{{ session('error') }}</div>
    @endif

    @if(empty($teamMembers))
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-500">
            <p>No team members in your zones yet. Ask admin to assign technicians to your zones (Zone Assignment).</p>
            <a href="{{ route('supervisor.dashboard') }}" class="mt-4 inline-block text-indigo-600 hover:underline">Back to Dashboard</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($teamMembers as $member)
                <a href="{{ route('supervisor.team.show', $member['id']) }}" class="block bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all duration-200">
                    <div class="flex items-start gap-4">
                        @if(!empty($member['profile_picture_url']))
                            <img src="{{ $member['profile_picture_url'] }}" alt="" class="h-12 w-12 rounded-full object-cover flex-shrink-0">
                        @else
                            <div class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-medium flex-shrink-0">
                                {{ strtoupper(substr($member['name'], 0, 2)) }}
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-900 truncate">{{ $member['name'] }}</p>
                            <p class="text-xs text-gray-500">{{ $member['employee_id'] }}</p>
                            <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-medium {{ $member['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $member['status'] }}
                            </span>
                            <p class="mt-2 text-sm text-gray-600 truncate" title="{{ $member['current_activity'] }}">{{ $member['current_activity'] }}</p>
                            <p class="mt-1 text-xs text-gray-500">Tasks: {{ $member['tasks_display'] }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-supervisor-layout>
