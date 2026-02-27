<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // payload は longtext だが、JSON文字列として JSON_* 関数で更新している前提。
        // 安全のため JSON_VALID(payload)=1 の行だけを対象にする。

        // ============================================================
        // 1) furusato_inputs（トップレベルJSON）
        //    - 旧キー削除
        //    - *_sogo_* が未設定(null/欠落)なら syunyu-keihi で再計算して埋める
        // ============================================================
        DB::statement(<<<'SQL'
UPDATE furusato_inputs
SET payload =
  JSON_SET(
    JSON_REMOVE(
      payload,
      '$.sashihiki_joto_tanki_prev',
      '$.sashihiki_joto_tanki_curr',
      '$.sashihiki_joto_choki_prev',
      '$.sashihiki_joto_choki_curr',
      '$.tsusango_joto_tanki_prev',
      '$.tsusango_joto_tanki_curr',
      '$.tsusango_joto_choki_prev',
      '$.tsusango_joto_choki_curr'
    ),
    '$.sashihiki_joto_tanki_sogo_prev',
      IF(
        JSON_EXTRACT(payload,'$.sashihiki_joto_tanki_sogo_prev') IS NULL,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.syunyu_joto_tanki_prev')) AS SIGNED)
          - CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.keihi_joto_tanki_prev')) AS SIGNED),
        JSON_EXTRACT(payload,'$.sashihiki_joto_tanki_sogo_prev')
      ),
    '$.sashihiki_joto_tanki_sogo_curr',
      IF(
        JSON_EXTRACT(payload,'$.sashihiki_joto_tanki_sogo_curr') IS NULL,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.syunyu_joto_tanki_curr')) AS SIGNED)
          - CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.keihi_joto_tanki_curr')) AS SIGNED),
        JSON_EXTRACT(payload,'$.sashihiki_joto_tanki_sogo_curr')
      ),
    '$.sashihiki_joto_choki_sogo_prev',
      IF(
        JSON_EXTRACT(payload,'$.sashihiki_joto_choki_sogo_prev') IS NULL,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.syunyu_joto_choki_prev')) AS SIGNED)
          - CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.keihi_joto_choki_prev')) AS SIGNED),
        JSON_EXTRACT(payload,'$.sashihiki_joto_choki_sogo_prev')
      ),
    '$.sashihiki_joto_choki_sogo_curr',
      IF(
        JSON_EXTRACT(payload,'$.sashihiki_joto_choki_sogo_curr') IS NULL,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.syunyu_joto_choki_curr')) AS SIGNED)
          - CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.keihi_joto_choki_curr')) AS SIGNED),
        JSON_EXTRACT(payload,'$.sashihiki_joto_choki_sogo_curr')
      )
  )
WHERE JSON_VALID(payload) = 1;
SQL);

        // ============================================================
        // 2) furusato_results（トップが {details,payload,upper,...} 形式）
        //    - payload.* と upper.* の両方から旧キーを削除
        //    - *_sogo_* が未設定(null/欠落)なら、payload.sy/ke から再計算して埋める
        // ============================================================
        DB::statement(<<<'SQL'
UPDATE furusato_results
SET payload =
  JSON_SET(
    JSON_REMOVE(
      payload,
      '$.payload.sashihiki_joto_tanki_prev',
      '$.payload.sashihiki_joto_tanki_curr',
      '$.payload.sashihiki_joto_choki_prev',
      '$.payload.sashihiki_joto_choki_curr',
      '$.payload.tsusango_joto_tanki_prev',
      '$.payload.tsusango_joto_tanki_curr',
      '$.payload.tsusango_joto_choki_prev',
      '$.payload.tsusango_joto_choki_curr',
      '$.upper.sashihiki_joto_tanki_prev',
      '$.upper.sashihiki_joto_tanki_curr',
      '$.upper.sashihiki_joto_choki_prev',
      '$.upper.sashihiki_joto_choki_curr',
      '$.upper.tsusango_joto_tanki_prev',
      '$.upper.tsusango_joto_tanki_curr',
      '$.upper.tsusango_joto_choki_prev',
      '$.upper.tsusango_joto_choki_curr'
    ),
    '$.payload.sashihiki_joto_tanki_sogo_prev',
      IF(
        JSON_EXTRACT(payload,'$.payload.sashihiki_joto_tanki_sogo_prev') IS NULL,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payload.syunyu_joto_tanki_prev')) AS SIGNED)
          - CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payload.keihi_joto_tanki_prev')) AS SIGNED),
        JSON_EXTRACT(payload,'$.payload.sashihiki_joto_tanki_sogo_prev')
      ),
    '$.payload.sashihiki_joto_tanki_sogo_curr',
      IF(
        JSON_EXTRACT(payload,'$.payload.sashihiki_joto_tanki_sogo_curr') IS NULL,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payload.syunyu_joto_tanki_curr')) AS SIGNED)
          - CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payload.keihi_joto_tanki_curr')) AS SIGNED),
        JSON_EXTRACT(payload,'$.payload.sashihiki_joto_tanki_sogo_curr')
      ),
    '$.payload.sashihiki_joto_choki_sogo_prev',
      IF(
        JSON_EXTRACT(payload,'$.payload.sashihiki_joto_choki_sogo_prev') IS NULL,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payload.syunyu_joto_choki_prev')) AS SIGNED)
          - CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payload.keihi_joto_choki_prev')) AS SIGNED),
        JSON_EXTRACT(payload,'$.payload.sashihiki_joto_choki_sogo_prev')
      ),
    '$.payload.sashihiki_joto_choki_sogo_curr',
      IF(
        JSON_EXTRACT(payload,'$.payload.sashihiki_joto_choki_sogo_curr') IS NULL,
        CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payload.syunyu_joto_choki_curr')) AS SIGNED)
          - CAST(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.payload.keihi_joto_choki_curr')) AS SIGNED),
        JSON_EXTRACT(payload,'$.payload.sashihiki_joto_choki_sogo_curr')
      ),

    -- upper も同じ値で揃える（互換）
    '$.upper.sashihiki_joto_tanki_sogo_prev', JSON_EXTRACT(payload,'$.payload.sashihiki_joto_tanki_sogo_prev'),
    '$.upper.sashihiki_joto_tanki_sogo_curr', JSON_EXTRACT(payload,'$.payload.sashihiki_joto_tanki_sogo_curr'),
    '$.upper.sashihiki_joto_choki_sogo_prev', JSON_EXTRACT(payload,'$.payload.sashihiki_joto_choki_sogo_prev'),
    '$.upper.sashihiki_joto_choki_sogo_curr', JSON_EXTRACT(payload,'$.payload.sashihiki_joto_choki_sogo_curr')
  )
WHERE JSON_VALID(payload) = 1;
SQL);
    }

    public function down(): void
    {
        // 旧キーは廃止方針のため復元しない。
    }
};
