<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eve_planet_schematics', function (Blueprint $table) {
            $table->unsignedBigInteger('schematic_id')->primary();
            $table->string('name', 255);
            $table->unsignedInteger('cycle_time');
            $table->unsignedBigInteger('product_type_id')->index();
            $table->unsignedInteger('product_quantity');
            $table->timestamps();
        });

        Schema::create('eve_planet_schematic_inputs', function (Blueprint $table) {
            $table->unsignedBigInteger('schematic_id');
            $table->unsignedBigInteger('type_id');
            $table->unsignedInteger('quantity');
            $table->primary(['schematic_id', 'type_id']);
            $table->index('type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eve_planet_schematic_inputs');
        Schema::dropIfExists('eve_planet_schematics');
    }
};
