<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pi_tax_settings', function (Blueprint $table) {
            $table->decimal('target_profit_percent', 5, 2)->default(18)->after('export_tax_percent');
        });
    }

    public function down(): void
    {
        Schema::table('pi_tax_settings', function (Blueprint $table) {
            $table->dropColumn('target_profit_percent');
        });
    }
};
