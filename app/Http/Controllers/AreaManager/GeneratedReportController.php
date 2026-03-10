<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GeneratedReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:area_manager']);
    }

    public function index(Request $request): View
    {
        $reports = AdminReport::where('created_by', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('areamanager.generated-reports.index', compact('reports'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'types' => 'required|array',
            'types.*' => 'string|in:weekly_summary,team_performance,customer_satisfaction',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $types = array_unique(array_values($request->input('types', [])));
        if (empty($types)) {
            return redirect()
                ->route('areamanager.generated-reports.index')
                ->with('error', 'Please select at least one report type.');
        }

        $dateFrom = $request->input('date_from') ?? Carbon::now()->startOfMonth()->toDateString();
        $dateTo = $request->input('date_to') ?? Carbon::now()->endOfMonth()->toDateString();

        $typeMap = [
            'weekly_summary' => 'operational',
            'team_performance' => 'performance',
            'customer_satisfaction' => 'customer',
        ];

        $created = 0;
        foreach ($types as $type) {
            $adminReportType = $typeMap[$type] ?? 'operational';
            $title = ucfirst(str_replace('_', ' ', $type)) . ' (' . $dateFrom . ' to ' . $dateTo . ')';

            $report = AdminReport::create([
                'title' => $title,
                'type' => $adminReportType,
                'status' => 'pending',
                'format' => 'pdf',
                'parameters' => [
                    'start_date' => $dateFrom,
                    'end_date' => $dateTo,
                    'format' => 'pdf',
                ],
                'created_by' => $request->user()->id,
            ]);

            GenerateReportJob::dispatchSync($report);
            $created++;
        }

        $message = $created === 1
            ? 'Report generated. You can download, view, or delete it below.'
            : "{$created} reports generated. You can download, view, or delete them below.";

        return redirect()
            ->route('areamanager.generated-reports.index')
            ->with('success', $message);
    }

    public function download(int $id)
    {
        $report = AdminReport::where('created_by', request()->user()->id)->findOrFail($id);
        $format = request()->get('format', pathinfo($report->file_path ?? '', PATHINFO_EXTENSION) ?: 'pdf');

        if ($format === 'csv') {
            return $this->downloadAsCsv($report);
        }

        if (! $report->file_path || ! Storage::disk('local')->exists($report->file_path)) {
            return redirect()
                ->route('areamanager.generated-reports.index')
                ->with('error', 'Report file is not available yet or generation failed.');
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = 'area-manager-report-' . $report->id . '.' . $ext;
        $mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'csv' ? 'text/csv' : 'application/octet-stream');

        return Storage::disk('local')->download($report->file_path, $filename, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Open report in browser (inline) so user can view content. Works for PDF and text.
     */
    public function view(int $id)
    {
        $report = AdminReport::where('created_by', request()->user()->id)->findOrFail($id);

        if (! $report->file_path || ! Storage::disk('local')->exists($report->file_path)) {
            return redirect()
                ->route('areamanager.generated-reports.index')
                ->with('error', 'Report file is not available yet or generation failed.');
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: 'pdf';
        $mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'csv' ? 'text/csv' : 'text/plain');
        $path = Storage::disk('local')->path($report->file_path);

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="report-' . $report->id . '.' . $ext . '"',
        ]);
    }

    protected function downloadAsCsv(AdminReport $report)
    {
        $params = $report->parameters ?? [];
        $startDate = $params['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $params['end_date'] ?? now()->endOfMonth()->toDateString();
        $content = GenerateReportJob::buildReportContentForReport($report, $startDate, $endDate);
        $filename = 'area-manager-report-' . $report->id . '-' . now()->format('Y-m-d-His') . '.csv';

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Delete a generated report (and its file if any).
     */
    public function destroy(int $id)
    {
        $report = AdminReport::where('created_by', request()->user()->id)->findOrFail($id);

        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }

        $report->delete();

        return redirect()
            ->route('areamanager.generated-reports.index')
            ->with('success', 'Report deleted.');
    }
}
