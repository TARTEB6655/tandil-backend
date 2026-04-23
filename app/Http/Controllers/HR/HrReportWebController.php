<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use App\Models\User;
use App\Services\HrTechnicianMonthlyReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrReportWebController extends Controller
{
    private function paginatedReports(Request $request)
    {
        return AdminReport::where('created_by', $request->user()->id)
            ->where('type', 'hr_technician_monthly')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }

    public function __construct()
    {
        $this->middleware(['auth', 'role:hr']);
    }

    public function technicianMonthlyForm(Request $request): View
    {
        $technicians = User::role('technician')->active()->with('employee')->orderBy('name')->get();
        $defaultYear = (int) $request->get('year', now()->year);
        $defaultMonth = (int) $request->get('month', now()->month);
        $selectedTechId = (int) $request->get('technician_id', 0);

        $myReports = $this->paginatedReports($request);

        return view('hr.reports.technician-monthly', [
            'technicians' => $technicians,
            'defaultYear' => $defaultYear,
            'defaultMonth' => $defaultMonth,
            'preview' => null,
            'selectedTechId' => $selectedTechId,
            'myReports' => $myReports,
        ]);
    }

    public function technicianMonthlyPreview(Request $request): View
    {
        $v = Validator::make($request->all(), [
            'technician_id' => 'required|integer|exists:users,id',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);
        if ($v->fails() || ! User::role('technician')->whereKey($request->input('technician_id'))->exists()) {
            return redirect()->route('hr.reports.technician-monthly')->withErrors($v)->withInput();
        }

        $preview = HrTechnicianMonthlyReportService::buildPayload(
            (int) $request->input('technician_id'),
            (int) $request->input('year'),
            (int) $request->input('month')
        );

        $technicians = User::role('technician')->active()->with('employee')->orderBy('name')->get();
        $myReports = $this->paginatedReports($request);

        return view('hr.reports.technician-monthly', [
            'technicians' => $technicians,
            'defaultYear' => (int) $request->input('year'),
            'defaultMonth' => (int) $request->input('month'),
            'preview' => $preview,
            'selectedTechId' => (int) $request->input('technician_id'),
            'myReports' => $myReports,
        ]);
    }

    public function technicianMonthlyGenerate(Request $request): StreamedResponse|RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'technician_id' => 'required|integer|exists:users,id',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);
        if ($v->fails()) {
            return redirect()->route('hr.reports.technician-monthly')->withErrors($v);
        }
        $tid = (int) $request->input('technician_id');
        if (! User::role('technician')->whereKey($tid)->exists()) {
            return redirect()->route('hr.reports.technician-monthly')->withErrors(['technician_id' => 'Select a technician.']);
        }

        $year = (int) $request->input('year');
        $month = (int) $request->input('month');
        $tech = User::with('employee')->find($tid);
        $label = Carbon::create($year, $month, 1)->format('F Y');
        $title = ($tech->name ?? 'Technician') . ' — ' . $label;

        $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $report = AdminReport::create([
            'title' => $title,
            'type' => 'hr_technician_monthly',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [
                'technician_id' => $tid,
                'year' => $year,
                'month' => $month,
                'start_date' => $start,
                'end_date' => $end,
            ],
            'created_by' => $request->user()->id,
        ]);

        GenerateReportJob::dispatchSync($report);
        $report->refresh();

        if ($report->status !== 'generated' || ! $report->file_path) {
            return redirect()
                ->route('hr.reports.technician-monthly', ['year' => $year, 'month' => $month, 'technician_id' => $tid])
                ->withErrors(['download' => 'Could not generate report instantly. Please try again.']);
        }
        if (! Storage::disk('local')->exists($report->file_path)) {
            return redirect()
                ->route('hr.reports.technician-monthly', ['year' => $year, 'month' => $month, 'technician_id' => $tid])
                ->withErrors(['download' => 'Generated file not found. Please try again.']);
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::disk('local')->download($report->file_path, 'hr-report-' . $report->id . '.' . $ext);
    }

    public function downloadGenerated(Request $request, int $id): StreamedResponse|RedirectResponse
    {
        $report = AdminReport::where('created_by', $request->user()->id)->find($id);
        if (! $report || $report->status !== 'generated' || ! $report->file_path) {
            return redirect()->route('hr.reports.technician-monthly')->withErrors(['download' => 'Report not ready or not found.']);
        }
        if (! Storage::disk('local')->exists($report->file_path)) {
            return redirect()->route('hr.reports.technician-monthly')->withErrors(['download' => 'File missing on server.']);
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::disk('local')->download($report->file_path, 'hr-report-' . $report->id . '.' . $ext);
    }
}

