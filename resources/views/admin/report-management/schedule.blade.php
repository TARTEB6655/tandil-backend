<x-admin-layout>
    <div class="space-y-6">
        <div class="mb-6">
            <a href="{{ route('admin.report-management.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Report Management
            </a>
            <h1 class="text-xl font-semibold text-gray-900">Schedule Report</h1>
            <p class="mt-1 text-sm text-gray-500">Schedule a report to be generated at a future time, optionally recurring.</p>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                <ul class="list-disc list-inside text-sm">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-6 max-w-2xl">
            <form method="POST" action="{{ route('admin.report-management.schedule.store') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="e.g. Weekly Performance Report">
                    </div>
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Report type <span class="text-red-500">*</span></label>
                        <select name="type" id="type" required class="mt-1 block w-full rounded-md border-gray-300">
                            @foreach(\App\Models\AdminReport::TYPES as $t)
                                <option value="{{ $t }}" {{ old('type') == $t ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="scheduled_at" class="block text-sm font-medium text-gray-700">Scheduled at <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="scheduled_at" id="scheduled_at" value="{{ old('scheduled_at') }}" required
                               class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label for="recurrence" class="block text-sm font-medium text-gray-700">Recurrence</label>
                        <select name="recurrence" id="recurrence" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">None</option>
                            @foreach(\App\Models\AdminReport::RECURRENCE as $r)
                                <option value="{{ $r }}" {{ old('recurrence') == $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="format" class="block text-sm font-medium text-gray-700">Format</label>
                        <select name="parameters[format]" id="format" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="pdf" {{ (old('parameters.format') ?? 'pdf') == 'pdf' ? 'selected' : '' }}>PDF</option>
                            <option value="excel" {{ old('parameters.format') == 'excel' ? 'selected' : '' }}>Excel</option>
                            <option value="csv" {{ old('parameters.format') == 'csv' ? 'selected' : '' }}>CSV</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                        Schedule Report
                    </button>
                    <a href="{{ route('admin.report-management.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
