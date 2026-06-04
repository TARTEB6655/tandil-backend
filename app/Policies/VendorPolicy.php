<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin() || $user->id === $vendor->user_id;
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin() || ($user->id === $vendor->user_id && $vendor->isApproved());
    }

    public function approve(User $user): bool
    {
        return $user->isAdmin();
    }
}
