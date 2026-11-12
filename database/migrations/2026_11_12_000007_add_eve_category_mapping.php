<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('eve_item_types', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->index();
        });
        Schema::create('eve_item_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->primary();
            $table->unsignedBigInteger('category_id')->index();
            $table->string('group_name', 255);
            $table->string('category_name', 120)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eve_item_groups');
        Schema::table('eve_item_types', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });
    }
};
