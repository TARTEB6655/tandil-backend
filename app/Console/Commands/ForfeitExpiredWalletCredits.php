<?php

namespace App\Console\Commands;

use App\Models\WalletCredit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ForfeitExpiredWalletCredits extends Command
{
    protected $signature = 'wallet:forfeit-expired';

    protected $description = 'Forfeit expired wallet refunds and transfer to company bucket';

    public function handle(): int
    {
        $rows = WalletCredit::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(500)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No expired wallet credits found.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($rows as $credit) {
            DB::transaction(function () use ($credit, &$count) {
                $fresh = WalletCredit::query()->whereKey($credit->id)->lockForUpdate()->first();
                if (! $fresh || $fresh->status !== 'active' || ! $fresh->expires_at || $fresh->expires_at->isFuture()) {
                    return;
                }

                $user = $fresh->user()->lockForUpdate()->first();
                if (! $user) {
                    return;
                }

                $amount = (float) $fresh->amount;
                $currentBalance = (float) ($user->wallet_balance ?? 0);
                $deduct = min($currentBalance, $amount);

                $user->wallet_balance = round($currentBalance - $deduct, 2);
                $user->wallet_forfeited_total = round((float) ($user->wallet_forfeited_total ?? 0) + $amount, 2);
                $user->save();

                $fresh->status = 'forfeited';
                $fresh->forfeited_at = now();
                $fresh->save();

                $count++;
            });
        }

        $this->info("Forfeited {$count} wallet credit(s).");

        return self::SUCCESS;
    }
}
