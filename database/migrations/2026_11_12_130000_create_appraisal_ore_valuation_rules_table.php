<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appraisal_ore_valuation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ore_type_id')->unique();
            $table->string('ore_name', 255);
            $table->string('valuation_mode', 24)->default('compressed');
            $table->unsignedBigInteger('output_type_id')->nullable();
            $table->string('output_name', 255)->nullable();
            $table->decimal('output_quantity_per_input', 20, 8)->nullable();
            $table->timestamps();
            $table->index('valuation_mode');
        });

        // Preserve the requested default if SDE item data was already imported.
        $ore = DB::table('eve_item_types')->where('type_name', 'Compressed Mercoxit')->first(['type_id', 'type_name']);
        $morphite = DB::table('eve_item_types')->where('type_id', 11399)->first(['type_id', 'type_name']);
        if ($ore && $morphite) {
            DB::table('appraisal_ore_valuation_rules')->insert([
                'ore_type_id' => $ore->type_id,
                'ore_name' => $ore->type_name,
                'valuation_mode' => 'reprocessed',
                'output_type_id' => $morphite->type_id,
                'output_name' => $morphite->type_name,
                'output_quantity_per_input' => 1.2688,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_ore_valuation_rules');
    }
};
