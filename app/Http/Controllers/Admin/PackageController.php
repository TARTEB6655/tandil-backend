<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\ImageCompressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PackageController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $packages = Package::orderBy('sort_order')->get()->map(function ($package) {
            $package->orders_count = $package->orders()->count();
            return $package;
        });
        return view('admin.packages.index', compact('packages'));
    }

    public function edit($id)
    {
        $package = Package::findOrFail($id);
        $package->orders_count = $package->orders()->count();
        return view('admin.packages.edit', compact('package'));
    }

    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);
        $request->validate([
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:20480',
            'is_active' => 'nullable|boolean',
        ]);

        $data = [
            'price' => $request->price,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('image')) {
            if ($package->image && Storage::disk('public')->exists($package->image)) {
                Storage::disk('public')->delete($package->image);
            }
            $data['image'] = $request->file('image')->store('packages', 'public');
            ImageCompressionService::compressIfNeededFromPublicPath($data['image']);
        }

        $package->update($data);
        return redirect()->route('admin.packages.index')
            ->with('success', 'Package updated successfully.');
    }
}
