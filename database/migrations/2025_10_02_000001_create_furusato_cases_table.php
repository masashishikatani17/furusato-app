<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('furusato_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->boolean('designated_city');
            $table->unsignedBigInteger('gross_income_total');
            $table->unsignedBigInteger('taxable_income_total');
            $table->unsignedBigInteger('personal_diff_excl_base')->default(0);
            $table->boolean('apply_base_diff_50k')->default(true);
            $table->unsignedBigInteger('donation_amount');
            $table->string('filing_method', 16)->default('kakutei');
            $table->json('calc_snapshot')->nullable(); // 結果のスナップショット
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('furusato_cases');
    }
};