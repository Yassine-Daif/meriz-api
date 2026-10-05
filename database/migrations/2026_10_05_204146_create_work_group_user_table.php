<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_group_user', function (Blueprint $table) {
            $table->foreignUlid('group_id')->constrained('work_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // created_at sert de date d'arrivée dans le groupe.
            $table->timestamps();

            $table->primary(['group_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_group_user');
    }
};
