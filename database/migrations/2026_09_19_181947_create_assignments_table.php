<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Le prof se déduit de la classe : pas de colonne à tenir cohérente.
            $table->foreignUlid('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('instructions');
            $table->string('type', 20);
            $table->timestamp('due_at')->nullable();
            // Contenus de document, stockés tels quels, jamais interprétés.
            $table->longText('base_content')->nullable();
            $table->longText('solution_content')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('solution_released_at')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_mime', 50)->nullable();
            $table->unsignedInteger('image_size')->nullable();
            $table->timestamps();

            $table->index(['classroom_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
