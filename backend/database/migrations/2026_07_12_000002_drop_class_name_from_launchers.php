<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('launchers', 'class_name')) {
            return;
        }

        Schema::table('launchers', function (Blueprint $table) {
            $table->dropColumn('class_name');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('launchers', 'class_name')) {
            return;
        }

        Schema::table('launchers', function (Blueprint $table) {
            $table->string('class_name')->after('output_schema');
        });
    }
};
