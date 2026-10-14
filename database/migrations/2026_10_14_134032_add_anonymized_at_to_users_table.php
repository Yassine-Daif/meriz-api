<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marque un compte supprimé par son titulaire.
     *
     * Pas de SoftDeletes : la ligne doit rester visible des requêtes, car
     * un rendu noté et des commentaires y renvoient encore. Elle est vidée
     * de tout ce qui identifie, et cette date dit qu'elle est éteinte.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->index()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
