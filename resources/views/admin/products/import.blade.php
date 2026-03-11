<x-admin-layout>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900">{{ __('admin.import_products') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('admin.import_products_description') }}</p>
            </div>
            <a href="{{ route('admin.products.index') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Products
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-red-800 mb-2">{{ __('admin.import_errors') }}</p>
                        <ul class="text-sm text-red-700 list-disc list-inside space-y-1 max-h-60 overflow-y-auto">
                            @foreach(session('import_errors') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Import Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Upload CSV File</h2>
                    
                    <form method="POST" action="{{ route('admin.products.import.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        
                        <div>
                            <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-2">
                                CSV File
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-indigo-400 transition-colors">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="csv_file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                            <span>Upload a file</span>
                                            <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt" class="sr-only" required>
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">CSV, TXT up to 10MB</p>
                                </div>
                            </div>
                            @error('csv_file')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center">
                            <input id="update_existing" 
                                   name="update_existing" 
                                   type="checkbox" 
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="update_existing" class="ml-2 block text-sm text-gray-900">
                                Update existing products (by name)
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                            <a href="{{ route('admin.products.index') }}" 
                               class="px-4 py-2 bg-white text-gray-700 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                {{ __('admin.import_products') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Instructions -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sticky top-6">
                    <h3 class="text-base font-medium text-gray-900 mb-4">CSV Format</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Required Columns:</p>
                            <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
                                <li>Name (required)</li>
                                <li>Description (optional)</li>
                                <li>Category (optional)</li>
                                <li>Price (required)</li>
                                <li>Stock (optional, default: 0)</li>
                                <li>Image Path (optional)</li>
                            </ul>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Example CSV:</p>
                            <div class="bg-gray-50 rounded-md p-3 text-xs font-mono text-gray-700 overflow-x-auto">
                                Name,Description,Category,Price,Stock<br>
                                "Product 1","Description here","Electronics",99.99,50<br>
                                "Product 2","Another product","Clothing",49.99,100
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-200">
                            <a href="{{ route('admin.products.export') }}" 
                               class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                {{ __('admin.download_sample_csv') }}
                            </a>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <p class="text-xs text-blue-800">
                                <strong>Note:</strong> The first row should contain column headers. Categories will be created automatically if they don't exist.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // File input preview
        const fileInput = document.getElementById('csv_file');
        const dropZone = fileInput.closest('.border-dashed');
        
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                dropZone.classList.remove('border-gray-300');
                dropZone.classList.add('border-indigo-400', 'bg-indigo-50');
            }
        });

        // Drag and drop
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.classList.add('border-indigo-400', 'bg-indigo-50');
        });

        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            if (!fileInput.files.length) {
                dropZone.classList.remove('border-indigo-400', 'bg-indigo-50');
            }
        });

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length > 0 && (files[0].type === 'text/csv' || files[0].name.endsWith('.csv') || files[0].name.endsWith('.txt'))) {
                fileInput.files = files;
                dropZone.classList.add('border-indigo-400', 'bg-indigo-50');
            }
        });
    </script>
    @endpush
</x-admin-layout>

