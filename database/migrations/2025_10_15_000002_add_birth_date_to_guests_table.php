<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guests')) {
            return;
        }

        Schema::table('guests', function (Blueprint $table) {
            if (! Schema::hasColumn('guests', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guests')) {
            return;
        }

        Schema::table('guests', function (Blueprint $table) {
            if (Schema::hasColumn('guests', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};