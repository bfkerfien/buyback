<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('users', function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->nullable()->unique();$t->string('role',24)->default('member')->index();$t->boolean('active')->default(true)->index();$t->timestamp('last_login_at')->nullable();$t->rememberToken();$t->timestamps();});
  Schema::table('eve_characters', function(Blueprint $t){$t->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();$t->text('access_token')->nullable();$t->text('refresh_token')->nullable();$t->timestamp('token_expires_at')->nullable();$t->json('scopes')->nullable();$t->index(['user_id','eve_character_id']);});
  Schema::table('appraisal_quotes', function(Blueprint $t){$t->foreignId('created_by')->nullable()->after('eve_character_id')->constrained('users')->nullOnDelete();});
  Schema::table('runner_jobs', function(Blueprint $t){$t->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();$t->foreignId('assigned_user_id')->nullable()->after('runner_character_id')->constrained('users')->nullOnDelete();$t->text('notes')->nullable();});
  Schema::table('financier_requests', function(Blueprint $t){$t->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$t->text('notes')->nullable();$t->timestamp('reviewed_at')->nullable();});
  Schema::create('audit_logs',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->string('action',100)->index();$t->string('subject_type',100)->nullable();$t->unsignedBigInteger('subject_id')->nullable();$t->json('details')->nullable();$t->string('ip_address',45)->nullable();$t->timestamps();$t->index(['subject_type','subject_id']);});
 }
 public function down(): void {
  Schema::dropIfExists('audit_logs');
  Schema::table('financier_requests',function(Blueprint $t){$t->dropConstrainedForeignId('reviewed_by');$t->dropConstrainedForeignId('user_id');$t->dropColumn(['notes','reviewed_at']);});
  Schema::table('runner_jobs',function(Blueprint $t){$t->dropConstrainedForeignId('assigned_user_id');$t->dropConstrainedForeignId('created_by');$t->dropColumn('notes');});
  Schema::table('appraisal_quotes',function(Blueprint $t){$t->dropConstrainedForeignId('created_by');});
  Schema::table('eve_characters',function(Blueprint $t){$t->dropConstrainedForeignId('user_id');$t->dropColumn(['access_token','refresh_token','token_expires_at','scopes']);});
  Schema::dropIfExists('users');
 }
};