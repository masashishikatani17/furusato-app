<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            if (!Schema::hasColumn('guests', 'client_user_id')) {
                // client（顧問先）ログインアカウントと紐付ける（1 client = 1 guest）
                $table->unsignedBigInteger('client_user_id')->nullable()->after('user_id');
                $table->unique('client_user_id', 'guests_client_user_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            if (Schema::hasColumn('guests', 'client_user_id')) {
                // unique → column の順で落とす
                try {
                    $table->dropUnique('guests_client_user_id_unique');
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('client_user_id');
            }
        });
    }
};
