<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appraisal_item_payout_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('type_id')->unique();
            $table->string('type_name');
            $table->decimal('payout_multiplier', 12, 6)->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_item_payout_overrides');
    }
};
