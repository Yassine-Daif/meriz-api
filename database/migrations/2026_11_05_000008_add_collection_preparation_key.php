<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_collections', function (Blueprint $table) {
            $table->uuid('preparation_key')->nullable();
            $table->string('preparation_fingerprint', 64)->nullable();
            $table->unique(['user_id', 'preparation_key']);
        });
    }

    public function down(): void
    {
        Schema::table('workspace_collections', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'preparation_key']);
            $table->dropColumn('preparation_key');
            $table->dropColumn('preparation_fingerprint');
        });
    }
};
