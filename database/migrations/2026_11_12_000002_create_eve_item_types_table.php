<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eve_item_types', function (Blueprint $table) {
            $table->unsignedBigInteger('type_id')->primary();
            $table->string('type_name', 255)->unique();
            $table->boolean('published')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eve_item_types');
    }
};
