<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tokurei_rates');

        Schema::create('tokurei_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('lower')->nullable();
            $table->unsignedBigInteger('upper')->nullable();
            $table->decimal('income_rate', 6, 3);
            $table->decimal('ninety_minus_rate', 6, 3);
            $table->decimal('income_rate_with_recon', 6, 3);
            $table->decimal('tokurei_deduction_rate', 6, 3);
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'year', 'sort'], 'tr_main');
            $table->index('year', 'tr_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokurei_rates');
    }
};