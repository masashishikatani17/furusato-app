<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_billing_settings', function (Blueprint $table): void {
            $table->string('billing_tel', 15)->nullable()->after('payment_method_code');
            $table->unsignedTinyInteger('credit_register_status')->nullable()->after('billing_tel');
            $table->timestamp('credit_registered_at')->nullable()->after('credit_register_status');
            $table->string('credit_last_error_code', 50)->nullable()->after('credit_registered_at');
            $table->string('credit_last_error_message', 255)->nullable()->after('credit_last_error_code');
        });
    }

    public function down(): void
    {
        Schema::table('company_billing_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_tel',
                'credit_register_status',
                'credit_registered_at',
                'credit_last_error_code',
                'credit_last_error_message',
            ]);
        });
    }
};