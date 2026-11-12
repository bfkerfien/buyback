<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appraisal_quotes', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('appraisal_quote_hidden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_quote_id')->constrained('appraisal_quotes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['appraisal_quote_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_quote_hidden');

        Schema::table('appraisal_quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archived_at');
        });
    }
};
