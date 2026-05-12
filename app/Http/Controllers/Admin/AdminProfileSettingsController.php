<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageCompressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminProfileSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        return view('admin.settings.profile', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'current_password' => 'nullable|required_with:password',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (! empty($validated['password'])) {
            if (! Hash::check($request->input('current_password'), $user->password)) {
                return back()
                    ->withErrors(['current_password' => __('admin.current_password_incorrect')])
                    ->withInput($request->except(['password', 'password_confirmation', 'current_password']));
            }
            $user->password = Hash::make($validated['password']);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($request->hasFile('profile_picture')) {
            $stored = $request->file('profile_picture')->store('profiles', 'public');
            $user->profile_picture = $stored;
            ImageCompressionService::compressIfNeededFromPublicPath($stored);
        }

        $user->save();

        return redirect()->route('admin.settings.index')->with('success', __('admin.profile_saved'));
    }
}
