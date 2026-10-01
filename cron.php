<?php

/**
 * Cloudways / shared-hosting cron entrypoint.
 *
 * Crontab (already on Cloudways):
 *   * * * * * cd /path/to/public_html && php /path/to/public_html/cron.php
 *
 * This file runs Laravel's scheduler so due tasks fire every minute, including:
 *   php artisan reports:process-scheduled
 */

define('LARAVEL_START', microtime(true));

$artisan = __DIR__.DIRECTORY_SEPARATOR.'artisan';
if (! is_file($artisan)) {
    fwrite(STDERR, "cron.php: artisan not found in ".__DIR__.PHP_EOL);
    exit(1);
}

$php = PHP_BINARY ?: 'php';
$cmd = escapeshellarg($php).' '.escapeshellarg($artisan).' schedule:run';

$output = [];
$exitCode = 0;
exec($cmd.' 2>&1', $output, $exitCode);

$logDir = __DIR__.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
if (is_dir($logDir) && is_writable($logDir)) {
    $line = sprintf(
        "[%s] schedule:run exit=%d%s%s",
        date('Y-m-d H:i:s'),
        $exitCode,
        $output !== [] ? ' '.implode(' | ', $output) : '',
        PHP_EOL
    );
    @file_put_contents($logDir.DIRECTORY_SEPARATOR.'cron-schedule.log', $line, FILE_APPEND);
}

exit($exitCode);
