/* global bootstrap, Sortable */
(function () {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const btn = $('#furusato-preview-button');
  if (!btn) return;

  const modeModalEl = $('#furusato-preview-mode-modal');
  const listModalEl = $('#furusato-report-preview-modal');
  const viewerModalEl = $('#furusato-report-viewer-modal');
  const gridEl = $('#furusato-preview-grid');
  const loadingEl = $('#furusato-preview-loading');
  const errorEl = $('#furusato-preview-error');

  const clearBtn = $('#furusato-preview-clear');
  const openSelectedBtn = $('#furusato-preview-open-selected');
  const selectedCountEl = $('#furusato-preview-selected-count');

  const viewerGrid = $('#furusato-viewer-grid');

  const modeModal = (modeModalEl && bootstrap?.Modal) ? bootstrap.Modal.getOrCreateInstance(modeModalEl) : null;
  const listModal = (listModalEl && bootstrap?.Modal) ? bootstrap.Modal.getOrCreateInstance(listModalEl) : null;
  const viewerModal = (viewerModalEl && bootstrap?.Modal) ? bootstrap.Modal.getOrCreateInstance(viewerModalEl) : null;

  const buildUrlWithVariant = (baseUrl, variant) => {
    const u = new URL(baseUrl, window.location.origin);
    u.searchParams.set('pdf_variant', String(variant || 'max'));
    u.searchParams.set('format', 'json');
    // キャッシュ回避
    u.searchParams.set('_ts', String(Date.now()));
    return u.toString();
  };

  // ---- state ----
  let pages = [];       // { html, key, idx }
  let selected = new Set(); // idx
  let lastOpened = null;    // idx
  let sortable = null;
  let aborter = null;

  const showLoading = (msg) => {
    if (!loadingEl) return;
    loadingEl.classList.remove('d-none');
    if (msg) loadingEl.querySelector('div:last-child')?.replaceChildren(document.createTextNode(msg));
  };
  const hideLoading = () => loadingEl?.classList.add('d-none');
  const showError = (msg) => {
    if (!errorEl) return;
    errorEl.textContent = msg || 'エラーが発生しました。';
    errorEl.classList.remove('d-none');
  };
  const hideError = () => {
    if (!errorEl) return;
    errorEl.textContent = '';
    errorEl.classList.add('d-none');
  };

  const resetList = () => {
    pages = [];
    selected = new Set();
    lastOpened = null;
    if (gridEl) gridEl.innerHTML = '';
    hideError();
    hideLoading();
  };

  const sanitizeKeyLabel = (idx) => {
    // 0:表紙, 1:1ページ目... の表示
    if (idx === 0) return '表紙';
    return `${idx}ページ目`;
  };

  const updateSelectedCount = () => {
    if (!selectedCountEl) return;
    selectedCountEl.textContent = `選択中：${selected.size}件`;
  };
  const makeThumbCard = (p) => {
    const col = document.createElement('div');
    col.className = 'col-12 col-sm-6 col-md-6';
    col.dataset.idx = String(p.idx);

    const card = document.createElement('div');
    card.className = 'border rounded bg-white shadow-sm position-relative';
    card.style.userSelect = 'none';
    card.style.cursor = 'pointer';
    card.style.padding = '8px';

    // 選択バッジ
    const badge = document.createElement('div');
    badge.className = 'position-absolute top-0 end-0 m-2 px-2 py-1 small rounded bg-primary text-white';
    badge.textContent = '選択';
    badge.style.display = 'none';
    badge.dataset.role = 'selected-badge';
    card.appendChild(badge);

    // チェックボックス（クリックで複数選択しやすく）
    const chkWrap = document.createElement('div');
    chkWrap.className = 'position-absolute top-0 end-0 mt-2 me-2';
    chkWrap.style.zIndex = '5';
    const chk = document.createElement('input');
    chk.type = 'checkbox';
    chk.className = 'form-check-input';
    chk.dataset.role = 'thumb-check';
    chk.title = '選択';
    chkWrap.appendChild(chk);
    card.appendChild(chkWrap);

    // タイトル
    const title = document.createElement('div');
    title.className = 'small text-muted mb-1';
    title.textContent = sanitizeKeyLabel(p.idx);
    card.appendChild(title);

    // iframe（縮小）
    const wrap = document.createElement('div');
    wrap.style.width = '100%';
    wrap.style.border = '1px solid #ddd';
    wrap.style.overflow = 'hidden';
    wrap.style.borderRadius = '6px';
    wrap.style.background = '#fff';
    // A4横（297×210）を前提に、幅に合わせて高さも自動で決める
    // 297/210 ≒ 1.414
    wrap.style.aspectRatio = '297 / 210';
    wrap.style.position = 'relative';

    const iframe = document.createElement('iframe');
    // 内部は「A4横相当の固定サイズ」で描画し、wrap幅にフィットする倍率へ自動スケールする
    // 1400×990 ≒ 1.414（A4横比率に揃える）
    iframe.dataset.baseW = '1400';
    iframe.dataset.baseH = '990';
    iframe.style.width = '1400px';
    iframe.style.height = '990px';
    iframe.style.border = '0';
    iframe.style.position = 'absolute';
    iframe.style.top = '0';
    iframe.style.left = '0';
    iframe.style.transformOrigin = '0 0';
    iframe.style.pointerEvents = 'none';
    // srcdoc は JS でセット（長文HTMLを属性に入れない）
    iframe.dataset.role = 'thumb-frame';

    wrap.appendChild(iframe);

    // 透明オーバーレイ（★ここを掴む＝PDF面を掴んで並び替え）
    // - title/checkbox領域は邪魔しないように wrap の中にだけ置く
    const overlay = document.createElement('div');
    overlay.className = 'thumb-overlay';
    overlay.style.position = 'absolute';
    overlay.style.inset = '0';
    overlay.style.cursor = 'grab';
    overlay.style.background = 'transparent';
    overlay.style.zIndex = '3';
    overlay.dataset.role = 'thumb-overlay';
    wrap.style.position = 'relative';
    wrap.appendChild(overlay);

    card.appendChild(wrap);

    col.appendChild(card);

    return col;
  };

  const fitThumb = (col) => {
    const wrap = col.querySelector('div[style*="aspect-ratio"]') || col.querySelector('div');
    const iframe = col.querySelector('iframe[data-role="thumb-frame"]');
    if (!wrap || !iframe) return;
    const baseW = Number(iframe.dataset.baseW || 1400);
    const baseH = Number(iframe.dataset.baseH || 990);
    const w = wrap.clientWidth || 0;
    if (!w) return;
    // 幅にフィットさせる（縦は比率で自然に決まる）
    const scale = w / baseW;
    iframe.style.transform = `scale(${scale})`;
    // wrapの高さは aspect-ratio で決まるが、ブラウザ差の保険で明示も入れる
    // （固定高さを持たせない＝黄色の空欄が出ない）
    const h = Math.round(baseH * scale);
    wrap.style.height = `${h}px`;
  };

  const applySelectionStyle = (col) => {
    const idx = Number(col.dataset.idx || 0);
    const card = col.firstElementChild;
    const badge = col.querySelector('[data-role="selected-badge"]');
    const chk = col.querySelector('[data-role="thumb-check"]');
    if (!card || !badge) return;
    if (selected.has(idx)) {
      card.classList.add('border-primary');
      badge.style.display = '';
      if (chk) chk.checked = true;
    } else {
      card.classList.remove('border-primary');
      badge.style.display = 'none';
      if (chk) chk.checked = false;
    }
  };

  const toggleSelect = (idx, additive) => {
    if (!additive) selected = new Set(); // 単独選択
    if (selected.has(idx)) selected.delete(idx);
    else selected.add(idx);
    // UI反映
    $$('#furusato-preview-grid > div').forEach(applySelectionStyle);
    updateSelectedCount();
  };

  // ============================
  // 並べて拡大表示（選択枚数ぶん）
  // - 各枠：＋/−でズーム、ドラッグでパン（個別）
  // ============================
  const buildViewerColsClass = (count) => {
    if (count <= 1) return 'col-12';
    if (count === 2) return 'col-12 col-md-6';
    if (count === 3) return 'col-12 col-md-4';
    return 'col-12 col-md-3'; // 4以上
  };

  const applyTransform = (canvas, state) => {
    canvas.style.transform = `translate(${state.tx}px, ${state.ty}px) scale(${state.scale})`;
  };

  // パンの移動範囲を「端までで止める」ためのクランプ
  // - 画面外に無限に飛ばないよう、(0 .. viewportW - scaledW) に収める（Yも同様）
  // - scaledW/H が viewport より小さい場合は中央寄せにする
  const clampPan = (viewport, canvas, state) => {
    const vw = viewport.clientWidth || 0;
    const vh = viewport.clientHeight || 0;
    if (vw <= 0 || vh <= 0) return;

    // canvas の “元サイズ” を取得（transform前のサイズ）
    // scrollWidth/scrollHeight が取れない場合は offsetWidth/offsetHeight を使う
    const bw = canvas.scrollWidth || canvas.offsetWidth || 0;
    const bh = canvas.scrollHeight || canvas.offsetHeight || 0;
    if (bw <= 0 || bh <= 0) return;

    const sw = bw * state.scale;
    const sh = bh * state.scale;

    // X方向
    if (sw <= vw) {
      // コンテンツが小さい → 中央
      state.tx = Math.round((vw - sw) / 2);
    } else {
      const minTx = vw - sw; // 左へ行ける最大（負）
      const maxTx = 0;       // 右へは 0 まで
      state.tx = Math.min(maxTx, Math.max(minTx, state.tx));
    }

    // Y方向
    if (sh <= vh) {
      state.ty = Math.round((vh - sh) / 2);
    } else {
      const minTy = vh - sh;
      const maxTy = 0;
      state.ty = Math.min(maxTy, Math.max(minTy, state.ty));
    }
  };

  const attachPan = (viewport, canvas, state) => {
    let dragging = false;
    let sx = 0, sy = 0;
    let stx = 0, sty = 0;

    const onDown = (e) => {
      // 左ボタンのみ（タッチはOK）
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      dragging = true;
      viewport.setPointerCapture?.(e.pointerId);
      viewport.style.cursor = 'grabbing';
      viewport.style.userSelect = 'none';
      sx = e.clientX;
      sy = e.clientY;
      stx = state.tx;
      sty = state.ty;
    };
    const onMove = (e) => {
      if (!dragging) return;
      const dx = e.clientX - sx;
      const dy = e.clientY - sy;
      state.tx = stx + dx;
      state.ty = sty + dy;
      clampPan(viewport, canvas, state);
      applyTransform(canvas, state);
    };
    const onUp = () => {
      dragging = false;
      viewport.style.cursor = 'grab';
      viewport.style.userSelect = '';
    };

    viewport.addEventListener('pointerdown', onDown);
    viewport.addEventListener('pointermove', onMove);
    viewport.addEventListener('pointerup', onUp);
    viewport.addEventListener('pointercancel', onUp);
    viewport.addEventListener('pointerleave', onUp);
  };

  const openViewerTiles = (orderedIdxList) => {
    const list = orderedIdxList.filter((x) => Number.isFinite(x));
    if (list.length === 0) return;
    if (!viewerGrid) return;

    viewerGrid.innerHTML = '';
    const colClass = buildViewerColsClass(list.length);

    for (const idx of list) {
      const page = pages.find((pp) => pp.idx === idx);
      if (!page) continue;

      const col = document.createElement('div');
      col.className = colClass;

      const tile = document.createElement('div');
      tile.className = 'border rounded bg-white shadow-sm';
      tile.dataset.idx = String(idx);

      const header = document.createElement('div');
      header.className = 'd-flex justify-content-between align-items-center px-2 py-1 border-bottom bg-light';

      const label = document.createElement('div');
      label.className = 'small text-muted';
      label.textContent = sanitizeKeyLabel(idx);

      const btnWrap = document.createElement('div');
      btnWrap.className = 'btn-group btn-group-sm';

      const btnMinus = document.createElement('button');
      btnMinus.type = 'button';
      btnMinus.className = 'btn btn-outline-secondary';
      btnMinus.textContent = '−';

      const zoomLabel = document.createElement('span');
      zoomLabel.className = 'btn btn-outline-secondary disabled';
      zoomLabel.textContent = '100%';

      const btnPlus = document.createElement('button');
      btnPlus.type = 'button';
      btnPlus.className = 'btn btn-outline-secondary';
      btnPlus.textContent = '＋';

      const btnReset = document.createElement('button');
      btnReset.type = 'button';
      btnReset.className = 'btn btn-outline-secondary';
      btnReset.textContent = '⟲';

      btnWrap.appendChild(btnMinus);
      btnWrap.appendChild(zoomLabel);
      btnWrap.appendChild(btnPlus);
      btnWrap.appendChild(btnReset);

      header.appendChild(label);
      header.appendChild(btnWrap);

      const viewport = document.createElement('div');
      viewport.className = 'viewer-viewport';
      viewport.style.height = 'calc(100vh - 140px)';
      viewport.style.overflow = 'hidden';
      viewport.style.position = 'relative';
      viewport.style.background = '#fff';
      viewport.style.cursor = 'grab';

      const canvas = document.createElement('div');
      canvas.className = 'viewer-canvas';
      canvas.style.position = 'absolute';
      canvas.style.left = '0';
      canvas.style.top = '0';
      canvas.style.transformOrigin = '0 0';

      // HTML をそのまま埋め込む（iframeではなくdiv）
      canvas.innerHTML = page.html || '';

      viewport.appendChild(canvas);

      const state = { scale: 1.0, tx: 0, ty: 0 };
      // 初期位置（中央寄せ）
      clampPan(viewport, canvas, state);
      applyTransform(canvas, state);
      attachPan(viewport, canvas, state);

      const clampScale = (s) => Math.max(0.5, Math.min(3.0, s));
      const updateZoomLabel = () => {
        zoomLabel.textContent = `${Math.round(state.scale * 100)}%`;
      }
      updateZoomLabel();+

      btnPlus.addEventListener('click', () => {
        state.scale = clampScale(state.scale * 1.12);
        clampPan(viewport, canvas, state);
        applyTransform(canvas, state);
        updateZoomLabel();
      });
      btnMinus.addEventListener('click', () => {
        state.scale = clampScale(state.scale / 1.12);
        clampPan(viewport, canvas, state);
        applyTransform(canvas, state);
        updateZoomLabel();
      });
      btnReset.addEventListener('click', () => {
        state.scale = 1.0;
        state.tx = 0;
        state.ty = 0;
        clampPan(viewport, canvas, state);
        applyTransform(canvas, state);
        updateZoomLabel();
      });

      // ホイールズーム（Ctrl/⌘ + ホイールで、その枠だけズーム）
      // - 画面スクロールを邪魔しないため、修飾キー必須
      viewport.addEventListener('wheel', (e) => {
        if (!(e.ctrlKey || e.metaKey)) {
          return;
        }
        e.preventDefault();
        // マウス位置を中心にズーム（見たい箇所がズレにくい）
        const rect = viewport.getBoundingClientRect();
        const px = e.clientX - rect.left;
        const py = e.clientY - rect.top;

        const oldScale = state.scale;
        const dir = (e.deltaY < 0) ? 1 : -1;
        const factor = dir > 0 ? 1.10 : (1 / 1.10);
        const newScale = clampScale(oldScale * factor);
        if (newScale === oldScale) return;

        // ズーム前後で「同じ画面上の点(px,py)が同じ内容を指す」ように平行移動を補正
        // contentX = (px - tx) / scale  を保つように tx を更新
        const contentX = (px - state.tx) / oldScale;
        const contentY = (py - state.ty) / oldScale;
        state.scale = newScale;
        state.tx = px - contentX * newScale;
        state.ty = py - contentY * newScale;
        clampPan(viewport, canvas, state);
        applyTransform(canvas, state);
        updateZoomLabel();
      }, { passive: false });

      // ★ウィンドウサイズ変更で端クランプが崩れるのを防ぐ
      // （fullscreen内の列幅変化で viewport が変わるため）
      window.addEventListener('resize', () => {
        clampPan(viewport, canvas, state);
        applyTransform(canvas, state);
      });

      tile.appendChild(header);
      tile.appendChild(viewport);
      col.appendChild(tile);
      viewerGrid.appendChild(col);
    }

    viewerModal?.show();
  };

  const enableSortable = () => {
    if (!gridEl || typeof Sortable === 'undefined') return;
    if (sortable) {
      sortable.destroy();
      sortable = null;
    }
    sortable = new Sortable(gridEl, {
      animation: 150,
      delay: 180,              // 長押し相当（タッチ系に効く）
      delayOnTouchOnly: true,
      handle: '.thumb-overlay',
      ghostClass: 'sortable-ghost',
      onEnd: () => {
        // 保存しない（その場のDOM順でOK）
      },
    });
  };

  const loadPages = async (variant) => {
    const base = btn.getAttribute('data-preview-json-url') || '';
    if (!base) throw new Error('preview URL が初期化されていません。');
    const url = buildUrlWithVariant(base, variant);

    aborter?.abort();
    aborter = new AbortController();

    showLoading('再計算＆プレビュー生成中です…');
    hideError();

    const res = await fetch(url, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      signal: aborter.signal,
    });
    if (!res.ok) {
      const t = await res.text().catch(() => '');
      throw new Error(t || 'プレビューの取得に失敗しました。');
    }
    const json = await res.json();
    const arr = Array.isArray(json.pages) ? json.pages : [];
    return arr.map((html, i) => ({ html: String(html || ''), idx: i, key: (Array.isArray(json.keys) ? json.keys[i] : '') }));
  };

  const renderGrid = (arr) => {
    if (!gridEl) return;
    gridEl.innerHTML = '';
    pages = arr;
    selected = new Set();
    lastOpened = null;
    updateSelectedCount();

    for (const p of arr) {
      const col = makeThumbCard(p);
      gridEl.appendChild(col);

      // iframe srcdocセット
      const iframe = col.querySelector('iframe[data-role="thumb-frame"]');
      if (iframe) iframe.srcdoc = p.html || '';

      // サムネ枠を「ページ全体」にフィット（空欄を消す）
      // iframe の load を待たずに枠幅でスケールできるので即実行
      fitThumb(col);

      // click/dblclick は overlay で拾う（iframeがイベントを奪わないように）
      const overlay = col.querySelector('[data-role="thumb-overlay"]');
      if (overlay) {
        overlay.addEventListener('click', (e) => {
          const idx = Number(col.dataset.idx || 0);
          const additive = e.ctrlKey || e.metaKey || e.shiftKey;
          toggleSelect(idx, additive);
        });
        overlay.addEventListener('dblclick', () => {
          const idx = Number(col.dataset.idx || 0);
          // ダブルクリックは “その1枚だけ” を選択→拡大（並べ表示は openSelected で）
          selected = new Set([idx]);
          $$('#furusato-preview-grid > div').forEach(applySelectionStyle);
          updateSelectedCount();
          openSelectedBtn?.click();
        });
      }

      // チェックボックス（複数選択しやすい）
      const chk = col.querySelector('[data-role="thumb-check"]');
      if (chk) {
        chk.addEventListener('click', (e) => {
          e.stopPropagation();
          const idx = Number(col.dataset.idx || 0);
          // checkboxは additive として扱う（単独にしない）
          if (chk.checked) selected.add(idx);
          else selected.delete(idx);
          $$('#furusato-preview-grid > div').forEach(applySelectionStyle);
          updateSelectedCount();
        });
      }
    }

    enableSortable();
  };

  // 画面リサイズ時もサムネを再フィット（横幅が変わるため）
  window.addEventListener('resize', () => {
    $$('#furusato-preview-grid > div').forEach((col) => fitThumb(col));
  });

  const startPreview = async (variant) => {
    resetList();
    listModal?.show();
    showLoading('再計算＆プレビュー生成中です…');
    try {
      const arr = await loadPages(variant);
      hideLoading();
      renderGrid(arr);
    } catch (e) {
      hideLoading();
      showError(e?.message || 'プレビューの取得に失敗しました。');
    }
  };

  // ---- UI events ----
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (modeModal) modeModal.show();
    else startPreview('max');
  }, { passive: false });

  // 3択モーダル
  if (modeModalEl) {
    $$('[data-preview-variant]', modeModalEl).forEach((el) => {
      el.addEventListener('click', async () => {
        const v = el.getAttribute('data-preview-variant') || 'max';
        try { modeModal?.hide(); } catch (_e) {}
        await startPreview(v);
      });
    });
  }

  clearBtn?.addEventListener('click', () => {
    selected = new Set();
    $$('#furusato-preview-grid > div').forEach(applySelectionStyle);
    updateSelectedCount();
  });

  openSelectedBtn?.addEventListener('click', () => {
    // 選択済みを「サムネの現在の並び順（ドラッグ後）」で列挙する
    const ordered = [];
    $$('#furusato-preview-grid > div').forEach((col) => {
      const idx = Number(col.dataset.idx || 0);
      if (selected.has(idx)) ordered.push(idx);
    });
    if (ordered.length === 0) {
      // 未選択なら先頭を1枚表示
      if (pages[0]) ordered.push(pages[0].idx);
    }
    openViewerTiles(ordered);
  });

  // list modal を閉じたら fetch を止める
  listModalEl?.addEventListener('hidden.bs.modal', () => {
    aborter?.abort();
    aborter = null;
  });
})();