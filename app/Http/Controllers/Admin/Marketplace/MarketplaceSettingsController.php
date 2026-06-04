<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Support\MarketplaceSettings;
use Illuminate\Http\Request;

class MarketplaceSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        return view('admin.marketplace.settings', [
            'commissionPercent' => MarketplaceSettings::commissionPercent(),
            'productApprovalRequired' => MarketplaceSettings::productApprovalRequired(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'marketplace_commission_percent' => 'required|numeric|min:0|max:100',
            'marketplace_product_approval_required' => 'nullable|boolean',
        ]);

        MarketplaceSettings::setCommissionPercent((float) $data['marketplace_commission_percent']);
        MarketplaceSettings::setProductApprovalRequired($request->boolean('marketplace_product_approval_required'));

        return back()->with('success', 'Marketplace settings saved.');
    }

    public function updateVendorCommission(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $vendor->update([
            'commission_rate' => $data['commission_rate'] === null || $data['commission_rate'] === ''
                ? null
                : round((float) $data['commission_rate'], 2),
        ]);

        return back()->with('success', 'Vendor commission rate updated.');
    }
}
