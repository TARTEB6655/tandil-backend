<x-admin-layout>
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Email Templates</h1>
                <p class="text-sm text-gray-500 mt-1">Manage email templates for notifications and communications</p>
            </div>
            <a href="{{ route('admin.settings.index') }}" 
               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                ← Back to Settings
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        <!-- Email Templates List -->
        <div class="space-y-4">
            @forelse($templates as $template)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-base font-medium text-gray-900">{{ $template->name }}</h3>
                            <p class="text-sm text-gray-500 mt-1">Key: {{ $template->key }}</p>
                        </div>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $template->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    
                    <form method="POST" action="{{ route('admin.settings.email-template.update', $template->id) }}" class="space-y-4">
                        @csrf
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                            <input type="text" 
                                   name="subject" 
                                   value="{{ old('subject', $template->subject) }}"
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Body</label>
                            <textarea name="body" 
                                      rows="10"
                                      required
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm">{{ old('body', $template->body) }}</textarea>
                            @if($template->variables)
                                <p class="mt-2 text-xs text-gray-500">
                                    Available variables: {{ implode(', ', json_decode($template->variables, true) ?? []) }}
                                </p>
                            @endif
                        </div>
                        
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                            Update Template
                        </button>
                    </form>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">No email templates found</h3>
                    <p class="text-sm text-gray-500">Email templates will appear here once created.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-admin-layout>

