<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * List addresses – same as API GET /api/user/addresses.
     */
    public function index()
    {
        $addresses = UserAddress::where('user_id', Auth::id())
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return view('client.addresses.index', compact('addresses'));
    }

    public function create()
    {
        return view('client.addresses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'street_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);
        $validated['user_id'] = Auth::id();
        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        if ($validated['is_default']) {
            UserAddress::where('user_id', Auth::id())->update(['is_default' => false]);
        }
        UserAddress::create($validated);
        return redirect()->route('client.addresses.index')->with('success', 'Address added successfully.');
    }

    public function edit($id)
    {
        $address = UserAddress::where('user_id', Auth::id())->findOrFail($id);
        return view('client.addresses.edit', compact('address'));
    }

    public function update(Request $request, $id)
    {
        $address = UserAddress::where('user_id', Auth::id())->findOrFail($id);
        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
            'street_address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'sometimes|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);
        $validated['is_default'] = $request->boolean('is_default');
        if ($validated['is_default']) {
            UserAddress::where('user_id', Auth::id())->update(['is_default' => false]);
        }
        $address->update($validated);
        return redirect()->route('client.addresses.index')->with('success', 'Address updated successfully.');
    }

    public function destroy($id)
    {
        $address = UserAddress::where('user_id', Auth::id())->findOrFail($id);
        $address->delete();
        return redirect()->route('client.addresses.index')->with('success', 'Address removed.');
    }
}
