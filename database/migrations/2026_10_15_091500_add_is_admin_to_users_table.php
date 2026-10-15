<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le droit d'entrer dans la zone d'administration.
     *
     * Jamais écrit par le client : il ne se pose qu'en ligne de commande,
     * par meriz:admin. Il est absent de Fillable et de toutes les API
     * Resources, donc ni attribuable ni lisible par l'API.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->index()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
