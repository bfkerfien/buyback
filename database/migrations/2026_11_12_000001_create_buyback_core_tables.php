<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eve_characters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eve_character_id')->unique();
            $table->string('name');
            $table->string('owner_hash', 64)->nullable();
            $table->timestamp('last_authenticated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('appraisal_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eve_character_id')->nullable()->constrained('eve_characters')->nullOnDelete();
            $table->string('public_token', 64)->unique();
            $table->string('origin_system')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('price_source', 64)->nullable();
            $table->timestamp('priced_at')->nullable();
            $table->unsignedInteger('calculation_version')->default(1);
            $table->decimal('gross_value', 20, 2)->default(0);
            $table->decimal('fees', 20, 2)->default(0);
            $table->decimal('payout_value', 20, 2)->default(0);
            $table->json('pricing_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('appraisal_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('type_id')->index();
            $table->string('type_name');
            $table->unsignedBigInteger('quantity');
            $table->decimal('unit_price', 20, 4)->default(0);
            $table->decimal('line_value', 20, 2)->default(0);
            $table->json('source_data')->nullable();
            $table->timestamps();
        });

        Schema::create('market_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('type_id')->index();
            $table->string('region_id', 32)->default('10000002');
            $table->string('source', 64);
            $table->decimal('buy_price', 20, 4)->nullable();
            $table->decimal('sell_price', 20, 4)->nullable();
            $table->timestamp('observed_at')->index();
            $table->timestamps();
            $table->index(['type_id', 'region_id', 'observed_at']);
        });

        Schema::create('runner_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('runner_character_id')->nullable()->constrained('eve_characters')->nullOnDelete();
            $table->string('status', 24)->default('open')->index();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->decimal('reward_isk', 20, 2)->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('verification_data')->nullable();
            $table->timestamps();
        });

        Schema::create('financier_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financier_character_id')->nullable()->constrained('eve_characters')->nullOnDelete();
            $table->string('request_type', 24)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->decimal('amount_isk', 20, 2);
            $table->string('external_reference')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('verification_data')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eve_character_id')->nullable()->constrained('eve_characters')->nullOnDelete();
            $table->string('entry_type', 32)->index();
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount_isk', 20, 2);
            $table->string('idempotency_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('financier_requests');
        Schema::dropIfExists('runner_jobs');
        Schema::dropIfExists('market_price_snapshots');
        Schema::dropIfExists('appraisal_quote_items');
        Schema::dropIfExists('appraisal_quotes');
        Schema::dropIfExists('eve_characters');
    }
};
