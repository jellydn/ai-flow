<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launchers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->text('prompt_template');
            $table->string('input_type');
            $table->json('output_schema');
            $table->string('class_name');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('launcher_id')->constrained()->cascadeOnDelete();
            $table->text('source_url');
            $table->string('status')->default('queued')->index();
            $table->json('progress');
            $table->json('input');
            $table->json('source_context')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runs');
        Schema::dropIfExists('launchers');
    }
};
