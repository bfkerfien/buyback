<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('role', 24)->default('member')->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('eve_characters', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->index(['user_id', 'eve_character_id']);
        });

        Schema::table('appraisal_quotes', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('eve_character_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('runner_jobs', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->after('runner_character_id')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
        });

        // Financier requests were removed from the product. Do not recreate or alter
        // their retired table as part of a fresh Buyback installation.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::table('runner_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('notes');
        });

        Schema::table('appraisal_quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('eve_characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['access_token', 'refresh_token', 'token_expires_at', 'scopes']);
        });

        Schema::dropIfExists('users');
    }
};
