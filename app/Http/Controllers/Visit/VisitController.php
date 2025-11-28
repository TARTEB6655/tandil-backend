<?php

namespace App\Http\Controllers\Visit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use App\Models\VisitPhoto;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            $visits = Visit::with(['subscription', 'photos'])->get();
        } else if ($user) {
            // visits for client's subscriptions
            $visits = Visit::whereHas('subscription', function ($q) use ($user) {
                $q->where('client_id', $user->id);
            })->with(['subscription','photos'])->get();
        } else {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json(['status' => true, 'data' => $visits], 200);
    }

    public function show(Request $request, $id)
    {
        $visit = Visit::with(['subscription', 'photos', 'report'])->find($id);

        if (! $visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        return response()->json(['status' => true, 'data' => $visit], 200);
    }

    public function uploadPhoto(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (! $visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        // minimal: accept a file upload or create a placeholder path
        $path = null;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $path = $file->store('visit_photos');
        } else {
            $path = 'seeded-placeholder.jpg';
        }

        $photo = VisitPhoto::create([
            'visit_id' => $visit->id,
            'type' => $request->input('type', 'before'),
            'photo_path' => $path,
        ]);

        return response()->json(['status' => true, 'data' => $photo], 201);
    }
}
