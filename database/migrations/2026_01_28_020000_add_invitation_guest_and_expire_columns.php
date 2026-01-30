<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (!Schema::hasColumn('invitations', 'guest_id')) {
                $table->unsignedBigInteger('guest_id')->nullable()->after('group_id');
                $table->index(['company_id', 'guest_id'], 'invitations_company_guest_index');
            }
            if (!Schema::hasColumn('invitations', 'guest_name')) {
                $table->string('guest_name', 25)->nullable()->after('guest_id');
                $table->index(['company_id', 'guest_name'], 'invitations_company_guest_name_index');
            }
            if (!Schema::hasColumn('invitations', 'expired_at')) {
                // Scheduler が期限切れを確定した時刻（監査ログ二重防止のSoT）
                $table->timestamp('expired_at')->nullable()->after('expires_at');
                $table->index(['expired_at'], 'invitations_expired_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (Schema::hasColumn('invitations', 'expired_at')) {
                try { $table->dropIndex('invitations_expired_at_index'); } catch (\Throwable $e) {}
                $table->dropColumn('expired_at');
            }
            if (Schema::hasColumn('invitations', 'guest_name')) {
                try { $table->dropIndex('invitations_company_guest_name_index'); } catch (\Throwable $e) {}
                $table->dropColumn('guest_name');
            }
            if (Schema::hasColumn('invitations', 'guest_id')) {
                try { $table->dropIndex('invitations_company_guest_index'); } catch (\Throwable $e) {}
                $table->dropColumn('guest_id');
            }
        });
    }
};
