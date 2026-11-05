<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', fn (Blueprint $table) => $table->json('interface_settings')->nullable());
    }

    public function down(): void
    {
        Schema::table('assignments', fn (Blueprint $table) => $table->dropColumn('interface_settings'));
    }
};
