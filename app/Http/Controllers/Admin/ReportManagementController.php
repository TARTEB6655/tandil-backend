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

class ReportManagementController extends Controller
{
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
     * Start report generation (async job).
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

        GenerateReportJob::dispatch($report);

        return redirect()->route('admin.report-management.index')
            ->with('success', 'Report generation started. You will be notified when it\'s ready.');
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
        if ($report->status !== 'generated' || ! $report->file_path) {
            return redirect()->route('admin.report-management.show', $id)
                ->with('error', 'Report file is not available.');
        }
        if (! Storage::disk('local')->exists($report->file_path)) {
            return redirect()->route('admin.report-management.show', $id)
                ->with('error', 'Report file not found.');
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: $report->format;
        $filename = 'report-' . $report->id . '.' . $ext;

        return Storage::disk('local')->download($report->file_path, $filename);
    }

    /**
     * Form to share report (email or link).
     */
    public function createShare(string $id)
    {
        $report = AdminReport::with('creator')->findOrFail($id);
        if ($report->status !== 'generated' || ! $report->file_path) {
            return redirect()->route('admin.report-management.show', $id)
                ->with('error', 'Only generated reports can be shared.');
        }
        return view('admin.report-management.share', compact('report'));
    }

    /**
     * Send share (email or copy link).
     */
    public function storeShare(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|in:email,link',
            'recipients' => 'required_if:method,email|nullable|string',
            'message' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return redirect()->route('admin.report-management.share.create', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $report = AdminReport::with('creator')->findOrFail($id);
        if ($report->status !== 'generated' || ! $report->file_path) {
            return redirect()->route('admin.report-management.show', $id)
                ->with('error', 'Only generated reports can be shared.');
        }

        $method = $request->method;

        if ($method === 'link') {
            $shareLink = URL::temporarySignedRoute(
                'api.admin.reports.download',
                now()->addDays(7),
                ['id' => $report->id]
            );
            return redirect()->route('admin.report-management.show', $id)
                ->with('success', 'Share link created (valid 7 days).')
                ->with('share_link', $shareLink);
        }

        $recipientsStr = $request->recipients ?? '';
        $recipients = array_filter(array_map('trim', preg_split('/[\s,;]+/', $recipientsStr)));
        $recipients = array_filter($recipients, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if (empty($recipients)) {
            return redirect()->route('admin.report-management.share.create', $id)
                ->withErrors(['recipients' => ['Please enter at least one valid email.']])
                ->withInput();
        }

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
                // continue
            }
        }

        return redirect()->route('admin.report-management.show', $id)
            ->with('success', 'Report shared by email to: ' . implode(', ', $recipients));
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
