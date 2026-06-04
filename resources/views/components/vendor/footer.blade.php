<footer class="flex-shrink-0 border-t border-gray-200 bg-white px-4 py-4 sm:px-6">
    <div class="mx-auto flex max-w-[1600px] flex-col items-center justify-between gap-2 text-center text-xs text-gray-500 sm:flex-row sm:text-start">
        <p>&copy; {{ date('Y') }} Tandil — Vendor Portal</p>
        <a href="{{ route('legal.privacy-policy') }}" target="_blank" rel="noopener noreferrer" class="font-medium text-indigo-600 hover:text-indigo-800">
            {{ __('admin.privacy_policy') }}
        </a>
    </div>
</footer>
