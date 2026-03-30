<?php

return [
    // API base (example: https://api.billing-robo.jp)
    'base_url' => rtrim((string) env('BILLING_ROBO_BASE_URL', ''), '/'),

    // 支払手続きURL（画面に表示するだけ：ロボ側のURLに誘導）
    'payment_url' => (string) env('BILLING_ROBO_PAYMENT_URL', ''),

    // API共通認証（ロボ仕様：user_id  access_key）
    'user_id' => (string) env('BILLING_ROBO_USER_ID', ''),
    'access_key' => (string) env('BILLING_ROBO_ACCESS_KEY', ''),

    // 5人プラン（1商品）のロボ商品コード
    'item_code_5seats' => (string) env('BILLING_ROBO_ITEM_CODE_5SEATS', '0001'),

    // 銀行振込の振込パターンコード（ロボの運用設定の実値に合わせること）
    // 省略時は請求管理ロボ画面の実機確認値に合わせて「1」を使用する。
    'bank_transfer_pattern_code' => (string) env('BILLING_ROBO_BANK_TRANSFER_PATTERN_CODE', '1'),

    // 請求方法
    // 0:送付なし
    // 1:自動メール
    // 2:手動メール
    // 3:自動郵送
    // 4:手動郵送
    // 5:自動メール+自動郵送
    // 6:手動メール+手動郵送
    // 7:自動マイページ
    // 8:手動マイページ
    // 初期は 2(手動メール) にしておく
    'billing_method' => (int) env('BILLING_ROBO_BILLING_METHOD', 2),

    // 請求書テンプレートコード
    'bill_template_code' => (int) env('BILLING_ROBO_BILL_TEMPLATE_CODE', 10000),

    // クレジットカード登録（web決済フォーム）
    // 契約時に発行された決済システムの店舗ID
    'credit_aid' => (string) env('BILLING_ROBO_CREDIT_AID', ''),
    'credit_registration_link_expire_minutes' => (int) env('BILLING_ROBO_CREDIT_REGISTRATION_LINK_EXPIRE_MINUTES', 1440),
    'credit_jquery_js_url' => (string) env('BILLING_ROBO_CREDIT_JQUERY_JS_URL', 'https://credit.j-payment.co.jp/gateway/js/jquery.js'),
    'credit_token_js_url' => (string) env('BILLING_ROBO_CREDIT_TOKEN_JS_URL', 'https://credit.j-payment.co.jp/gateway/js/CPToken.js'),
    'credit_emv3ds_js_url' => (string) env('BILLING_ROBO_CREDIT_EMV3DS_JS_URL', 'https://credit.j-payment.co.jp/gateway/js/EMV3DSAdapter.js'),
 
    // 税（運用に合わせて後で調整）
    // tax_category: 0/1 等（ロボ仕様に合わせる）
    // tax: 10/8 等（ロボ仕様に合わせる）
    'tax_category' => (int) env('BILLING_ROBO_TAX_CATEGORY', 0),
    'tax' => (int) env('BILLING_ROBO_TAX', 10),
];
