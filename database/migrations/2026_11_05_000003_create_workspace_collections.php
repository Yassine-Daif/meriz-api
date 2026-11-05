<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_collections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 16);
            $table->unsignedSmallInteger('year_start')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'kind', 'year_start']);
        });
        Schema::create('workspace_collection_classroom', function (Blueprint $table) {
            $table->foreignUlid('collection_id')->constrained('workspace_collections')->cascadeOnDelete();
            $table->foreignUlid('classroom_id')->constrained()->cascadeOnDelete();
            $table->primary(['collection_id', 'classroom_id']);
        });
        Schema::create('workspace_collection_group', function (Blueprint $table) {
            $table->foreignUlid('collection_id')->constrained('workspace_collections')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('work_groups')->cascadeOnDelete();
            $table->primary(['collection_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_collection_group');
        Schema::dropIfExists('workspace_collection_classroom');
        Schema::dropIfExists('workspace_collections');
    }
};
