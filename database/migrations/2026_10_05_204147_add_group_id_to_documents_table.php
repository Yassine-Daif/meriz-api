<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Document de groupe : espace commun. Nul pour un document
            // personnel. Supprimer le groupe emporte ses documents partagés.
            $table->foreignUlid('group_id')->nullable()->after('assignment_id')
                ->constrained('work_groups')->cascadeOnDelete();
            $table->index(['group_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'updated_at']);
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
