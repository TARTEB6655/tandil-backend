<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendor Registration</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-3xl mx-auto bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
        <h1 class="text-xl font-semibold text-gray-900">Become a vendor</h1>
        <p class="text-sm text-gray-500 mt-1">Create your business account. You can upload documents now or during onboarding — all required fields and documents must be completed before you can submit for admin approval.</p>
        @if(session('success'))
            <div class="mt-4 p-3 bg-green-50 text-green-800 text-sm rounded-lg">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mt-4 p-3 bg-red-50 text-red-800 text-sm rounded-lg">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="POST" action="{{ route('vendor.register.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
            @csrf

            @include('vendor.partials.business-profile-fields', ['profile' => null, 'vendorTypes' => $vendorTypes, 'emirates' => $emirates, 'requireLogo' => true])

            @if($categories->isNotEmpty())
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">Categories <span class="text-gray-400">(optional now — required before submission)</span></p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($categories as $category)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', []))) class="rounded border-gray-300" />
                                {{ $category->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 border-t border-gray-200 pt-4">
                <div><label class="block text-sm font-medium text-gray-700">Password *</label><input type="password" name="password" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
                <div><label class="block text-sm font-medium text-gray-700">Confirm Password *</label><input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="terms_accepted" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600" @checked(old('terms_accepted')) required />
                    <span>I accept the <a href="{{ route('legal.privacy-policy') }}" target="_blank" class="text-indigo-600 underline">Terms &amp; Conditions</a>. *</span>
                </label>
            </div>

            <button type="submit" class="w-full py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800">Create account</button>
        </form>
    </div>
</body>
</html>
