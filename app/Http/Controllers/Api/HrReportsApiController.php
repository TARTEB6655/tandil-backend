<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use App\Models\User;
use App\Services\HrTechnicianMonthlyReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class HrReportsApiController extends Controller
{
    protected function fileUrl(AdminReport $report): ?string
    {
        return url('/api/hr/reports/' . $report->id . '/download-public');
    }

    protected function transformReport(AdminReport $report): array
    {
        $report->load('creator');

        return [
            'id' => $report->id,
            'title' => $report->title,
            'type' => $report->type,
            'status' => $report->status,
            'created_at' => $report->created_at->toIso8601String(),
            'generated_at' => $report->generated_at?->toIso8601String(),
            'file_url' => $this->fileUrl($report),
            'file_size' => $report->file_size,
            'parameters' => $report->parameters,
            'failure_reason' => $report->failure_reason,
        ];
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

    /**
     * GET /api/hr/reports/technician-monthly
     * JSON preview: technician, leaves, visits, working-day summary for one calendar month.
     */
    public function technicianMonthlyPreview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'technician_id' => 'required|integer|exists:users,id',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $technicianId = (int) $request->input('technician_id');
        $user = \App\Models\User::role('technician')->whereKey($technicianId)->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User is not a technician.'], 422);
        }

        $payload = HrTechnicianMonthlyReportService::buildPayload(
            $technicianId,
            (int) $request->input('year'),
            (int) $request->input('month')
        );

        return response()->json(['success' => true, 'data' => $payload]);
    }

    /**
     * GET /api/hr/reports — reports created by the authenticated HR (or admin) user.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->input('per_page', 15)));
        $query = AdminReport::query()
            ->where('created_by', $request->user()->id)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $paginator = $query->paginate($perPage);
        $data = collect($paginator->items())->map(fn (AdminReport $r) => $this->transformReport($r))->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * POST /api/hr/reports/generate
     * Queues PDF (or csv) like admin; type must be hr_technician_monthly for this endpoint.
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'parameters' => 'required|array',
            'parameters.technician_id' => 'required|integer|exists:users,id',
            'parameters.year' => 'required|integer|min:2000|max:2100',
            'parameters.month' => 'required|integer|min:1|max:12',
            'parameters.format' => 'nullable|in:pdf,csv',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $techId = (int) $request->input('parameters.technician_id');
        $technician = User::role('technician')->whereKey($techId)->first();
        if (! $technician) {
            return response()->json(['success' => false, 'message' => 'User is not a technician.'], 422);
        }

        $year = (int) $request->input('parameters.year');
        $month = (int) $request->input('parameters.month');
        $monthDate = Carbon::create($year, $month, 1);
        $start = $monthDate->copy()->startOfMonth()->toDateString();
        $end = $monthDate->copy()->endOfMonth()->toDateString();
        $autoTitle = ($technician->name ?? 'Technician') . ' — ' . $monthDate->format('F Y');

        $params = array_merge($request->input('parameters', []), [
            'start_date' => $start,
            'end_date' => $end,
        ]);
        $format = $params['format'] ?? 'pdf';

        $report = AdminReport::create([
            'title' => $autoTitle,
            'type' => 'hr_technician_monthly',
            'status' => 'pending',
            'format' => $format,
            'parameters' => $params,
            'created_by' => $request->user()->id,
        ]);

        GenerateReportJob::dispatch($report);

        return response()->json([
            'success' => true,
            'message' => 'Report generation started. You will be notified when it is ready.',
            'data' => $this->transformReport($report->fresh()->load('creator')),
        ], 201);
    }

    public function download(Request $request, string $id)
    {
        $reportId = (int) $id;
        if ($reportId < 1) {
            return response()->json(['success' => false, 'message' => 'Invalid report id.'], 422);
        }

        $report = AdminReport::where('created_by', $request->user()->id)->find($reportId);
        if (! $report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }
        return $this->downloadReportFile($report);
    }

    public function downloadPublic(Request $request, string $id)
    {
        $reportId = (int) $id;
        if ($reportId < 1) {
            return response()->json(['success' => false, 'message' => 'Invalid report id.'], 422);
        }
        $report = AdminReport::find($reportId);

        if (! $report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        return $this->downloadReportFile($report);
    }

    protected function downloadReportFile(AdminReport $report)
    {
        $report = $this->ensureReportFile($report);
        if (! $report->file_path || ! Storage::disk('local')->exists($report->file_path)) {
            return response()->json(['success' => false, 'message' => 'Report file not found.'], 404);
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: $report->format;
        $mime = match (strtolower((string) $ext)) {
            'csv' => 'text/csv',
            default => 'application/pdf',
        };
        $filename = 'hr-report-' . $report->id . '.' . $ext;

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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $reportId = (int) $id;
        if ($reportId < 1) {
            return response()->json(['success' => false, 'message' => 'Invalid report id.'], 422);
        }

        $report = AdminReport::where('created_by', $request->user()->id)->find($reportId);
        if (! $report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully.',
        ]);
    }
}
