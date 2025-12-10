<x-hr-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Employee Management</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Manage all employee records.</p>
            </div>
            <a href="{{ route('hr.employees.create') }}" 
               class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 text-center">
                Add Employee
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Search Bar -->
    <div class="mb-4 sm:mb-6">
        <form action="{{ route('hr.employees.index') }}" method="GET" class="w-full">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="h-4 w-4 sm:h-5 sm:w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" 
                       name="search" 
                       value="{{ $search ?? '' }}" 
                       placeholder="Search by name, email, employee ID, designation..."
                       class="w-full pl-10 pr-10 py-2 text-xs sm:text-sm bg-gray-50 border border-gray-200 rounded-lg
                              focus:outline-none focus:ring-1 focus:ring-gray-300 focus:border-gray-300 focus:bg-white
                              transition-all duration-200">
                @if($search ?? '')
                    <button type="button"
                            onclick="window.location.href='{{ route('hr.employees.index') }}'"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition-colors duration-200"
                            aria-label="Clear search">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </form>
    </div>

    <!-- Employees Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-medium text-gray-900">All Employees</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Email</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Designation</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Role</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($employees as $employee)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">
                                {{ $employee->employee_id }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                {{ $employee->user ? $employee->user->name : 'N/A' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                {{ $employee->user ? $employee->user->email : 'N/A' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                {{ $employee->designation ?? 'N/A' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden lg:table-cell">
                                @if($employee->user)
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                        {{ ucfirst(str_replace('_', ' ', $employee->user->role)) }}
                                    </span>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('hr.employees.show', $employee->id) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">
                                        View
                                    </a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('hr.employees.edit', $employee->id) }}" 
                                       class="text-yellow-600 hover:text-yellow-900">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No employees found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($employees->hasPages())
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200 bg-gray-50">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-0">
                    <!-- Results Info -->
                    <div class="text-xs sm:text-sm text-gray-600">
                        Showing 
                        <span class="font-medium text-gray-900">{{ $employees->firstItem() ?? 0 }}</span>
                        to 
                        <span class="font-medium text-gray-900">{{ $employees->lastItem() ?? 0 }}</span>
                        of 
                        <span class="font-medium text-gray-900">{{ $employees->total() }}</span>
                        results
                    </div>
                    
                    <!-- Pagination Links -->
                    <div class="flex items-center justify-center">
                        {{ $employees->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        @elseif($employees->count() > 0)
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200 bg-gray-50">
                <div class="text-xs sm:text-sm text-gray-600 text-center">
                    Showing all 
                    <span class="font-medium text-gray-900">{{ $employees->count() }}</span>
                    {{ $employees->count() === 1 ? 'result' : 'results' }}
                </div>
            </div>
        @endif
    </div>
</x-hr-layout>

