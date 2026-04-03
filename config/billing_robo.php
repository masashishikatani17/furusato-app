<?php

return [
    // API base (example: https://api.billing-robo.jp)
    'base_url' => rtrim((string) env('BILLING_ROBO_BASE_URL', ''), '/'),

    // 支払手続きURL（画面に表示するだけ：ロボ側のURLに誘導）
    'payment_url' => (string) env('BILLING_ROBO_PAYMENT_URL', ''),

    // API共通認証（ロボ仕様：user_id  access_key）
    'user_id' => (string) env('BILLING_ROBO_USER_ID', ''),
    'access_key' => (string) env('BILLING_ROBO_ACCESS_KEY', ''),

    // 5人プラン（既存互換用）のロボ商品コード
    'item_code_5seats' => (string) env('BILLING_ROBO_ITEM_CODE_5SEATS', '0001'),
 
    // 初回申込・途中追加など単発請求の商品コード
    // 未設定時は既存の BILLING_ROBO_ITEM_CODE_5SEATS を流用する
    'initial_item_code_5seats' => (string) env('BILLING_ROBO_INITIAL_ITEM_CODE_5SEATS', env('BILLING_ROBO_ITEM_CODE_5SEATS', '0001')),

    // 翌年度以降の定期請求マスタの商品コード
    // 未設定時は既存の BILLING_ROBO_ITEM_CODE_5SEATS を流用する
    'recurring_item_code_5seats' => (string) env('BILLING_ROBO_RECURRING_ITEM_CODE_5SEATS', env('BILLING_ROBO_ITEM_CODE_5SEATS', '0001')),

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
    // 初期は 1(自動メール)
    'billing_method' => (int) env('BILLING_ROBO_BILLING_METHOD', 1),

    // Webhook管理画面に表示される BillingRoboSignaturekey
    // デモ/開発で未設定の場合は署名検証をスキップする
    'webhook_signature_key' => (string) env('BILLING_ROBO_WEBHOOK_SIGNATURE_KEY', ''),

    // 年次定期契約の基準設定
    'fiscal_year_start_month' => (int) env('BILLING_ROBO_FISCAL_YEAR_START_MONTH', 4),
    'bank_transfer_recurring_issue_day' => (int) env('BILLING_ROBO_BANK_TRANSFER_RECURRING_ISSUE_DAY', 15),
    'credit_recurring_issue_day' => (int) env('BILLING_ROBO_CREDIT_RECURRING_ISSUE_DAY', 25),
    'recurring_deadline_day' => (int) env('BILLING_ROBO_RECURRING_DEADLINE_DAY', 99),
    'payment_grace_period_days' => (int) env('BILLING_ROBO_PAYMENT_GRACE_PERIOD_DAYS', 7),

    // 請求書テンプレートコード
    'bill_template_code' => (int) env('BILLING_ROBO_BILL_TEMPLATE_CODE', 10000),

    // クレジットカード登録（web決済フォーム）
    // 契約時に発行された決済システムの店舗ID
    'credit_aid' => (string) env('BILLING_ROBO_CREDIT_AID', ''),
    'credit_registration_link_expire_minutes' => (int) env('BILLING_ROBO_CREDIT_REGISTRATION_LINK_EXPIRE_MINUTES', 1440),
    'credit_jquery_js_url' => (string) env('BILLING_ROBO_CREDIT_JQUERY_JS_URL', 'https://credit.j-payment.co.jp/gateway/js/jquery.js'),
    'credit_token_js_url' => (string) env('BILLING_ROBO_CREDIT_TOKEN_JS_URL', 'https://credit.j-payment.co.jp/gateway/js/CPToken.js'),
    'credit_emv3ds_js_url' => (string) env('BILLING_ROBO_CREDIT_EMV3DS_JS_URL', 'https://credit.j-payment.co.jp/gateway/js/EMV3DSAdapter.js'),
    // 初回クレカ請求の未払い猶予日数
    'credit_initial_grace_days' => (int) env('BILLING_ROBO_CREDIT_INITIAL_GRACE_DAYS', 7),
 
    // 初回クレカ決済の実行方式
    // - bulk_register: カード登録完了直後に即時決済APIで初回決済まで実行する
    // - issue_bill: 単発請求書を発行し、bill/search 差分同期で決済結果を追う（従来方式）
    'credit_initial_charge_mode' => (string) env('BILLING_ROBO_CREDIT_INITIAL_CHARGE_MODE', 'bulk_register'),

    // 即時決済API成功時に請求書番号を bill/search で補完する探索窓
    'credit_initial_immediate_bill_search_window_seconds' => (int) env('BILLING_ROBO_CREDIT_INITIAL_IMMEDIATE_BILL_SEARCH_WINDOW_SECONDS', 120),

    // 初回クレカ決済成功の差分ポーリング設定（bill/search の update_date 差分参照）
    'credit_initial_poll_lookback_minutes' => (int) env('BILLING_ROBO_CREDIT_INITIAL_POLL_LOOKBACK_MINUTES', 180),
    'credit_initial_poll_limit' => (int) env('BILLING_ROBO_CREDIT_INITIAL_POLL_LIMIT', 100),
    'credit_initial_poll_overlap_seconds' => (int) env('BILLING_ROBO_CREDIT_INITIAL_POLL_OVERLAP_SECONDS', 30),

    // 税（運用に合わせて後で調整）
    // tax_category: 0/1 等（ロボ仕様に合わせる）
    // tax: 10/8 等（ロボ仕様に合わせる）
    'tax_category' => (int) env('BILLING_ROBO_TAX_CATEGORY', 0),
    'tax' => (int) env('BILLING_ROBO_TAX', 10),
];
