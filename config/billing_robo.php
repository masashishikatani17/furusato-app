<?php

return [
    // API base (example: https://api.billing-robo.jp)
    'base_url' => rtrim((string) env('BILLING_ROBO_BASE_URL', ''), '/'),

    // 支払手続きURL（画面に表示するだけ：ロボ側のURLに誘導）
    'payment_url' => (string) env('BILLING_ROBO_PAYMENT_URL', ''),

    // API共通認証（ロボ仕様：user_id + access_key）
    'user_id' => (string) env('BILLING_ROBO_USER_ID', ''),
    'access_key' => (string) env('BILLING_ROBO_ACCESS_KEY', ''),

    // 5人プラン（1商品）のロボ商品コード
    'item_code_5seats' => (string) env('BILLING_ROBO_ITEM_CODE_5SEATS', '0001'),

    // 銀行振込の振込パターンコード（ロボの運用設定の実値に合わせること）
    // 省略時は請求管理ロボ画面の実機確認値に合わせて「1」を使用する。
    'bank_transfer_pattern_code' => (string) env('BILLING_ROBO_BANK_TRANSFER_PATTERN_CODE', '1'),

    // 税（運用に合わせて後で調整）
    // tax_category: 0/1 等（ロボ仕様に合わせる）
    // tax: 10/8 等（ロボ仕様に合わせる）
    'tax_category' => (int) env('BILLING_ROBO_TAX_CATEGORY', 0),
    'tax' => (int) env('BILLING_ROBO_TAX', 10),
];
