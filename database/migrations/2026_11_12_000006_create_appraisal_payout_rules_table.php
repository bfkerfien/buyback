<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appraisal_payout_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('type_id')->unique();
            $table->decimal('payout_percent', 5, 2);
            $table->foreign('type_id')->references('type_id')->on('eve_item_types')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_payout_rules');
    }
};
