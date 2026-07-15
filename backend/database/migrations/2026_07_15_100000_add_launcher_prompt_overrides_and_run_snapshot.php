<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launcher_prompt_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('launcher_id')->constrained()->cascadeOnDelete();
            $table->text('prompt_template');
            $table->timestamps();

            $table->unique(['user_id', 'launcher_id']);
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->text('prompt_snapshot')->nullable()->after('input');
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('prompt_snapshot');
        });

        Schema::dropIfExists('launcher_prompt_overrides');
    }
};
