<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ReportManagementController extends Controller
{
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
     * List generated/scheduled reports.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 15), 100);
        $query = AdminReport::with('creator')->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $reports = $query->paginate($perPage);
        $statistics = $this->getStatistics();

        return view('admin.report-management.index', compact('reports', 'statistics'));
    }

    /**
     * Form to generate a new report.
     */
    public function create()
    {
        return view('admin.report-management.create');
    }

    /**
     * Start report generation and download immediately.
     */
    public function store(Request $request)
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
            return redirect()->route('admin.report-management.create')
                ->withErrors($validator)
                ->withInput();
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

        GenerateReportJob::dispatchSync($report);
        $report->refresh();

        if ($report->status !== 'generated' || ! $report->file_path) {
            $reason = $report->failure_reason ? (' Reason: ' . $report->failure_reason) : '';
            return redirect()->route('admin.report-management.index')
                ->with('error', 'Could not generate report instantly.' . $reason);
        }

        return redirect()->route('admin.report-management.download', ['id' => $report->id]);
    }

    /**
     * Form to schedule a report.
     */
    public function createSchedule()
    {
        return view('admin.report-management.schedule');
    }

    /**
     * Store scheduled report.
     */
    public function storeSchedule(Request $request)
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
            return redirect()->route('admin.report-management.schedule.create')
                ->withErrors($validator)
                ->withInput();
        }

        $params = $request->input('parameters', []);
        $format = $params['format'] ?? 'pdf';

        AdminReport::create([
            'title' => $request->title,
            'type' => $request->type,
            'status' => 'scheduled',
            'scheduled_at' => $request->scheduled_at,
            'recurrence' => $request->recurrence,
            'format' => $format,
            'parameters' => $params,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.report-management.index')
            ->with('success', 'Report scheduled successfully.');
    }

    /**
     * Show single report details.
     */
    public function show(string $id)
    {
        $report = AdminReport::with('creator')->findOrFail($id);
        return view('admin.report-management.show', compact('report'));
    }

    /**
     * Download report file.
     */
    public function download(string $id)
    {
        $report = AdminReport::findOrFail($id);
        $report = $this->ensureReportFile($report);
        if (! $report->file_path || ! Storage::disk('local')->exists($report->file_path)) {
            return redirect()->route('admin.report-management.show', $id)
                ->with('error', 'Report file not found.');
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: $report->format;
        $filename = 'report-' . $report->id . '.' . $ext;
        $mime = match (strtolower((string) $ext)) {
            'csv' => 'text/csv',
            'xlsx', 'xls' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/pdf',
        };
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
     * Cancel a scheduled report.
     */
    public function cancel(string $id)
    {
        $report = AdminReport::findOrFail($id);
        if ($report->status !== 'scheduled') {
            return redirect()->route('admin.report-management.show', $id)
                ->with('error', 'Only scheduled reports can be cancelled.');
        }
        $report->delete();
        return redirect()->route('admin.report-management.index')
            ->with('success', 'Scheduled report cancelled.');
    }

    /**
     * Delete a report.
     */
    public function destroy(string $id)
    {
        $report = AdminReport::findOrFail($id);
        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }
        $report->delete();
        return redirect()->route('admin.report-management.index')
            ->with('success', 'Report deleted successfully.');
    }

    protected function getStatistics(): array
    {
        return [
            'total' => AdminReport::count(),
            'pending' => AdminReport::where('status', 'pending')->count(),
            'generated' => AdminReport::where('status', 'generated')->count(),
            'scheduled' => AdminReport::where('status', 'scheduled')->count(),
            'failed' => AdminReport::where('status', 'failed')->count(),
        ];
    }
}
