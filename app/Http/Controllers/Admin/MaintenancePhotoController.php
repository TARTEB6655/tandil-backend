<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePhoto;
use App\Services\MaintenancePhotoService;
use App\Support\MaintenancePhotoCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenancePhotoController extends Controller
{
    public function __construct(private readonly MaintenancePhotoService $photos)
    {
        $this->middleware('role:admin');
    }

    public function index(): View
    {
        $photos = MaintenancePhoto::query()
            ->orderBy('priority')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.maintenance-photos.index', compact('photos'));
    }

    public function create(): View
    {
        return view('admin.maintenance-photos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'before_image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'after_image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $this->photos->store(
            $request->file('before_image'),
            $request->file('after_image'),
            [
                'title' => $validated['title'] ?? null,
                'priority' => (int) ($validated['priority'] ?? 0),
                'active' => $request->boolean('active', true),
            ]
        );

        MaintenancePhotoCache::bumpVersion();

        return redirect()
            ->route('admin.maintenance-photos.index')
            ->with('success', 'Maintenance photo saved.');
    }

    public function edit(int $id): View
    {
        $photo = MaintenancePhoto::findOrFail($id);

        return view('admin.maintenance-photos.edit', compact('photo'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $photo = MaintenancePhoto::findOrFail($id);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'before_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'after_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $this->photos->update(
            $photo,
            $request->file('before_image'),
            $request->file('after_image'),
            [
                'title' => $validated['title'] ?? $photo->title,
                'priority' => array_key_exists('priority', $validated) ? (int) $validated['priority'] : $photo->priority,
                'active' => $request->boolean('active'),
            ]
        );

        MaintenancePhotoCache::bumpVersion();

        return redirect()
            ->route('admin.maintenance-photos.index')
            ->with('success', 'Maintenance photo updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $photo = MaintenancePhoto::findOrFail($id);
        $this->photos->delete($photo);
        MaintenancePhotoCache::bumpVersion();

        return redirect()
            ->route('admin.maintenance-photos.index')
            ->with('success', 'Maintenance photo deleted.');
    }
}
