<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buyback_system_status', function (Blueprint $table) {
            $table->string('status_key', 100)->primary();
            $table->text('status_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyback_system_status');
    }
};
