<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Devoir dont ce document est une copie de la base, s'il y en a un.
            // nullOnDelete : supprimer le devoir ne supprime pas le travail
            // de l'élève, il perd seulement son rattachement.
            $table->foreignUlid('assignment_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->index(['user_id', 'assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'assignment_id']);
            $table->dropConstrainedForeignId('assignment_id');
        });
    }
};
