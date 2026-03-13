<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_billing_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->unique();

            $table->string('payment_method', 32);
            $table->string('billing_code', 64)->nullable();
            $table->string('billing_individual_code', 64)->nullable();
            $table->string('payment_method_code', 64)->nullable();

            $table->unsignedTinyInteger('bank_account_type')->nullable();
            $table->string('bank_code', 4)->nullable();
            $table->string('branch_code', 5)->nullable();
            $table->string('bank_account_number', 8)->nullable();
            $table->string('bank_account_name', 30)->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_billing_settings');
    }
};