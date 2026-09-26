<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Trace de transparence : quand ce travail a été consulté par le
            // prof pour la dernière fois. L'élève la voit.
            $table->timestamp('last_observed_at')->nullable()->after('assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('last_observed_at');
        });
    }
};
