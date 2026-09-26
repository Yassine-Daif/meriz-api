<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Suivi en direct : jamais actif par défaut, c'est le prof qui
            // l'ouvre, et l'élève voit le drapeau.
            $table->boolean('live_tracking')->default(false)->after('solution_released_at');
            $table->timestamp('live_tracking_enabled_at')->nullable()->after('live_tracking');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['live_tracking', 'live_tracking_enabled_at']);
        });
    }
};
