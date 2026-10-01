<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AssignablePlatformCategoryId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Optional field — empty/null is allowed (use with `nullable` rule)
        if ($value === null || $value === '') {
            return;
        }

        if (! is_numeric($value)) {
            $fail('category_id must be a numeric platform category id from GET /api/vendor/categories.');

            return;
        }

        $id = (int) $value;
        if ($id < 1) {
            $fail('category_id must be a valid platform category id from GET /api/vendor/categories.');

            return;
        }

        if (Category::vendorAssignable()->where('id', $id)->exists()) {
            return;
        }

        $available = Category::vendorAssignable()->orderBy('id')->limit(15)->pluck('id')->all();
        if ($available === []) {
            $fail('No platform categories are available. Ask admin to create and activate categories first.');

            return;
        }

        $fail(
            'Invalid platform category id '.$id.'. Call GET /api/vendor/categories and use one of these ids: '
            .implode(', ', $available).'.'
        );
    }
}
