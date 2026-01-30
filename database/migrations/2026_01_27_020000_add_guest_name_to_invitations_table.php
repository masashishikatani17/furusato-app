<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (!Schema::hasColumn('invitations', 'guest_name')) {
                // client招待（新規顧問先作成）のため：顧問先名を招待に保持する
                $table->string('guest_name', 25)->nullable()->after('guest_id');
                $table->index(['company_id', 'guest_name'], 'invitations_company_guest_name_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (Schema::hasColumn('invitations', 'guest_name')) {
                try { $table->dropIndex('invitations_company_guest_name_index'); } catch (\Throwable $e) {}
                $table->dropColumn('guest_name');
            }
        });
    }
};