<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ParsesMultipartPhoto;
use App\Models\MaintenancePhoto;
use App\Services\MaintenancePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaintenancePhotoController extends Controller
{
    use ParsesMultipartPhoto;

    public function __construct(private readonly MaintenancePhotoService $photos)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $photos = MaintenancePhoto::query()
            ->orderBy('priority')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $photos->getCollection()
            ->map(fn (MaintenancePhoto $photo) => $this->photos->toApiItem($photo))
            ->values()
            ->all();

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

    public function show(int $id): JsonResponse
    {
        $photo = MaintenancePhoto::find($id);
        if ($photo === null) {
            return ApiResponse::error('Photo not found.', 404);
        }

        return ApiResponse::success('Maintenance photo retrieved.', $this->photos->toApiItem($photo));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->storeRules());

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $photo = $this->photos->store(
            $request->file('before_image'),
            $request->file('after_image'),
            $this->payloadFromRequest($request)
        );

        return ApiResponse::success('Maintenance photo created.', $this->photos->toApiItem($photo), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $photo = MaintenancePhoto::find($id);
        if ($photo === null) {
            return ApiResponse::error('Photo not found.', 404);
        }

        $this->parseMultipartFilesIfNeeded($request);

        $validator = Validator::make($request->all(), $this->updateRules());

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $photo = $this->photos->update(
            $photo,
            $request->file('before_image'),
            $request->file('after_image'),
            $this->payloadFromRequest($request, true)
        );

        return ApiResponse::success('Maintenance photo updated.', $this->photos->toApiItem($photo));
    }

    public function destroy(int $id): JsonResponse
    {
        $photo = MaintenancePhoto::find($id);
        if ($photo === null) {
            return ApiResponse::error('Photo not found.', 404);
        }

        $this->photos->delete($photo);

        return ApiResponse::success('Maintenance photo deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'before_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'after_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'priority' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'before_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'after_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'priority' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
        ];
    }

    /**
     * @return array{title?: ?string, priority?: int, active?: bool}
     */
    private function payloadFromRequest(Request $request, bool $forUpdate = false): array
    {
        $payload = [];

        if ($request->has('title')) {
            $payload['title'] = $request->input('title') !== '' ? $request->input('title') : null;
        } elseif (! $forUpdate) {
            $payload['title'] = null;
        }

        if ($request->has('priority')) {
            $payload['priority'] = (int) $request->input('priority');
        } elseif (! $forUpdate) {
            $payload['priority'] = 0;
        }

        if ($request->has('active')) {
            $payload['active'] = $request->boolean('active');
        } elseif (! $forUpdate) {
            $payload['active'] = true;
        }

        return $payload;
    }

    private function parseMultipartFilesIfNeeded(Request $request): void
    {
        $contentType = $request->header('Content-Type', '');
        if (! str_contains($contentType, 'multipart/form-data') || ! ($request->isMethod('PUT') || $request->isMethod('POST'))) {
            return;
        }

        $this->parseMultipartIntoRequest($request, 'before_image');
        $this->parseMultipartIntoRequest($request, 'after_image');
    }
}
