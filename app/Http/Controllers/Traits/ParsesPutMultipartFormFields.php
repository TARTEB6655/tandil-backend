<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

/**
 * PHP does not populate $_POST for PUT/PATCH with multipart/form-data.
 * Merge text fields from the raw body (cached by CachePutRequestBody middleware).
 */
trait ParsesPutMultipartFormFields
{
    protected function mergePutMultipartFormFields(Request $request): void
    {
        if (! $request->isMethod('PUT') && ! $request->isMethod('PATCH')) {
            return;
        }

        $contentType = $request->header('Content-Type');
        if (! $contentType || ! str_contains($contentType, 'multipart/form-data')) {
            return;
        }
        if (! preg_match('/boundary=(?:"([^"]+)"|([^\s;]+))/', $contentType, $m)) {
            return;
        }

        $boundary = trim($m[1] ?? $m[2]);
        $raw = $request->attributes->get('_put_multipart_raw');
        if ($raw === null) {
            $raw = $request->getContent();
        }
        if ($raw === '' || $raw === false || ! is_string($raw)) {
            return;
        }

        $params = [];
        $lineDelimiter = "\r\n--".$boundary;
        $parts = explode($lineDelimiter, $raw);
        $firstPrefix = '--'.$boundary;

        foreach ($parts as $i => $segment) {
            $part = $segment;
            if ($i === 0) {
                if ($part === '' || $part === '--') {
                    continue;
                }
                if (str_starts_with($part, $firstPrefix."\r\n")) {
                    $part = substr($part, strlen($firstPrefix) + 2);
                } elseif (str_starts_with($part, $firstPrefix."\n")) {
                    $part = substr($part, strlen($firstPrefix) + 1);
                }
            }
            $part = trim($part, "\r\n");
            if ($part === '' || $part === '-') {
                continue;
            }

            $headerEnd = strpos($part, "\r\n\r\n");
            if ($headerEnd === false) {
                $headerEnd = strpos($part, "\n\n");
            }
            if ($headerEnd === false) {
                continue;
            }

            $headers = substr($part, 0, $headerEnd);
            $bodyStart = $headerEnd + (str_contains($part, "\r\n\r\n") ? 4 : 2);
            $value = substr($part, $bodyStart);
            $value = preg_replace('/\r?\n$/s', '', $value);

            if (! preg_match('/name="([^"]+)"/', $headers, $nameMatch)) {
                continue;
            }
            if (preg_match('/filename="([^"]*)"/', $headers)) {
                continue;
            }

            $params[$nameMatch[1]] = $value;
        }

        if ($params !== []) {
            $request->merge($params);
        }
    }
}
