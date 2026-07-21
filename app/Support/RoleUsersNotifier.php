<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

final class RoleUsersNotifier
{
    /**
     * @return Collection<int, User>
     */
    public static function usersForRole(string $role): Collection
    {
        $key = strtolower(trim($role));
        if ($key === '') {
            return collect();
        }

        return User::query()
            ->where(function ($query) use ($key) {
                $query->whereRaw('LOWER(role) = ?', [$key])
                    ->orWhereHas('roles', fn ($roles) => $roles->whereRaw('LOWER(name) = ?', [$key]));
            })
            ->get();
    }
}
