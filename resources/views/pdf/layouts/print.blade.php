<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','PDF')</title>
  <!-- ★ DomPDFはローカルファイルを読むのが確実。CSSも public_path を使う -->
  <link rel="stylesheet" href="{{ public_path('css/pdf/print.css') }}">
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
    /* A4横・余白ゼロ（帳票側で再指定可だが、ここをゼロにしておくと左右のズレが消える） */
    @page { size: A4 landscape; margin: 0; }

    /* 用紙コンテナを中央に固定（ブラウザ印刷プレビューでも左右中央） */
    .page {
      width: 297mm;
      height: 210mm;
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