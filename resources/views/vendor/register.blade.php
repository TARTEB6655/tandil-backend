<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendor Registration</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-xl mx-auto bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
        <h1 class="text-xl font-semibold text-gray-900">Become a vendor</h1>
        <p class="text-sm text-gray-500 mt-1">Submit your business details. Admin will review your application.</p>
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
            <div><label class="block text-sm font-medium text-gray-700">Business name *</label><input name="business_name" value="{{ old('business_name') }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Owner name *</label><input name="owner_name" value="{{ old('owner_name') }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Email *</label><input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Phone</label><input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Address</label><textarea name="address" rows="2" class="mt-1 w-full rounded-lg border-gray-300">{{ old('address') }}</textarea></div>
            <div><label class="block text-sm font-medium text-gray-700">Tax / VAT number</label><input name="tax_vat_number" value="{{ old('tax_vat_number') }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Description</label><textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description') }}</textarea></div>
            <div><label class="block text-sm font-medium text-gray-700">Logo</label><input type="file" name="logo" accept="image/*" class="mt-1 w-full text-sm" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Trade license</label><input type="file" name="trade_license" accept=".pdf,image/*" class="mt-1 w-full text-sm" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Business proof</label><input type="file" name="business_proof" accept=".pdf,image/*" class="mt-1 w-full text-sm" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Password *</label><input type="password" name="password" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="block text-sm font-medium text-gray-700">Confirm password *</label><input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <button type="submit" class="w-full py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800">Submit registration</button>
        </form>
    </div>
</body>
</html>
