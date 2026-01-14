<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:technician']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $search = $request->get('search', '');
        
        $visitsQuery = Visit::where('technician_id', $user->id);
        
        if ($search) {
            $visitsQuery->where(function($q) use ($search) {
                $q->where('status', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('subscription.client', function($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('area', function($aq) use ($search) {
                      $aq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        
        $visits = $visitsQuery->with(['subscription.client', 'supervisor', 'area', 'photos'])
            ->orderBy('scheduled_date', 'desc')
            ->paginate(15);

        return view('technician.visits.index', compact('visits', 'search'));
    }

    public function show($id)
    {
        $user = Auth::user();
        $visit = Visit::where('technician_id', $user->id)
            ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos', 'report', 'complaints'])
            ->findOrFail($id);

        return view('technician.visits.show', compact('visit'));
    }

    public function accept(Request $request, $id)
    {
        $user = Auth::user();
        $visit = Visit::where('technician_id', $user->id)->findOrFail($id);

        if ($visit->status !== 'pending') {
            return back()->with('error', 'Visit can only be accepted when status is pending.');
        }

        $visit->status = 'accepted';
        $visit->accepted_at = now();
        $visit->save();

        return back()->with('success', 'Visit accepted successfully.');
    }

    public function start(Request $request, $id)
    {
        $user = Auth::user();
        $visit = Visit::where('technician_id', $user->id)->findOrFail($id);

        if (!in_array($visit->status, ['accepted', 'pending'])) {
            return back()->with('error', 'Visit can only be started when status is accepted or pending.');
        }

        $visit->status = 'started';
        $visit->started_at = now();
        $visit->save();

        return back()->with('success', 'Visit started successfully.');
    }

    public function complete(Request $request, $id)
    {
        $user = Auth::user();
        $visit = Visit::where('technician_id', $user->id)->findOrFail($id);

        if ($visit->status !== 'started') {
            return back()->with('error', 'Visit can only be completed when status is started.');
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $visit->status = 'completed';
        $visit->completed_at = now();
        if ($request->has('notes')) {
            $visit->notes = $request->input('notes');
        }
        $visit->save();

        return back()->with('success', 'Visit completed successfully.');
    }

    public function updateNotes(Request $request, $id)
    {
        $user = Auth::user();
        $visit = Visit::where('technician_id', $user->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'notes' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $visit->notes = $request->input('notes');
        $visit->save();

        return back()->with('success', 'Visit notes updated successfully.');
    }

    public function uploadPhoto(Request $request, $id)
    {
        $user = Auth::user();
        $visit = Visit::where('technician_id', $user->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
            'type' => 'nullable|in:before,during,after',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $file = $request->file('photo');
        $type = $request->input('type', 'after');
        $path = $file->store('visit_photos', 'public');

        $visitPhoto = \App\Models\VisitPhoto::create([
            'visit_id' => $visit->id,
            'photo_path' => $path,
            'type' => $type,
        ]);

        return back()->with('success', 'Photo uploaded successfully.');
    }
}

