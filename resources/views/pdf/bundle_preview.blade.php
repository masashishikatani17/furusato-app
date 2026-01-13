{{-- resources/views/pdf/bundle_preview.blade.php --}}
@extends('layouts.min')

@section('title', '帳票プレビュー')

@push('styles')
  <style>
    .bundle-wrap { max-width: 1400px; margin: 0 auto; padding: 0 12px; }
    .bundle-item { background:#fff; border:1px solid #ddd; border-radius:8px; overflow:hidden; margin:0 0 14px 0; position: relative; }
    .bundle-iframe {
      width: 100%;
      border: 0;
      display: block;
      height: 920px;
      background: #fff;
      visibility: hidden; /* 読み込み完了まで隠す */
    }

    /* 各ページごとのローディング */
    .page-loading {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255,255,255,0.9);
      z-index: 2;
    }
    .page-loading-inner {
      text-align: center;
      font-size: 13px;
      color: #333;
    }
    /* Bootstrapのspinnerに依存しない簡易スピナー */
    .mini-spinner {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      border: 3px solid rgba(0,0,0,0.12);
      border-top-color: rgba(0,0,0,0.55);
      animation: spin 0.9s linear infinite;
      margin: 0 auto;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
@endpush

@section('content')
  <div class="bundle-wrap">
    @foreach (($keys ?? []) as $idx => $key)
      <div class="bundle-item">
        <div class="page-loading" data-loading-for="{{ (int)$idx }}">
          <div class="page-loading-inner">
            <div class="mini-spinner"></div>
            <div class="mt-2">読み込み中…</div>
          </div>
        </div>
        <iframe class="bundle-iframe" data-idx="{{ (int)$idx }}" loading="lazy"></iframe>
      </div>
    @endforeach
  </div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const iframes = Array.from(document.querySelectorAll('iframe.bundle-iframe'));
    const pagesUrl = @json($pages_url ?? '');

    // srcdoc注入直後に「枠だけ先に見せる」（load待ちで隠しっぱなしを避ける）
    const showFrameFor = (idx) => {
      const iframe = document.querySelector(`iframe.bundle-iframe[data-idx="${idx}"]`);
      if (iframe) iframe.style.visibility = 'visible';
    };

    const hideLoadingFor = (idx) => {
      const overlay = document.querySelector(`.page-loading[data-loading-for="${idx}"]`);
      if (overlay) overlay.style.display = 'none';
      const iframe = document.querySelector(`iframe.bundle-iframe[data-idx="${idx}"]`);
      if (iframe) iframe.style.visibility = 'visible';
    };

    const resize = (iframe) => {
      try {
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        if (!doc) return;
        const h1 = doc.documentElement ? doc.documentElement.scrollHeight : 0;
        const h2 = doc.body ? doc.body.scrollHeight : 0;
        const h = Math.max(h1, h2, 920);
        iframe.style.height = (h + 10) + 'px';
      } catch (e) {}
    };

    // srcdoc注入後のloadで「そのページだけ」ぐるぐるを消す
    iframes.forEach((iframe) => {
      const idx = Number(iframe.getAttribute('data-idx') || '0');
      iframe.addEventListener('load', () => {
        // 直後はレイアウトが揺れやすいので少し遅らせて1回だけ測る
        setTimeout(() => resize(iframe), 60);
        hideLoadingFor(idx);
      });
      iframe.addEventListener('error', () => {
        hideLoadingFor(idx);
      });
    });

    // JSONを取りに行って srcdoc を流し込む
    if (!pagesUrl) {
      // 取得先が無いなら、永遠ぐるぐるを避けるため全部解除
      iframes.forEach((iframe) => {
        const idx = Number(iframe.getAttribute('data-idx') || '0');
        hideLoadingFor(idx);
      });
      return;
    }
    
    fetch(pagesUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        const pages = Array.isArray(data?.pages) ? data.pages : [];

        // ★体感の本体は「8枚同時DOM構築」なので、可視領域に近いものだけ注入する
        const injected = new Set();
        const injectIdx = (idx) => {
          if (injected.has(idx)) return;
          const iframe = document.querySelector(`iframe.bundle-iframe[data-idx="${idx}"]`);
          if (!iframe) return;
          const html = (typeof pages[idx] === 'string') ? pages[idx] : '';
          injected.add(idx);
          iframe.srcdoc = html;

          // loadを待たず、枠だけ先に見せる（最初の2ページが“出ない”問題の対策）
          showFrameFor(idx);

          // 注入したページだけに保険タイムアウト（永遠ぐるぐる防止）
          setTimeout(() => hideLoadingFor(idx), 30000);
        };

        // まず先頭2枚だけ注入（初期表示を軽く・早く）
        injectIdx(0);
        injectIdx(1);

        // IntersectionObserver が使えない環境では、段階注入フォールバック
        if (typeof IntersectionObserver === 'undefined') {
          let i = 2;
          const step = () => {
            if (i >= iframes.length) return;
            injectIdx(i);
            i++;
            setTimeout(step, 120);
          };
          step();
          return;
        }

        // 1画面分手前で注入（スクロールして「待ち」が出ないように）
        const io = new IntersectionObserver((entries) => {
          entries.forEach((ent) => {
            if (!ent.isIntersecting) return;
            const iframe = ent.target;
            const idx = Number(iframe.getAttribute('data-idx') || '0');
            injectIdx(idx);
            io.unobserve(iframe);
          });
        }, {
          root: null,
          rootMargin: '1200px 0px',
          threshold: 0.01,
        });

        iframes.forEach((iframe, i) => {
          if (i <= 1) return; // 先頭2枚は注入済み
          io.observe(iframe);
        });
      })
      .catch(() => {
        // 失敗したら全部解除（真っ白よりマシ）
        iframes.forEach((iframe) => {
          const idx = Number(iframe.getAttribute('data-idx') || '0');
          hideLoadingFor(idx);
        });
      });
  });
</script>
@endpush