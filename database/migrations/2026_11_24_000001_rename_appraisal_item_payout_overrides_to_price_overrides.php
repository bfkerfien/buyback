<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::rename('appraisal_item_payout_overrides', 'appraisal_item_price_overrides');

        Schema::table('appraisal_item_price_overrides', function (Blueprint $table): void {
            $table->renameColumn('payout_multiplier', 'price_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_item_price_overrides', function (Blueprint $table): void {
            $table->renameColumn('price_multiplier', 'payout_multiplier');
        });

        Schema::rename('appraisal_item_price_overrides', 'appraisal_item_payout_overrides');
    }
};
