<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Couleurs de la pastille d'initiales, en hex (#aabbcc).
            // Nulles : les valeurs par défaut s'appliquent à la lecture.
            $table->string('avatar_bg', 7)->nullable()->after('contact_shared');
            $table->string('avatar_fg', 7)->nullable()->after('avatar_bg');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_bg', 'avatar_fg']);
        });
    }
};
