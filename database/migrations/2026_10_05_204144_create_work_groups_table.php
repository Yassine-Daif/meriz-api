<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // work_groups, et non groups : GROUPS est un mot réservé de MySQL 8.
        Schema::create('work_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('join_code', 16)->unique();
            $table->timestamps();

            $table->index('creator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_groups');
    }
};
