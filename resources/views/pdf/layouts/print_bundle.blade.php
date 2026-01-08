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
  <style data-pdf-base="1">
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

    body   { font-family: ipaexg, "DejaVu Sans", sans-serif; }
    .mincho{ font-family: ipaexm, serif; }

    /* bundleでもページごとに max-width が衝突しない共通ラッパー */
    .cover {
      width: 100%;
      display: flex;
      justify-content: center;
    }
    .cover-frame {
      width: 100%;
      text-align: center;
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