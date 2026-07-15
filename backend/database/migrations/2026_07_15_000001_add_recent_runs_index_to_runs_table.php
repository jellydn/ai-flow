<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->index(['status', 'user_id', 'completed_at'], 'runs_status_user_completed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropIndex('runs_status_user_completed_at_index');
        });
    }
};
