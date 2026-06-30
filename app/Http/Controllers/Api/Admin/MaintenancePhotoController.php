<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ParsesMultipartPhoto;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Services\VisitPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MaintenancePhotoController extends Controller
{
    use ParsesMultipartPhoto;

    public function __construct(private readonly VisitPhotoService $visitPhotoService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = VisitPhoto::query()
            ->with('visit:id,subscription_id,scheduled_date,status,completed_at')
            ->orderByDesc('created_at');

        if ($request->filled('visit_id')) {
            $query->where('visit_id', (int) $request->visit_id);
        }

        if ($request->filled('client_id')) {
            $clientId = (int) $request->client_id;
            $query->whereHas('visit.subscription', fn ($q) => $q->where('client_id', $clientId));
        }

        if ($request->has('show_on_client_app')) {
            $query->where('show_on_client_app', $request->boolean('show_on_client_app'));
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $photos = $query->paginate($perPage);

        $data = $photos->getCollection()->map(function (VisitPhoto $photo) {
            $visit = $photo->visit;
            $visitSummary = $visit ? [
                'id' => $visit->id,
                'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
                'status' => $visit->status,
                'status_display' => Str::title(str_replace('_', ' ', $visit->status ?? '')),
                'completed_at' => $visit->completed_at?->format('c'),
            ] : null;

            return $this->visitPhotoService->toApiItem($photo, $visitSummary);
        })->values()->all();

        return ApiResponse::success('Maintenance photos retrieved.', [
            'data' => $data,
            'pagination' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
                'per_page' => $photos->perPage(),
                'total' => $photos->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->parseMultipartIfNeeded($request, 'photo');

        $validator = Validator::make($request->all(), [
            'visit_id' => 'required|integer|exists:visits,id',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'type' => 'nullable|string|in:before,during,after',
            'show_on_client_app' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $visit = Visit::findOrFail((int) $request->input('visit_id'));
        $photo = $this->visitPhotoService->storeForVisit(
            $visit,
            $request->file('photo'),
            $request->input('type', 'after'),
            $request->boolean('show_on_client_app', true),
        );

        return ApiResponse::success('Maintenance photo uploaded.', $this->visitPhotoService->toApiItem($photo), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $photo = VisitPhoto::find($id);
        if (! $photo) {
            return ApiResponse::error('Photo not found.', 404);
        }

        $this->parseMultipartIfNeeded($request, 'photo');

        $validator = Validator::make($request->all(), [
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'type' => 'nullable|string|in:before,during,after',
            'show_on_client_app' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $photo = $this->visitPhotoService->updatePhoto(
            $photo,
            $request->file('photo'),
            $request->input('type'),
            $request->has('show_on_client_app') ? $request->boolean('show_on_client_app') : null,
        );

        return ApiResponse::success('Maintenance photo updated.', $this->visitPhotoService->toApiItem($photo));
    }

    public function destroy(int $id): JsonResponse
    {
        $photo = VisitPhoto::find($id);
        if (! $photo) {
            return ApiResponse::error('Photo not found.', 404);
        }

        $this->visitPhotoService->deletePhoto($photo);

        return ApiResponse::success('Maintenance photo deleted.');
    }

    private function parseMultipartIfNeeded(Request $request, string $fileField): void
    {
        $contentType = $request->header('Content-Type', '');
        if (str_contains($contentType, 'multipart/form-data') && ($request->isMethod('PUT') || $request->isMethod('POST'))) {
            $this->parseMultipartIntoRequest($request, $fileField);
        }
    }
}
