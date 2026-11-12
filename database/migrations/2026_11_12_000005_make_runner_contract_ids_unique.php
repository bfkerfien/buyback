<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('runner_jobs', function (Blueprint $table) {
            $table->unique('contract_id');
        });
    }

    public function down(): void
    {
        Schema::table('runner_jobs', function (Blueprint $table) {
            $table->dropUnique(['contract_id']);
        });
    }
};
