<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Services\VisitPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenancePhotoController extends Controller
{
    public function __construct(private readonly VisitPhotoService $visitPhotoService)
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request): View
    {
        $query = VisitPhoto::query()
            ->with([
                'visit:id,subscription_id,scheduled_date,status',
                'visit.subscription.client:id,name,email',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('visit_id')) {
            $query->where('visit_id', (int) $request->visit_id);
        }

        if ($request->filled('client_id')) {
            $clientId = (int) $request->client_id;
            $query->whereHas('visit.subscription', fn ($q) => $q->where('client_id', $clientId));
        }

        $photos = $query->paginate(20)->withQueryString();

        $visits = Visit::query()
            ->with(['subscription.client:id,name,email'])
            ->orderByDesc('scheduled_date')
            ->limit(200)
            ->get(['id', 'subscription_id', 'scheduled_date', 'status']);

        return view('admin.maintenance-photos.index', compact('photos', 'visits'));
    }

    public function create(): View
    {
        $visits = Visit::query()
            ->with(['subscription.client:id,name,email'])
            ->orderByDesc('scheduled_date')
            ->limit(200)
            ->get(['id', 'subscription_id', 'scheduled_date', 'status']);

        return view('admin.maintenance-photos.create', compact('visits'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visit_id' => ['required', 'integer', 'exists:visits,id'],
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'type' => ['nullable', 'string', 'in:before,during,after'],
            'show_on_client_app' => ['nullable', 'boolean'],
        ]);

        $visit = Visit::findOrFail((int) $validated['visit_id']);

        $this->visitPhotoService->storeForVisit(
            $visit,
            $request->file('photo'),
            $validated['type'] ?? 'after',
            $request->boolean('show_on_client_app', true),
        );

        return redirect()
            ->route('admin.maintenance-photos.index')
            ->with('success', 'Maintenance photo uploaded. It will appear on the client app when visible is enabled.');
    }

    public function edit(int $id): View
    {
        $photo = VisitPhoto::with([
            'visit.subscription.client:id,name,email',
        ])->findOrFail($id);

        return view('admin.maintenance-photos.edit', compact('photo'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $photo = VisitPhoto::findOrFail($id);

        $validated = $request->validate([
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'type' => ['required', 'string', 'in:before,during,after'],
            'show_on_client_app' => ['nullable', 'boolean'],
        ]);

        $this->visitPhotoService->updatePhoto(
            $photo,
            $request->file('photo'),
            $validated['type'],
            $request->boolean('show_on_client_app', true),
        );

        return redirect()
            ->route('admin.maintenance-photos.index')
            ->with('success', 'Maintenance photo updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $photo = VisitPhoto::findOrFail($id);
        $this->visitPhotoService->deletePhoto($photo);

        return redirect()
            ->route('admin.maintenance-photos.index')
            ->with('success', 'Maintenance photo deleted.');
    }
}
