<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('eve_item_types', function (Blueprint $table) {
            $table->decimal('volume', 18, 6)->nullable()->after('type_name');
        });
    }

    public function down(): void
    {
        Schema::table('eve_item_types', function (Blueprint $table) {
            $table->dropColumn('volume');
        });
    }
};
