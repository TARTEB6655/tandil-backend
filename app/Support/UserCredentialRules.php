<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Email/phone may be reused across different roles (e.g. same person as client + vendor).
 * Uniqueness is scoped per role column on users.
 */
final class UserCredentialRules
{
    public static function uniqueEmail(string $role, ?int $ignoreUserId = null): Unique
    {
        $role = strtolower(trim($role));
        $rule = Rule::unique('users', 'email')->where(function ($query) use ($role) {
            $query->whereRaw('LOWER(COALESCE(role, "")) = ?', [$role]);
        });

        if ($ignoreUserId) {
            $rule->ignore($ignoreUserId);
        }

        return $rule;
    }

    public static function uniquePhone(string $role, ?int $ignoreUserId = null): Unique
    {
        $role = strtolower(trim($role));
        $rule = Rule::unique('users', 'phone')->where(function ($query) use ($role) {
            $query->whereRaw('LOWER(COALESCE(role, "")) = ?', [$role]);
        });

        if ($ignoreUserId) {
            $rule->ignore($ignoreUserId);
        }

        return $rule;
    }

    /**
     * Laravel string rule form: unique:users,email,NULL,id,role,client
     * Prefer Unique objects above when possible.
     */
    public static function uniqueEmailString(string $role, ?int $ignoreUserId = null): string
    {
        $role = strtolower(trim($role));
        $id = $ignoreUserId ?: 'NULL';

        return "unique:users,email,{$id},id,role,{$role}";
    }

    public static function uniquePhoneString(string $role, ?int $ignoreUserId = null): string
    {
        $role = strtolower(trim($role));
        $id = $ignoreUserId ?: 'NULL';

        return "unique:users,phone,{$id},id,role,{$role}";
    }
}
