<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        // Apply middleware to protect routes based on roles
        $this->middleware(['auth:sanctum', 'role:client|technician|supervisor|area_manager|admin']);
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
            }

            if ($user->hasRole('admin') || $user->hasRole('supervisor') || $user->hasRole('area_manager')) {
                $reports = \App\Models\Report::with(['visit', 'supervisor'])->get();
            } else {
                // Clients and technicians see only their own reports
                $reports = \App\Models\Report::whereHas('visit', function($q) use ($user) {
                    if ($user->hasRole('client')) {
                        $q->whereHas('subscription', function($sq) use ($user) {
                            $sq->where('client_id', $user->id);
                        });
                    } elseif ($user->hasRole('technician')) {
                        $q->where('technician_id', $user->id);
                    }
                })->with(['visit', 'supervisor'])->get();
            }

            return response()->json([
                'status' => true,
                'data' => $reports
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch reports: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $report = \App\Models\Report::with(['visit', 'supervisor'])->find($id);

            if (! $report) {
                return response()->json(['status' => false, 'message' => 'Report not found'], 404);
            }

            return response()->json(['status' => true, 'data' => $report], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'visit_id' => 'required|integer|exists:visits,id',
                'notes' => 'nullable|string',
                'status' => 'nullable|string|in:draft,pending,approved,sent_to_client',
            ]);

            $report = \App\Models\Report::create($data);

            return response()->json(['status' => true, 'data' => $report], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create report: ' . $e->getMessage()
            ], 500);
        }
    }
}
