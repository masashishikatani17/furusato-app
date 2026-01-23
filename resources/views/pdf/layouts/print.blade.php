<!-- resources/views/pdf/layouts/print.blade.php -->
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','PDF')</title>
  @php
  // ★PDF生成は Browsershot(Chromium) 経由：file:// は禁止のため使わない（HTMLに含めない）
    $isPdf = !empty($is_pdf);
    $cssStylePath = public_path('css/style.css');
    $cssPrintPath = public_path('css/pdf/print.css');
    $cssStyleInline = is_file($cssStylePath) ? file_get_contents($cssStylePath) : '';
    $cssPrintInline = is_file($cssPrintPath) ? file_get_contents($cssPrintPath) : '';
    // フォントは Browsershot で HTTP(S) 経由読み込みに寄せる（asset）
    $fontG = asset('fonts/ipaexg.ttf');
    $fontM = asset('fonts/ipaexm.ttf');
 
  @endphp
  @if(!$isPdf)
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pdf/print.css') }}">
  @endif
  @if($isPdf)
    <style data-inline-style="1">{!! $cssStyleInline !!}</style>
    <style data-inline-print="1">{!! $cssPrintInline !!}</style>
  @endif
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

    /* =========================================================
     * ページ番号配置（安定版：absolute/fixed を使わず、Flexで最下段へ）
     * 前提：@page margin = 10mm 6mm 10mm 6mm（A4横）
     * - 印刷領域の高さ = 210 - 10 - 10 = 190mm
     * - 右39mm（紙端）→ 印刷領域基準: 39-6 = 33mm
     * - 下15mm（紙端）→ 印刷領域基準: 15-10 = 5mm
     * ========================================================= */
/* page-frame を absolute の基準にする */
    .page-frame{ position: relative !important; }

    /* ページ番号：上/左基準で固定（印刷領域基準） */
    .page-frame .page-footer{
      position: absolute !important;
      left: 252mm !important;  /* = (297-39) - 左余白6 */
      top: 178mm !important;   /* = (210-15) - 上余白10 */
      width: auto !important;
      margin: 0 !important;
      padding: 0 !important;
      z-index: 9999 !important;
    }
    
     .page-footer table{
       width: auto !important;
       margin: 0 !important;
       border-collapse: collapse;
       table-layout: auto;
     }
     .page-footer td{
       padding: 0 !important;
       border: 0 !important;
       white-space: nowrap;
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