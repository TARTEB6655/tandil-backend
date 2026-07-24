<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PhoneController extends Controller
{
    public function edit(): View
    {
        $user = Auth::user();

        return view('client.phone.edit', [
            'user' => $user,
            'needsPhone' => $user->needsPhone(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($request->filled('phone_number') && ! $request->filled('phone')) {
            $request->merge(['phone' => $request->input('phone_number')]);
        }

        $validated = $request->validate([
            'phone' => [
                'required',
                'string',
                'min:7',
                'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
        ]);

        $user->phone = trim($validated['phone']);
        $user->save();

        return redirect()
            ->route('client.phone.edit')
            ->with('status', 'phone-updated');
    }
}
