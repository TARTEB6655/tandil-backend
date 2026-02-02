<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to Dashboard
                </a>
                <h1 class="text-xl font-semibold text-gray-900">Tips Management</h1>
                <p class="mt-1 text-sm text-gray-500">Create, edit, publish, and manage tips for users.</p>
            </div>
            <a href="{{ route('admin.tips.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium text-sm shadow-sm">
                Create New Tip
            </a>
        </div>
        <!-- Success Message -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('admin.tips.index') }}" class="flex gap-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tips..." class="flex-1 rounded-md border-gray-300">
                <select name="status" class="rounded-md border-gray-300">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>Published</option>
                    <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
                <select name="type" class="rounded-md border-gray-300">
                    <option value="">All Types</option>
                    <option value="weekly" {{ request('type') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                    <option value="monthly" {{ request('type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="seasonal" {{ request('type') == 'seasonal' ? 'selected' : '' }}>Seasonal</option>
                    <option value="general" {{ request('type') == 'general' ? 'selected' : '' }}>General</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filter</button>
                <a href="{{ route('admin.tips.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Clear</a>
            </form>
        </div>

        <!-- Tips Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Language</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($tips as $tip)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $tip->title }}</div>
                                <div class="text-sm text-gray-500 truncate max-w-xs">{{ Str::limit($tip->content, 50) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ ucfirst($tip->type) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ strtoupper($tip->language) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $tip->status == 'published' ? 'bg-green-100 text-green-800' : 
                                       ($tip->status == 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ ucfirst($tip->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $tip->scheduled_at ? $tip->scheduled_at->format('M d, Y') : 'Not scheduled' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.tips.show', $tip) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    <a href="{{ route('admin.tips.edit', $tip) }}" class="text-yellow-600 hover:text-yellow-900">Edit</a>
                                    <form action="{{ route('admin.tips.toggle-status', $tip) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" 
                                                class="text-xs px-2 py-1 rounded {{ $tip->status === 'published' ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }} transition-colors">
                                            {{ $tip->status === 'published' ? 'Unpublish' : 'Publish' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.tips.destroy', $tip) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No tips found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $tips->links() }}
        </div>
    </div>
</x-admin-layout>

