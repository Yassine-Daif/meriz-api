<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('lesson_id')->constrained()->cascadeOnDelete();
            // image ou audio, déduit du contenu réel du fichier.
            $table->string('kind', 10);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            // Nom d'origine nettoyé, pour l'affichage seulement.
            $table->string('name', 150);
            $table->timestamps();

            $table->index(['lesson_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_media');
    }
};
