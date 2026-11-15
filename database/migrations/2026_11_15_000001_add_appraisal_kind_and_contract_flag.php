<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appraisal_quotes', function (Blueprint $table) {
            $table->string('appraisal_kind', 24)->default('standard')->after('status')->index();
            $table->timestamp('contracted_at')->nullable()->index();
            $table->foreignId('contracted_by')->nullable()->after('contracted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contracted_by');
            $table->dropColumn(['appraisal_kind', 'contracted_at']);
        });
    }
};
