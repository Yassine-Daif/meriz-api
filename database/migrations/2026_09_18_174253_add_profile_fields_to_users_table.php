<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable en base pour les comptes existants, exigé à l'inscription.
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('bio', 280)->nullable()->after('is_academic');
            $table->boolean('bio_shared')->default(false)->after('bio');
            $table->string('contact', 255)->nullable()->after('bio_shared');
            $table->boolean('contact_shared')->default(false)->after('contact');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'bio', 'bio_shared', 'contact', 'contact_shared']);
        });
    }
};
