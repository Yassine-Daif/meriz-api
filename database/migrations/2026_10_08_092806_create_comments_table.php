<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            // L'auteur. Il vient toujours du jeton.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            // Position de la bulle dans le repère du schéma, stockée telle
            // quelle et jamais interprétée. Les deux ensemble, ou aucune :
            // sans position, c'est un commentaire général.
            $table->double('position_x')->nullable();
            $table->double('position_y')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
