<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Visit;
use App\Services\HrVisitAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class HrVisitAssignmentWebController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:hr']);
    }

    public function index(Request $request): View
    {
        $scope = $request->get('scope', 'assignable');
        $query = $scope === 'all' ? Visit::query() : HrVisitAssignmentService::assignableQuery();
        $query->with(['supervisor', 'subscription.client', 'area', 'technician']);

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_date', '<=', $request->get('date_to'));
        }

        $visits = $query->orderBy('scheduled_date')->orderBy('id')->paginate(25)->withQueryString();

        $technicians = User::role('technician')->active()->with('employee')->orderBy('name')->get();

        return view('hr.visit-assignments.index', compact('visits', 'technicians', 'scope'));
    }

    public function assign(Request $request, int $visit): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'technician_id' => 'required|integer|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $result = HrVisitAssignmentService::assignVisit(
            $visit,
            (int) $request->input('technician_id'),
            $request->input('scheduled_date'),
            $request->input('note')
        );

        if ($result['success'] ?? false) {
            return back()->with('status', $result['message'] ?? 'Assignment updated.');
        }

        return back()->withErrors(['assign' => $result['message'] ?? 'Could not assign visit.'])->withInput();
    }
}
