<x-admin-layout>
    <div class="space-y-6">
        <div class="mb-6">
            <a href="{{ route('admin.report-management.show', $report->id) }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Report
            </a>
            <h1 class="text-xl font-semibold text-gray-900">Share Report</h1>
            <p class="mt-1 text-sm text-gray-500">Share "{{ $report->title }}" by email or get a temporary link.</p>
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
            <form method="POST" action="{{ route('admin.report-management.share.store', $report->id) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Method</label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="method" value="email" {{ old('method', 'email') === 'email' ? 'checked' : '' }} class="rounded border-gray-300">
                                <span class="ml-2 text-sm">Email</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="method" value="link" {{ old('method') === 'link' ? 'checked' : '' }} class="rounded border-gray-300">
                                <span class="ml-2 text-sm">Get share link</span>
                            </label>
                        </div>
                    </div>
                    <div id="recipients-wrap">
                        <label for="recipients" class="block text-sm font-medium text-gray-700">Recipient emails (comma or space separated)</label>
                        <textarea name="recipients" id="recipients" rows="3" class="mt-1 block w-full rounded-md border-gray-300"
                                  placeholder="email1@example.com, email2@example.com">{{ old('recipients') }}</textarea>
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700">Message (optional)</label>
                        <textarea name="message" id="message" rows="3" class="mt-1 block w-full rounded-md border-gray-300"
                                  placeholder="Optional message to include with the report.">{{ old('message', 'Please find the attached report.') }}</textarea>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                        Share
                    </button>
                    <a href="{{ route('admin.report-management.show', $report->id) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
