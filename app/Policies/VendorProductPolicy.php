<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorProduct;

class VendorProductPolicy
{
    public function view(User $user, VendorProduct $vendorProduct): bool
    {
        return $user->isAdmin() || $user->vendor?->id === $vendorProduct->vendor_id;
    }

    public function update(User $user, VendorProduct $vendorProduct): bool
    {
        return $this->view($user, $vendorProduct) && ($user->isAdmin() || $user->vendor?->isApproved());
    }

    public function delete(User $user, VendorProduct $vendorProduct): bool
    {
        return $this->update($user, $vendorProduct);
    }
}
