<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pi_tax_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->decimal('import_tax_percent', 8, 4)->default(0);
            $table->decimal('export_tax_percent', 8, 4)->default(0);
            $table->timestamps();
        });

        DB::table('pi_tax_settings')->insert([
            'id' => 1,
            'import_tax_percent' => 0,
            'export_tax_percent' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pi_tax_settings');
    }
};
