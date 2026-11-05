<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_institutions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 200);
            $table->char('country', 2);
            $table->timestamps();
        });
        Schema::create('education_referents', function (Blueprint $table) {
            $table->foreignUlid('institution_id')->constrained('education_institutions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['institution_id', 'user_id']);
        });
        Schema::create('education_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('institution_id')->constrained('education_institutions')->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('education_institution_id')->nullable()->constrained('education_institutions')->nullOnDelete();
            $table->timestamp('teacher_verified_at')->nullable();
            $table->string('teacher_verification_source', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('education_institution_id');
            $table->dropColumn(['teacher_verified_at', 'teacher_verification_source']);
        });
        Schema::dropIfExists('education_invitations');
        Schema::dropIfExists('education_referents');
        Schema::dropIfExists('education_institutions');
    }
};
