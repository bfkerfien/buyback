<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eve_contract_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eve_character_id')->constrained('eve_characters')->cascadeOnDelete();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('issuer_id')->nullable();
            $table->string('contract_type', 32)->nullable();
            $table->string('contract_status', 32)->nullable();
            $table->timestamp('date_issued')->nullable();
            $table->string('notification_status', 20)->default('pending')->index();
            $table->json('contract_data')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['eve_character_id', 'contract_id'], 'eve_contract_char_contract_unique');
            $table->index(['notification_status', 'date_issued'], 'eve_contract_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eve_contract_notification_logs');
    }
};
