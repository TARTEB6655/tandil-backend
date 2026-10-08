<?php

/**
 * Restore platform services via admin HTTP API (no SSH).
 *
 *   set RESTORE_BASE_URL=https://app.tandil.ae
 *   set RESTORE_ADMIN_TOKEN=your-admin-bearer-token
 *   php scripts/restore_platform_services_via_api.php
 */

declare(strict_types=1);

$baseUrl = rtrim(getenv('RESTORE_BASE_URL') ?: 'https://app.tandil.ae', '/');
$token = getenv('RESTORE_ADMIN_TOKEN') ?: '';
$dryRun = filter_var(getenv('RESTORE_DRY_RUN') ?: '0', FILTER_VALIDATE_BOOLEAN);

if ($token === '') {
    fwrite(STDERR, "RESTORE_ADMIN_TOKEN is required.\n");
    exit(1);
}

$body = json_encode(['dry_run' => $dryRun], JSON_THROW_ON_ERROR);
$headers = implode("\r\n", [
    'Authorization: Bearer '.$token,
    'Accept: application/json',
    'Content-Type: application/json',
])."\r\n";

$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => $headers,
        'content' => $body,
        'ignore_errors' => true,
        'timeout' => 300,
    ],
]);

$url = $baseUrl.'/api/admin/catalog/restore-platform-services';
$raw = file_get_contents($url, false, $ctx);
$status = $http_response_header[0] ?? 'unknown';

echo "POST {$url}\n";
echo "Status: {$status}\n";
echo (string) $raw."\n";

exit(str_contains($status, '200') ? 0 : 1);
