<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_hidden_launchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('launcher_id')->constrained('launchers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'launcher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hidden_launchers');
    }
};
