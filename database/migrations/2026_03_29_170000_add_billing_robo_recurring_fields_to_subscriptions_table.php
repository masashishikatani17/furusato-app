<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('billing_robo_managed_recurring')
                ->default(false)
                ->after('billing_code');

            $table->string('billing_robo_master_demand_code', 20)
                ->nullable()
                ->after('billing_robo_managed_recurring');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_robo_managed_recurring',
                'billing_robo_master_demand_code',
            ]);
        });
    }
};