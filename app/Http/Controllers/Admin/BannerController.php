<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        // Note: You'd need a banners table/model for production
        // For now, this is a placeholder structure
        
        $banners = []; // Banner::orderBy('priority', 'asc')->get();
        
        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'link' => 'nullable|url',
            'priority' => 'nullable|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        // Save banner
        // $banner = Banner::create([...]);
        
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created successfully');
    }

    public function edit($id)
    {
        // $banner = Banner::findOrFail($id);
        // return view('admin.banners.edit', compact('banner'));
        
        return view('admin.banners.edit');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'link' => 'nullable|url',
            'priority' => 'nullable|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        // Update banner
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated successfully');
    }

    public function destroy($id)
    {
        // $banner = Banner::findOrFail($id);
        // $banner->delete();
        
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner deleted successfully');
    }
}

