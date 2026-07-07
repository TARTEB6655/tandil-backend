<?php

namespace App\Rules;

use App\Models\Service;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AssignablePlatformServiceId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_numeric($value)) {
            $fail('service_id must be a numeric platform service id from GET /api/vendor/services.');

            return;
        }

        $id = (int) $value;
        if ($id < 1 || ! Service::vendorAssignable()->where('id', $id)->exists()) {
            $available = Service::vendorAssignable()->orderBy('id')->limit(15)->pluck('id')->all();
            if ($available === []) {
                $fail('No platform services are available. Ask admin to create and activate services first.');

                return;
            }

            $fail(
                'Invalid platform service id. Call GET /api/vendor/services and use one of these ids: '
                .implode(', ', $available).'.'
            );
        }
    }
}
