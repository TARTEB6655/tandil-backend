<?php

namespace App\Support;

use Illuminate\Http\Request;

final class CapsPagination
{
    public static function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return min(max((int) $request->get('per_page', $default), 1), $max);
    }
}
