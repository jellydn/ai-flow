<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignUuid('provider_credential_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('provider')->nullable()->after('provider_credential_id');
            $table->string('model')->nullable()->after('provider');

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['provider_credential_id']);
            $table->dropColumn(['user_id', 'provider_credential_id', 'provider', 'model']);
        });
    }
};
