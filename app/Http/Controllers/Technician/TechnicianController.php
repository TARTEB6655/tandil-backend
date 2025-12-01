<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadVisitPhotoRequest;
use App\Models\Visit;
use App\Models\VisitPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TechnicianController extends Controller
{
    public function assigned(Request $request)
    {
        $user = $request->user();
        $visits = Visit::where('technician_id', $user->id)->with('subscription.client')->get();
        return response()->json(['status' => true, 'data' => $visits], 200);
    }

    public function accept(Request $request, $id)
    {
        $user = $request->user();
        $visit = Visit::find($id);
        if (! $visit) return response()->json(['status'=>false,'message'=>'Not found'],404);
        if ($visit->technician_id !== $user->id) return response()->json(['status'=>false,'message'=>'Forbidden'],403);
        $visit->status = 'accepted';
        $visit->accepted_at = now();
        $visit->save();
        return response()->json(['status'=>true,'data'=>$visit],200);
    }

    public function start(Request $request, $id)
    {
        $user = $request->user();
        $visit = Visit::find($id);
        if (! $visit) return response()->json(['status'=>false,'message'=>'Not found'],404);
        if ($visit->technician_id !== $user->id) return response()->json(['status'=>false,'message'=>'Forbidden'],403);
        $visit->status = 'in_progress';
        $visit->started_at = now();
        $visit->save();
        return response()->json(['status'=>true,'data'=>$visit],200);
    }

    public function complete(Request $request, $id)
    {
        $user = $request->user();
        $visit = Visit::find($id);
        if (! $visit) return response()->json(['status'=>false,'message'=>'Not found'],404);
        if ($visit->technician_id !== $user->id) return response()->json(['status'=>false,'message'=>'Forbidden'],403);

        $visit->status = 'completed';
        $visit->completed_at = now();
        $visit->notes = $request->input('notes', $visit->notes);
        $visit->save();

        return response()->json(['status'=>true,'data'=>$visit],200);
    }

    public function uploadPhoto(UploadVisitPhotoRequest $request, $id)
    {
        $user = $request->user();
        $visit = Visit::find($id);
        if (! $visit) return response()->json(['status'=>false,'message'=>'Not found'],404);
        if ($visit->technician_id !== $user->id) return response()->json(['status'=>false,'message'=>'Forbidden'],403);

        $file = $request->file('photo');
        $type = $request->input('type', 'after');
        $path = $file->store('visit_photos', 'public');

        $vp = VisitPhoto::create([
            'visit_id' => $visit->id,
            'photo_path' => $path,
            'type' => $type,
        ]);

        return response()->json(['status'=>true,'data'=>$vp],201);
    }
}
