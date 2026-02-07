<!-- resources/views/layouts/min.blade.php -->
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'ふるさと納税クラウド')</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  {{-- App common styles (public/css/style.css) with cache-busting --}}
  @php
    $cssPath = public_path('css/style.css');
    $v = is_file($cssPath) ? filemtime($cssPath) : time();
    $furusatoCssPath = public_path('css/furusato.css');
    $vFurusato = is_file($furusatoCssPath) ? filemtime($furusatoCssPath) : time();
  @endphp
  <link href="{{ asset('css/style.css') }}?v={{ $v }}" rel="stylesheet">  
  <link href="{{ asset('css/furusato.css') }}?v={{ $vFurusato }}" rel="stylesheet">
  @stack('styles')
</head>
<body class="bg-light">
  <main class="py-4">@yield('content')</main>

  <!-- Alpine.js（deferでDOM後に初期化） -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <!-- Bootstrap JS（モーダル等） -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- 和暦入力（元号＋年＋月＋日）→ hidden(YYYY-MM-DD) -->
  <script src="{{ asset('js/common/wareki_date.js') }}" defer></script>
  <script src="{{ asset('js/common/disable_on_submit.js') }}" defer></script>
  @stack('scripts')
</body>
</html>