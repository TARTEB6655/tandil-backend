<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use Illuminate\Http\Request;

class TipController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = Tip::with('creator');

        // Search
        if ($request->has('search') && $request->search) {
            $query->where('title', 'LIKE', "%{$request->search}%")
                  ->orWhere('content', 'LIKE', "%{$request->search}%");
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $tips = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.tips.index', compact('tips'));
    }

    public function create()
    {
        return view('admin.tips.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:weekly,monthly,seasonal,general',
            'status' => 'required|in:draft,published,archived',
            'language' => 'required|in:en,ar,ur',
            'scheduled_at' => 'nullable|date',
        ]);

        $validated['created_by'] = auth()->id();

        Tip::create($validated);

        return redirect()->route('admin.tips.index')
            ->with('success', 'Tip created successfully.');
    }

    public function show($id)
    {
        $tip = Tip::with('creator')->findOrFail($id);
        return view('admin.tips.show', compact('tip'));
    }

    public function edit($id)
    {
        $tip = Tip::findOrFail($id);
        return view('admin.tips.edit', compact('tip'));
    }

    public function update(Request $request, $id)
    {
        $tip = Tip::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:weekly,monthly,seasonal,general',
            'status' => 'required|in:draft,published,archived',
            'language' => 'required|in:en,ar,ur',
            'scheduled_at' => 'nullable|date',
        ]);

        $tip->update($validated);

        return redirect()->route('admin.tips.index')
            ->with('success', 'Tip updated successfully.');
    }

    public function destroy($id)
    {
        $tip = Tip::findOrFail($id);
        $tip->delete();

        return redirect()->route('admin.tips.index')
            ->with('success', 'Tip deleted successfully.');
    }
}
