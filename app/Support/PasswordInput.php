<?php

namespace App\Support;

use Illuminate\Http\Request;

class PasswordInput
{
    /**
     * Some JSON clients send an all-digit password as a bare JSON number instead of a
     * quoted string (e.g. {"password": 12345678} instead of {"password": "12345678"}).
     * json_decode then hands Laravel an int/float, which fails the 'string' validation
     * rule outright — from the app's perspective this looks like "numbers aren't
     * accepted in the password field". Coerce those back to a string before validation
     * runs. No-op for real strings and for multipart/form-data requests, which are
     * always strings already.
     *
     * @param  list<string>  $keys
     */
    public static function normalize(Request $request, array $keys = ['password', 'password_confirmation', 'current_password']): void
    {
        $merge = [];
        foreach ($keys as $key) {
            $value = $request->input($key);
            if (is_int($value) || is_float($value)) {
                $merge[$key] = (string) $value;
            }
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }
}
