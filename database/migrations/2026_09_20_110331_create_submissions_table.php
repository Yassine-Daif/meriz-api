<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Travail de l'élève, stocké tel quel, jamais interprété.
            $table->longText('content');
            // Date du dernier envoi : c'est elle qui décide du retard.
            $table->timestamp('submitted_at');
            $table->string('status', 20)->default('submitted');
            $table->string('grade', 50)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            // Un seul rendu par élève et par devoir, garanti en base.
            $table->unique(['assignment_id', 'user_id']);
            $table->index(['assignment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
