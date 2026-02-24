<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // 申込時点の選択値（たたき台：文字列のまま保存）
            if (!Schema::hasColumn('companies', 'signup_plan')) {
                $table->string('signup_plan', 255)->nullable()->after('name');
            }
            if (!Schema::hasColumn('companies', 'signup_payment_method')) {
                $table->string('signup_payment_method', 255)->nullable()->after('signup_plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'signup_payment_method')) {
                $table->dropColumn('signup_payment_method');
            }
            if (Schema::hasColumn('companies', 'signup_plan')) {
                $table->dropColumn('signup_plan');
            }
        });
    }
};

