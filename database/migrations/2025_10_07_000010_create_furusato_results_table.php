<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('furusato_results')) {
            return;
        }

        Schema::create('furusato_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('data_id')->unique();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->json('payload');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('furusato_results');
    }
};