<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_resources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->ulid('source_id');
            $table->string('title', 200);
            $table->unsignedInteger('version')->default(1);
            $table->longText('payload');
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->timestamps();
        });
        Schema::create('teaching_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('resource_id')->constrained('teaching_resources')->cascadeOnDelete();
            $table->foreignUlid('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->timestamps();
            $table->unique(['resource_id', 'classroom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_publications');
        Schema::dropIfExists('teaching_resources');
    }
};
