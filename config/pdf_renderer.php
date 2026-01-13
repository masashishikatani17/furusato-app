<?php

return [
    // 'dompdf' or 'chrome'
    // ★プレビューと同じ見た目を最優先するなら 'chrome'
    'engine' => env('PDF_RENDERER_ENGINE', 'dompdf'),

    // ★Browsershot/Puppeteer 実行環境（Cloud9で確実に解決させるため）
    'node_bin' => env('PDF_NODE_BIN', 'node'),
    'npm_bin'  => env('PDF_NPM_BIN', 'npm'),

    // ★プロジェクト内の node_modules を参照（puppeteer をここに入れているため）
    'node_modules_path' => env('PDF_NODE_MODULES_PATH', base_path('node_modules')),

    // ★OSのChromium/Chromeを使う場合だけ指定（puppeteer同梱Chromiumを使うなら空でOK）
    'chrome_bin' => env('PDF_CHROME_BIN', ''),
];
