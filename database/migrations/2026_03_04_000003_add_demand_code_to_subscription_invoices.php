<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_invoices', 'demand_code')) {
                // demand/bulk_upsert と bulk_issue_bill_select で利用する「請求情報コード」
                $table->string('demand_code', 64)->nullable()->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_invoices', 'demand_code')) {
                $table->dropColumn('demand_code');
            }
        });
    }
};
