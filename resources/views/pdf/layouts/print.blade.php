<!-- resources/views/pdf/layouts/print.blade.php -->
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','PDF')</title>
  @php
    // HTMLプレビューでは asset()、PDF(DomPDF)では public_path() を使う
    $isPdf = !empty($is_pdf);
    // ★プレビューとPDFを一致させる：CSS/フォントはURL(asset)で統一
    $cssStyle = asset('css/style.css');
    $cssPrint = asset('css/pdf/print.css');
    $fontG = asset('fonts/ipaexg.ttf');
    $fontM = asset('fonts/ipaexm.ttf');
  @endphp
  <link rel="stylesheet" href="{{ $cssStyle }}">
  <link rel="stylesheet" href="{{ $cssPrint }}">
  <style data-pdf-base="1">
    /* ★ フォントはレイアウトに集約（DomPDFが確実に埋め込めるように） */
    @font-face {
      font-family: 'ipaexg';
      src: url('{{ $fontG }}') format('truetype');
      font-weight: 400; font-style: normal;
    }
    @font-face {
      font-family: 'ipaexm';
      src: url('{{ $fontM }}') format('truetype');
      font-weight: 400; font-style: normal;
    }
    /* ★DomPDF/ブラウザ両対応：太字は weight 700 に寄せる（bold 文字列は解釈差が出やすい） */
    @font-face{ font-family:'ipaexg'; src:url('{{ $fontG }}') format('truetype'); font-weight:700; font-style:normal; }
    @font-face{ font-family:'ipaexm'; src:url('{{ $fontM }}') format('truetype'); font-weight:700; font-style:normal; }

    /* 既定：本文はゴシック、必要箇所は .mincho を付与 */
    body   { font-family: ipaexg, "DejaVu Sans", sans-serif; }
    .mincho{ font-family: ipaexm, serif; }
    /* 用紙コンテナ（サイズは print.css / 各帳票の @page で決める） */
    .page {
      margin: 0 auto;       /* ★ 中央寄せの要 */
      position: relative;   /* ★ 絶対配置の基準 */
      background: #fff;
    }

    /* =========================================================
     * Bundleでも崩れない共通ラッパー
     * - max-width はページ側で CSS 変数 (--cover-max-width) を指定
     * ========================================================= */
    .cover {
      width: 100%;
      /* ★DomPDFで flex が効かずズレるのを防止（ブラウザでも同様に中央寄せ可能） */
      text-align: center;
    }
    .cover-frame {
      display: inline-block; /* ★中央寄せの核 */
      width: 100%;
      max-width: var(--cover-max-width, 100%);
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