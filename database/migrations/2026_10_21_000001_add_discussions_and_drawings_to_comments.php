<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignUlid('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            // Annotation visuelle séparée du contenu Merise, soumise aux mêmes droits.
            $table->json('drawing')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('drawing');
        });
    }
};
