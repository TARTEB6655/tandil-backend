<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = \App\Models\Report::with(['visit', 'supervisor'])->get();

        return response()->json([
            'status' => true,
            'data' => $reports
        ], 200);
    }

    public function show($id)
    {
        $report = \App\Models\Report::with(['visit', 'supervisor'])->find($id);

        if (! $report) {
            return response()->json(['status' => false, 'message' => 'Report not found'], 404);
        }

        return response()->json(['status' => true, 'data' => $report], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'visit_id' => 'required|integer|exists:visits,id',
            'technician_notes' => 'nullable|string',
            'supervisor_notes' => 'nullable|string',
            'recommendations' => 'nullable|array',
        ]);

        $report = \App\Models\Report::create($data);

        return response()->json(['status' => true, 'data' => $report], 201);
    }
}
