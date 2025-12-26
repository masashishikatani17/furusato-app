<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','PDF')</title>
  @php
    // HTMLプレビューでは asset()、PDF(DomPDF)では public_path() を使う
    $isPdf = !empty($is_pdf);
    $cssStyle = $isPdf ? public_path('css/style.css') : asset('css/style.css');
    $cssPrint = $isPdf ? public_path('css/pdf/print.css') : asset('css/pdf/print.css');
  @endphp
  <link rel="stylesheet" href="{{ $cssStyle }}">
  <link rel="stylesheet" href="{{ $cssPrint }}">
  <style>
    /* ★ フォントはレイアウトに集約（DomPDFが確実に埋め込めるように） */
    @font-face {
      font-family: 'ipaexg';
      src: url('{{ public_path('fonts/ipaexg.ttf') }}') format('truetype');
      font-weight: 400; font-style: normal;
    }
    @font-face {
      font-family: 'ipaexm';
      src: url('{{ public_path('fonts/ipaexm.ttf') }}') format('truetype');
      font-weight: 400; font-style: normal;
    }
    @font-face{ font-family:'ipaexg'; src:url('{{ public_path('fonts/ipaexg.ttf') }}') format('truetype'); font-weight:bold;  font-style:normal; }
    @font-face{ font-family:'ipaexg'; src:url('{{ public_path('fonts/ipaexg.ttf') }}') format('truetype'); font-weight:400;   font-style:italic; }
    @font-face{ font-family:'ipaexm'; src:url('{{ public_path('fonts/ipaexm.ttf') }}') format('truetype'); font-weight:bold;  font-style:normal; }
    @font-face{ font-family:'ipaexm'; src:url('{{ public_path('fonts/ipaexm.ttf') }}') format('truetype'); font-weight:400;   font-style:italic; }

    /* 既定：本文はゴシック、必要箇所は .mincho を付与 */
    body   { font-family: ipaexg, "DejaVu Sans", sans-serif; }
    .mincho{ font-family: ipaexm, serif; }
    /* 用紙コンテナ（サイズは print.css / 各帳票の @page で決める） */
    .page {
      margin: 0 auto;       /* ★ 中央寄せの要 */
      position: relative;   /* ★ 絶対配置の基準 */
      background: #fff;
    }
    
  </style>
  <!-- 帳票ごとの追加スタイル用フック -->
  @yield('head')
</head>
<body>
  <main class="page">
    @yield('content')
  </main>
</body>
</html>