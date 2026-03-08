<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jpayment_webhook_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            // payment / auto_charge
            $table->string('type', 32)->index();

            // 受信した生データ（後で解析してinvoice紐付けに使う）
            $table->longText('raw_payload')->nullable();
            $table->longText('headers')->nullable();

            // 受信環境（追跡用）
            $table->string('remote_ip', 45)->nullable()->index();
            $table->string('user_agent', 255)->nullable();

            // 後で使う可能性が高いヒント（段階2で確定するが、段階1で保存しておく）
            $table->string('iid2', 64)->nullable()->index();          // INV-12345 を想定
            $table->unsignedBigInteger('invoice_id_hint')->nullable()->index();
            $table->string('jp_tid', 64)->nullable()->index();        // 取引ID相当（名称は受信後に確定）
            $table->string('result_code', 32)->nullable()->index();   // 成功/失敗等（名称は受信後に確定）

            $table->timestamp('received_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jpayment_webhook_events');
    }
};