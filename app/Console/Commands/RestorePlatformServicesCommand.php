<?php

namespace App\Console\Commands;

use App\Services\Admin\PlatformServicesRestoreService;
use Illuminate\Console\Command;

class RestorePlatformServicesCommand extends Command
{
    protected $signature = 'catalog:restore-platform-services
                            {--dry-run : Show actions without writing}
                            {--slug= : Restore only one snapshot slug}';

    protected $description = 'Restore Tandil platform services from snapshot (revert matching categories first, else recreate rows)';

    public function handle(PlatformServicesRestoreService $restore): int
    {
        try {
            $result = $restore->run((bool) $this->option('dry-run'), $this->option('slug') ?: null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['actions'] as $action) {
            $this->line(json_encode($action, JSON_UNESCAPED_UNICODE));
        }

        $this->newLine();
        $this->info("Done. restored={$result['restored']} skipped={$result['skipped']}");
        $this->line('Verify: GET /api/admin/services (admin token).');

        return self::SUCCESS;
    }
}
