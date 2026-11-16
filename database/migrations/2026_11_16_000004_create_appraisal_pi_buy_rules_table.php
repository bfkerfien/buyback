<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appraisal_pi_buy_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('type_id')->unique();
            $table->string('type_name', 255);
            $table->decimal('buy_percent', 5, 2);
            $table->timestamps();
            $table->index('type_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_pi_buy_rules');
    }
};
