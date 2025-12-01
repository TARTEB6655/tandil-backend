<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTips implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Simple implementation: fetch users who opted in and send a tips notification
        $users = User::where('receive_tips', true)->get();
        foreach ($users as $user) {
            try {
                $user->notify(new \App\Notifications\TipsNotification());
            } catch (\Throwable $e) {
            }
        }
    }
}
