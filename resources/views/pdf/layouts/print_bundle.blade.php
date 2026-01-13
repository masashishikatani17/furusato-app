<!-- resources/views/pdf/layouts/print_bundle.blade.php -->
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
    @font-face{ font-family:'ipaexg'; src:url('{{ $fontG }}') format('truetype'); font-weight:700; font-style:normal; }
    @font-face{ font-family:'ipaexm'; src:url('{{ $fontM }}') format('truetype'); font-weight:700; font-style:normal; }

    body   { font-family: ipaexg, "DejaVu Sans", sans-serif; }
    .mincho{ font-family: ipaexm, serif; }

    /* bundleでもページごとに max-width が衝突しない共通ラッパー */
    .cover {
      width: 100%;
      text-align: center; /* ★DomPDFで崩れやすいflexを避ける */
    }
    .cover-frame {
      display: inline-block;
      width: 100%;
      max-width: var(--cover-max-width, 100%);
    }
  </style>
  @yield('head')
</head>
<body>
  {{-- bundle は各ページ側が <main class="page"> を持つので、ここでは包まない --}}
  @yield('content')
</body>
</html>