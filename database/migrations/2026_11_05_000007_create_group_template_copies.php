<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_template_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('source_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('work_groups')->cascadeOnDelete();
            $table->foreignUlid('document_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('request_key');
            $table->timestamps();
            $table->unique(['user_id', 'request_key', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_template_copies');
    }
};
