<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_price_snapshots', function (Blueprint $table) {
            // The ore/PI valuation pages find MAX(id) per type within one region,
            // while ignoring observations without a usable buy price. This
            // covering index lets MariaDB group and scan those rows by region,
            // type, and newest snapshot without repeatedly scanning the table.
            $table->index(
                ['region_id', 'type_id', 'id', 'buy_price'],
                'market_price_latest_buy_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('market_price_snapshots', function (Blueprint $table) {
            $table->dropIndex('market_price_latest_buy_lookup_idx');
        });
    }
};
