<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('eve_item_types', function (Blueprint $table) {
            $table->unsignedInteger('portion_size')->default(1)->after('group_id');
        });

        Schema::create('eve_type_materials', function (Blueprint $table) {
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('material_type_id');
            $table->unsignedBigInteger('quantity');
            $table->timestamps();
            $table->primary(['type_id', 'material_type_id']);
            $table->index('material_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eve_type_materials');
        Schema::table('eve_item_types', function (Blueprint $table) {
            $table->dropColumn('portion_size');
        });
    }
};
