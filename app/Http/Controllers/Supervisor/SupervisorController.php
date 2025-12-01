<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Report;
use App\Models\Product;
use Illuminate\Http\Request;

class SupervisorController extends Controller
{
    public function reviewVisit(Request $request, $id)
    {
        $visit = Visit::with('visitPhotos','subscription.client')->find($id);
        if (! $visit) return response()->json(['status'=>false,'message'=>'Not found'],404);
        return response()->json(['status'=>true,'data'=>$visit],200);
    }

    public function recommendProducts(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (! $visit) return response()->json(['status'=>false,'message'=>'Not found'],404);

        $productIds = $request->input('product_ids', []);
        $products = Product::whereIn('id', $productIds)->get();

        // Attach to report (create or update)
        $report = Report::firstOrCreate(['visit_id' => $visit->id], ['notes' => '']);
        $report->recommended_products = $products->pluck('id')->toArray();
        $report->save();

        return response()->json(['status'=>true,'data'=>$report->load('visit')],200);
    }

    public function finalizeReport(Request $request, $id)
    {
        $visit = Visit::with('subscription.client')->find($id);
        if (! $visit) return response()->json(['status'=>false,'message'=>'Not found'],404);

        $report = Report::firstOrCreate(['visit_id' => $visit->id]);
        $report->notes = $request->input('notes', $report->notes);
        $report->approved_by = $request->user()->id;
        $report->approved_at = now();
        $report->status = 'finalized';
        $report->save();

        // Notify client (minimal)
        try {
            $visit->subscription->client->notify(new \App\Notifications\ReportFinalized($report));
        } catch (\Throwable $e) {
        }

        return response()->json(['status'=>true,'data'=>$report],200);
    }
}
