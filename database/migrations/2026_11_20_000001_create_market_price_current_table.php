<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_price_current', function (Blueprint $table) {
            $table->id();
            $table->string('region_id', 32);
            $table->unsignedBigInteger('type_id');
            $table->string('source', 64);
            $table->decimal('buy_price', 20, 4)->nullable();
            $table->decimal('sell_price', 20, 4)->nullable();
            $table->timestamp('buy_observed_at')->nullable();
            $table->timestamp('sell_observed_at')->nullable();
            $table->unsignedBigInteger('buy_snapshot_id')->nullable();
            $table->unsignedBigInteger('sell_snapshot_id')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'type_id', 'source'], 'market_price_current_source_type_unique');
            $table->index(['region_id', 'type_id', 'buy_snapshot_id'], 'market_price_current_buy_lookup_idx');
            $table->index(['region_id', 'source', 'type_id'], 'market_price_current_source_lookup_idx');
        });

        // Seed the compact table from the newest usable price on each side and
        // source. This scans the historical table once during migration; the
        // history itself remains untouched.
        DB::statement(<<<'SQL'
            INSERT INTO market_price_current (
                region_id, type_id, source,
                buy_price, sell_price,
                buy_observed_at, sell_observed_at,
                buy_snapshot_id, sell_snapshot_id,
                created_at, updated_at
            )
            SELECT latest.region_id, latest.type_id, latest.source,
                   buy_snapshot.buy_price, sell_snapshot.sell_price,
                   buy_snapshot.observed_at, sell_snapshot.observed_at,
                   latest.buy_snapshot_id, latest.sell_snapshot_id,
                   CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM (
                SELECT region_id, type_id, source,
                       MAX(CASE WHEN buy_price IS NOT NULL AND buy_price > 0 THEN id END) AS buy_snapshot_id,
                       MAX(CASE WHEN sell_price IS NOT NULL AND sell_price > 0 THEN id END) AS sell_snapshot_id
                FROM market_price_snapshots
                GROUP BY region_id, type_id, source
            ) AS latest
            LEFT JOIN market_price_snapshots AS buy_snapshot
                ON buy_snapshot.id = latest.buy_snapshot_id
            LEFT JOIN market_price_snapshots AS sell_snapshot
                ON sell_snapshot.id = latest.sell_snapshot_id
            WHERE latest.buy_snapshot_id IS NOT NULL
               OR latest.sell_snapshot_id IS NOT NULL
        SQL);

        Schema::create('market_price_watchlist', function (Blueprint $table) {
            $table->id();
            $table->string('region_id', 32);
            $table->unsignedBigInteger('type_id');
            $table->timestamp('added_at');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'type_id'], 'market_price_watchlist_region_type_unique');
            $table->index(['region_id', 'last_checked_at'], 'market_price_watchlist_checked_idx');
        });

        $now = now();
        DB::table('appraisal_quote_items')->select('type_id')->distinct()->orderBy('type_id')->chunk(500, function ($items) use ($now): void {
            $records = $items->map(fn ($item) => [
                'region_id' => (string) config('buyback.region_id', '10000002'),
                'type_id' => $item->type_id,
                'added_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($records) {
                DB::table('market_price_watchlist')->insertOrIgnore($records);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_watchlist');
        Schema::dropIfExists('market_price_current');
    }
};
