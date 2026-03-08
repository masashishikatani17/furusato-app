<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * subscription_invoices:
     * - 請求管理ロボの「請求書番号(bill.number)」を保存し、同期のSoTにする履歴テーブル
     * - bill_id はロボ仕様上 number が中心なので bill_number として保持する
     */
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('subscription_id')->index();

            // initial / add_quantity / renewal
            $table->string('kind', 32)->index();

            // pending / issued / paid / failed / canceled
            $table->string('status', 32)->default('pending')->index();

            // 請求管理ロボ：請求書番号（bill.number）
            $table->string('bill_number', 64)->nullable()->index();

            // 監査用（ロボ側のキー）
            $table->string('billing_code', 64)->nullable()->index();
            $table->string('item_code', 64)->nullable()->index();

            // 当時の条件
            $table->string('payment_method', 32)->nullable()->index();
            $table->integer('quantity')->default(1); // 口数（更新は総口数、追加は追加口数）
            $table->integer('unit_price_yen')->default(30000);
            $table->integer('months_charged')->default(12);
            $table->integer('amount_yen')->default(0);

            // 対象期間（請求の意味づけ）
            $table->date('period_start')->nullable()->index();
            $table->date('period_end')->nullable()->index();

            // 請求書発行日/期限（日付）
            $table->date('issue_date')->nullable()->index();
            $table->date('due_date')->nullable()->index();

            // 同期で取得した入金関連（bill/search）
            $table->date('transfer_date')->nullable()->index();
            $table->unsignedTinyInteger('clearing_status')->nullable()->index(); // 0/1/2...
            $table->integer('unclearing_amount')->nullable()->index();

            // 同期管理
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->text('last_sync_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
