<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('datas')) {
            Schema::create('datas', function (Blueprint $table) {
                $table->bigIncrements('id');
                // 最低限：右ペインで使用する列と将来の認可で使う親キー
                $table->unsignedBigInteger('guest_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('owner_user_id')->nullable()->index();
                // ふるさと納税の寄付年（今回の主目的）
                $table->integer('kihu_year')->nullable()->comment('寄付年(YYYY)');
                // 既存互換のために最低限保持（将来利用する場合に備え）
                $table->string('name', 100)->nullable();
                $table->date('date')->nullable();
                $table->string('visibility', 20)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // 既存データ保護のため dropTable は実施しない（必要なら明示的に作成）
        // Schema::dropIfExists('datas');
    }
};