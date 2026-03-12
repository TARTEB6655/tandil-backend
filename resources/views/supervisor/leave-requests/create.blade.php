<x-supervisor-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Apply for Leave</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Submit a leave request for HR approval. Once approved, your attendance status will show as On Leave.</p>
    </div>

    @if($errors->any())
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <ul class="text-xs sm:text-sm text-red-700 list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm max-w-xl">
        <form action="{{ route('supervisor.leave-requests.store') }}" method="POST" class="p-4 sm:p-6">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="leave_type" class="block text-sm font-medium text-gray-700 mb-1">Leave type</label>
                    <select name="leave_type" id="leave_type" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type['value'] }}" {{ old('leave_type') === $type['value'] ? 'selected' : '' }}>{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start date</label>
                        <input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End date</label>
                        <input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                    <textarea name="reason" id="reason" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Brief reason for leave">{{ old('reason') }}</textarea>
                </div>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700">Submit Request</button>
                <a href="{{ route('supervisor.leave-requests.index') }}" class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200">Cancel</a>
            </div>
        </form>
    </div>
</x-supervisor-layout>
