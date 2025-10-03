<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('guests')) {
            Schema::create('guests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255)->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index(); // 作成者など
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // 既存データ保護のため drop は行わない（必要時のみ手動）
        // Schema::dropIfExists('guests');
    }
};