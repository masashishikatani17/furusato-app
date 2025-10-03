<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 24mm 18mm; }

    /* 方式：ローカルファイルを直接読む（Cloud9でも確実） */
    @font-face {
      font-family: 'ipaexg';
      src: url('{{ public_path('fonts/ipaexg.ttf') }}') format('truetype');
      font-weight:400; font-style:normal;
    }
    @font-face {
      font-family: 'ipaexm';
      src: url('{{ public_path('fonts/ipaexm.ttf') }}') format('truetype');
      font-weight:400; font-style:normal;
    }
    /* 太字・斜体も同じTTFにマッピング（フォールバック回避） */
    @font-face{
      font-family:'ipaexg';
      src:url('{{ public_path('fonts/ipaexg.ttf') }}') format('truetype');
      font-weight:bold; font-style:normal;
    }
    @font-face{
      font-family:'ipaexg';
      src:url('{{ public_path('fonts/ipaexg.ttf') }}') format('truetype');
      font-weight:400; font-style:italic;
    }
    @font-face{
      font-family:'ipaexm';
      src:url('{{ public_path('fonts/ipaexm.ttf') }}') format('truetype');
      font-weight:bold; font-style:normal;
    }
    @font-face{
      font-family:'ipaexm';
      src:url('{{ public_path('fonts/ipaexm.ttf') }}') format('truetype');
      font-weight:400; font-style:italic;
    }

    body   { font-family: ipaexg, "DejaVu Sans", sans-serif; font-size: 12pt; line-height: 1.6; }
    h1     { font-size: 18pt; margin: 0 0 12mm; }
    .meta  { font-size: 10pt; color: #555; margin-bottom: 8mm; }
    .box   { border: 1px solid #333; padding: 6mm; border-radius: 4px; }
    .small { font-size: 9pt; color:#666; margin-top: 6mm; }
    .mincho { font-family: ipaexm, serif; }
  </style>
  <title>{{ $title }}</title>
</head>
<body>
  <h1>{{ $title }}</h1>
  <div class="meta">generated at: {{ $today }}</div>

  <div class="box">
    これは <b>IPAexゴシック</b> で出力しています。日本語が豆腐(□)にならなければ成功です。<br>
    下の段落は <span class="mincho"><b>IPAex明朝</b></span> で出力します。
  </div>

  <p class="mincho">
    （明朝）これは明朝体サンプルです。漢字・仮名・英数が正しく描画されるか確認してください。
  </p>

  <div class="small">
    ルート: <code>/pdf/sample</code>（表示）, <code>/pdf/sample/download</code>（DL）
  </div>
</body>
</html>
