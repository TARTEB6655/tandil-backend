<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Download/view for generated reports. Works in two ways:
 * 1) ID + Bearer token (area_manager): allow if report created_by = user (e.g. Postman).
 * 2) Signed URL (signature + expires): allow without auth (e.g. link from list in browser).
 */
class GeneratedReportPublicController extends Controller
{
    /**
     * Download report file (attachment).
     */
    public function download(Request $request, int $id): Response
    {
        return $this->serveReport($request, $id, true);
    }

    /**
     * View report inline (e.g. PDF in browser).
     */
    public function view(Request $request, int $id): Response
    {
        return $this->serveReport($request, $id, false);
    }

    private function serveReport(Request $request, int $id, bool $attachment): Response
    {
        $report = $this->resolveReport($request, $id);
        if ($report instanceof Response) {
            return $report;
        }
        if ($report->status !== 'generated' || ! $report->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Report file is not available yet or generation failed.',
            ], 404);
        }
        if (! Storage::disk('local')->exists($report->file_path)) {
            return response()->json(['success' => false, 'message' => 'Report file not found on server.'], 404);
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: ($report->format ?? 'pdf');
        $mime = match (strtolower($ext)) {
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'xlsx', 'xls' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/pdf',
        };
        $filename = 'area-manager-report-' . $report->id . '.' . $ext;
        $fullPath = Storage::disk('local')->path($report->file_path);

        $disposition = $attachment
            ? 'attachment; filename="' . $filename . '"'
            : 'inline; filename="' . $filename . '"';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Resolve report: allow access via valid signed URL or via Bearer token (area_manager, own report).
     * Returns AdminReport or a Response (401/404).
     */
    private function resolveReport(Request $request, int $id): AdminReport|Response
    {
        if (URL::hasValidSignature($request)) {
            $report = AdminReport::find($id);
            return $report
                ? $report
                : response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        if ($request->user() && $request->user()->hasRole('area_manager')) {
            $report = AdminReport::where('created_by', $request->user()->id)->find($id);
            return $report
                ? $report
                : response()->json([
                    'success' => false,
                    'message' => 'Report not found or you do not have access to it.',
                ], 404);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Use Bearer token (area_manager) or open the signed download link.',
        ], 401);
    }
}
