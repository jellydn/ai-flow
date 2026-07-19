<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_launchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description');
            $table->text('prompt_template');
            $table->string('input_type');
            $table->json('output_schema');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_launchers');
    }
};
