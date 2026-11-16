<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_order_book_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('type_id')->index();
            $table->string('region_id', 32)->default('10000002');
            $table->unsignedBigInteger('location_id')->index();
            $table->boolean('is_buy_order')->default(true);
            $table->decimal('price', 20, 4);
            $table->unsignedBigInteger('volume_remain');
            $table->timestamp('observed_at')->index();
            $table->timestamps();
            $table->index(['type_id', 'region_id', 'is_buy_order', 'observed_at'], 'market_depth_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_order_book_snapshots');
    }
};