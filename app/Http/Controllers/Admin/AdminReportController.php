<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AdminReportController extends Controller
{
    protected function fileUrl(AdminReport $report): ?string
    {
        return url('/api/admin/reports/' . $report->id . '/download');
    }

    protected function ensureReportFile(AdminReport $report): AdminReport
    {
        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            return $report;
        }

        $report->forceFill([
            'status' => 'pending',
            'failure_reason' => null,
        ])->save();

        GenerateReportJob::dispatchSync($report);

        return $report->fresh();
    }

    protected function transformReport(AdminReport $report, bool $includeCreatorEmail = false): array
    {
        $report->load('creator');
        $creator = [
            'id' => $report->creator->id,
            'name' => $report->creator->name,
        ];
        if ($includeCreatorEmail) {
            $creator['email'] = $report->creator->email;
        }
        return [
            'id' => $report->id,
            'title' => $report->title,
            'type' => $report->type,
            'status' => $report->status,
            'created_at' => $report->created_at->toIso8601String(),
            'scheduled_at' => $report->scheduled_at?->toIso8601String(),
            'generated_at' => $report->generated_at?->toIso8601String(),
            'recurrence' => $report->recurrence,
            'file_url' => $this->fileUrl($report),
            'file_size' => $report->file_size,
            'created_by' => $creator,
            'parameters' => $report->parameters,
        ];
    }

    /**
     * 1. Get All Reports
     * GET /api/admin/reports
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $query = AdminReport::with('creator')->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $paginator = $query->paginate($perPage);
        $data = collect($paginator->items())->map(fn ($r) => $this->transformReport($r))->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * 2. Get Single Report
     * GET /api/admin/reports/{id}
     */
    public function show(string $id): JsonResponse
    {
        $report = AdminReport::with('creator')->findOrFail($id);
        $data = $this->transformReport($report, true);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 3. Generate Report (async)
     * POST /api/admin/reports/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:' . implode(',', AdminReport::TYPES),
            'title' => 'required|string|max:255',
            'parameters' => 'nullable|array',
            'parameters.start_date' => 'nullable|date',
            'parameters.end_date' => 'nullable|date|after_or_equal:parameters.start_date',
            'parameters.format' => 'nullable|in:pdf,excel,csv',
            'parameters.include_charts' => 'nullable|boolean',
            'parameters.include_details' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $params = $request->input('parameters', []);
        $format = $params['format'] ?? 'pdf';

        $report = AdminReport::create([
            'title' => $request->title,
            'type' => $request->type,
            'status' => 'pending',
            'format' => $format,
            'parameters' => $params,
            'created_by' => $request->user()->id,
        ]);

        GenerateReportJob::dispatch($report);

        $data = $this->transformReport($report->load('creator'));
        return response()->json([
            'success' => true,
            'message' => 'Report generation started. You will be notified when it\'s ready.',
            'data' => $data,
        ], 201);
    }

    /**
     * 4. Schedule Report
     * POST /api/admin/reports/schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:' . implode(',', AdminReport::TYPES),
            'title' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'recurrence' => 'nullable|in:' . implode(',', AdminReport::RECURRENCE),
            'parameters' => 'nullable|array',
            'parameters.format' => 'nullable|in:pdf,excel,csv',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $params = $request->input('parameters', []);
        $format = $params['format'] ?? 'pdf';

        $report = AdminReport::create([
            'title' => $request->title,
            'type' => $request->type,
            'status' => 'scheduled',
            'scheduled_at' => $request->scheduled_at,
            'recurrence' => $request->recurrence,
            'format' => $format,
            'parameters' => $params,
            'created_by' => $request->user()->id,
        ]);

        $data = $this->transformReport($report->load('creator'));
        return response()->json([
            'success' => true,
            'message' => 'Report scheduled successfully',
            'data' => $data,
        ], 201);
    }

    /**
     * 5. Cancel Scheduled Report
     * DELETE /api/admin/reports/{id}/cancel
     */
    public function cancel(string $id): JsonResponse
    {
        $report = AdminReport::findOrFail($id);
        if ($report->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Only scheduled reports can be cancelled.',
            ], 400);
        }
        $report->delete();
        return response()->json([
            'success' => true,
            'message' => 'Scheduled report cancelled successfully',
        ]);
    }

    /**
     * 6. Download Report
     * GET /api/admin/reports/{id}/download
     */
    public function download(string $id)
    {
        $report = AdminReport::findOrFail($id);
        $report = $this->ensureReportFile($report);
        if (! $report->file_path || ! Storage::disk('local')->exists($report->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Report file not found.',
            ], 404);
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: $report->format;
        $mime = match (strtolower($ext)) {
            'csv' => 'text/csv',
            'xlsx', 'xls' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/pdf',
        };
        $filename = 'report-' . $report->id . '.' . $ext;

        $disk = Storage::disk('local');
        $fullPath = $disk->path($report->file_path);

        return response()->streamDownload(
            static function () use ($fullPath): void {
                $fp = fopen($fullPath, 'rb');
                if ($fp === false) {
                    return;
                }
                while (! feof($fp)) {
                    echo fread($fp, 8192);
                }
                fclose($fp);
            },
            $filename,
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * 7. Share Report
     * POST /api/admin/reports/{id}/share
     */
    public function share(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|in:email,link',
            'recipients' => 'required_if:method,email|array',
            'recipients.*' => 'email',
            'message' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $report = AdminReport::with('creator')->findOrFail($id);
        if ($report->status !== 'generated' || ! $report->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Only generated reports can be shared.',
            ], 400);
        }

        $method = $request->method;
        $data = [];

        if ($method === 'link') {
            $data['share_link'] = URL::temporarySignedRoute(
                'api.admin.reports.download',
                now()->addDays(7),
                ['id' => $report->id]
            );
        } else {
            $recipients = $request->recipients ?? [];
            $message = $request->input('message', 'Please find the attached report.');
            $downloadUrl = url('/api/admin/reports/' . $report->id . '/download');

            foreach ($recipients as $email) {
                try {
                    Mail::raw(
                        $message . "\n\nDownload: " . $downloadUrl . "\n\nReport: " . $report->title,
                        function ($mail) use ($email, $report) {
                            $mail->to($email)->subject('Shared report: ' . $report->title);
                        }
                    );
                } catch (\Throwable $e) {
                    // log and continue
                }
            }
            $data['sent_to'] = $recipients;
        }

        return response()->json([
            'success' => true,
            'message' => 'Report shared successfully',
            'data' => $data,
        ]);
    }

    /**
     * 8. Get Report Statistics
     * GET /api/admin/reports/statistics
     */
    public function statistics(): JsonResponse
    {
        $total = AdminReport::count();
        $pending = AdminReport::where('status', 'pending')->count();
        $generated = AdminReport::where('status', 'generated')->count();
        $scheduled = AdminReport::where('status', 'scheduled')->count();
        $failed = AdminReport::where('status', 'failed')->count();

        $byType = [];
        foreach (AdminReport::TYPES as $type) {
            $byType[$type] = AdminReport::where('type', $type)->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'pending' => $pending,
                'generated' => $generated,
                'scheduled' => $scheduled,
                'failed' => $failed,
                'by_type' => $byType,
            ],
        ]);
    }

    /**
     * 9. Delete Report
     * DELETE /api/admin/reports/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $report = AdminReport::findOrFail($id);
        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }
        $report->delete();
        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully',
        ]);
    }
}
