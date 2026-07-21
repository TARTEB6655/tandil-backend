<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'order_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->foreignId('order_id')->nullable()->after('subscription_id')->constrained('orders')->nullOnDelete();
                $table->index('order_id', 'visits_order_id_idx');
            });
        }

        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'order_id')) {
            DB::table('visits')
                ->whereNull('order_id')
                ->where('notes', 'like', '%[SHOP-ORDER:%')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        if (! preg_match('/\[SHOP-ORDER:(\d+)\]/', (string) ($row->notes ?? ''), $matches)) {
                            continue;
                        }
                        $orderId = (int) ($matches[1] ?? 0);
                        if ($orderId > 0) {
                            DB::table('visits')->where('id', $row->id)->update(['order_id' => $orderId]);
                        }
                    }
                });
        }

        if (Schema::hasTable('notifications') && ! Schema::hasColumn('notifications', 'search_text')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('search_text', 1000)->nullable()->after('data');
                $table->index('search_text', 'notifications_search_text_idx');
            });

            DB::table('notifications')
                ->orderBy('created_at')
                ->chunk(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $searchText = $this->buildSearchText((string) ($row->data ?? ''));
                        if ($searchText === '') {
                            continue;
                        }
                        DB::table('notifications')->where('id', $row->id)->update([
                            'search_text' => mb_substr($searchText, 0, 1000),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'search_text')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_search_text_idx');
                $table->dropColumn('search_text');
            });
        }

        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'order_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropForeign(['order_id']);
                $table->dropIndex('visits_order_id_idx');
                $table->dropColumn('order_id');
            });
        }
    }

    private function buildSearchText(string $json): string
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return '';
        }

        $parts = array_filter([
            $data['title'] ?? null,
            $data['message'] ?? null,
            $data['body'] ?? null,
            is_array($data['meta'] ?? null) ? json_encode($data['meta']) : ($data['meta'] ?? null),
        ], fn ($value) => is_string($value) && trim($value) !== '');

        return trim(implode(' ', $parts));
    }
};
