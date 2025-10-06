<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokurei_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedInteger('kihu_year');
            $table->unsignedInteger('version');
            $table->unsignedInteger('seq');
            $table->unsignedBigInteger('lower')->nullable();
            $table->unsignedBigInteger('upper')->nullable();
            $table->decimal('income_rate', 6, 3)->nullable();
            $table->decimal('ninety_minus_rate', 6, 3)->nullable();
            $table->decimal('income_rate_with_recon', 7, 3)->nullable();
            $table->decimal('tokurei_deduction_rate', 7, 3)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'kihu_year', 'version', 'seq'], 'tokurei_rates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokurei_rates');
    }
};