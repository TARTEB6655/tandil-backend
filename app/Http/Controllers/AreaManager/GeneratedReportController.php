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

            GenerateReportJob::dispatch($report);
            $created++;
        }

        $message = $created === 1
            ? 'Report generation started. Refresh this page to see status; download when Ready.'
            : "{$created} report generations started. Refresh this page to see status; download when Ready.";

        return redirect()
            ->route('areamanager.generated-reports.index')
            ->with('success', $message);
    }

    public function download(int $id)
    {
        $report = AdminReport::where('created_by', request()->user()->id)->findOrFail($id);

        if ($report->status !== 'generated' || ! $report->file_path) {
            return redirect()
                ->route('areamanager.generated-reports.index')
                ->with('error', 'Report file is not available yet or generation failed.');
        }

        if (! Storage::disk('local')->exists($report->file_path)) {
            return redirect()
                ->route('areamanager.generated-reports.index')
                ->with('error', 'Report file not found.');
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = 'area-manager-report-' . $report->id . '.' . $ext;

        return Storage::disk('local')->download($report->file_path, $filename, [
            'Content-Type' => $ext === 'pdf' ? 'application/pdf' : 'text/plain',
        ]);
    }
}
