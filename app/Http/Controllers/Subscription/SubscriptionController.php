<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Http\Requests\StoreSubscriptionRequest;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            $subs = Subscription::with('client')->get();
        } else if ($user) {
            $subs = Subscription::where('client_id', $user->id)->with('visits')->get();
        } else {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json(['status' => true, 'data' => $subs], 200);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $sub = Subscription::with('visits')->find($id);

        if (! $sub) {
            return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
        }

        if (! ($user->hasRole('admin') || $sub->client_id == $user->id)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['status' => true, 'data' => $sub], 200);
    }

    public function store(StoreSubscriptionRequest $request)
    {

        $user = $request->user();

        $data = $request->validated();
        $data['client_id'] = $user->id;

        $sub = Subscription::create($data);

        return response()->json(['status' => true, 'data' => $sub], 201);
    }
}

