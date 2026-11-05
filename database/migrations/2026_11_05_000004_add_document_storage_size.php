<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->unsignedBigInteger('content_bytes')->default(0));
        $size = DB::getDriverName() === 'sqlite' ? 'length(CAST(content AS BLOB))' : 'octet_length(content)';
        DB::table('documents')->update(['content_bytes' => DB::raw($size)]);
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('content_bytes'));
    }
};
