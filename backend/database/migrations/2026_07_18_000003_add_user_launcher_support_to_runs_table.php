<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns (safe on any engine).
        Schema::table('runs', function (Blueprint $table) {
            $table->foreignUuid('user_launcher_id')->nullable()->after('launcher_id');
            $table->foreign('user_launcher_id')->references('id')->on('user_launchers')->nullOnDelete();
            $table->boolean('is_public')->default(false)->after('model');
        });

        // Backfill is_public for existing anonymous runs.
        DB::table('runs')->whereNull('user_id')->update(['is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropForeign(['user_launcher_id']);
            $table->dropColumn(['user_launcher_id', 'is_public']);
        });
    }
};
