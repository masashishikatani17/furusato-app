@push('scripts')
<script>
/**
 * Furusato: Enter キーで「次の入力可能セル」へ移動（Shift+Enterで前へ）
 * - Enter で submit しない（例外なし）
 * - Ctrl/Meta+Enter は何もしない
 * - 対象：input/textarea/select（数値以外のテキストも含む）
 * - スキップ：readonly/disabled/hidden/name無し/data-server-lock/data-display-only/非表示
 * - 範囲：現在表示中のタブ（.tab-pane.active.show）内のみ（無い場合はページ全体）
 * - DOM順で移動、最後は止まる
 */
(function () {
  if (window.__furusatoEnterNavInstalled) return;
  window.__furusatoEnterNavInstalled = true;

  const isEditableElement = (el) => {
    if (!el) return false;
    const tag = (el.tagName || '').toLowerCase();
    if (!['input', 'textarea', 'select'].includes(tag)) return false;

    // input の type 除外
    if (tag === 'input') {
      const type = (el.getAttribute('type') || 'text').toLowerCase();
      if (type === 'hidden') return false;
      if (type === 'button' || type === 'submit' || type === 'reset') return false;
      // checkbox/radio も「入力対象」とみなして良いが、現状furusatoは数値テキスト中心のため除外しておく
      // 必要ならここを解除してください
      if (type === 'checkbox' || type === 'radio') return false;
    }

    // name か data-name のどちらかが必要（detail画面は data-name 方式が多い）
    const name = (el.getAttribute('name') || '').trim();
    const dataName = (el.dataset && typeof el.dataset.name === 'string') ? el.dataset.name.trim() : '';
    if (!name && !dataName) return false;

    // readonly/disabled は対象外
    if (el.disabled) return false;
    if (el.readOnly) return false;

    // サーバSoT固定／表示専用は対象外
    if (el.dataset && (el.dataset.serverLock === '1' || el.dataset.displayOnly === '1')) return false;

    // "－" 表示の入力（実質表示専用）も対象外
    const v = (el.value ?? '').toString().trim();
    if (v === '－') return false;

    // 非表示要素は対象外（別タブ、display:none等）
    // getClientRects が空＝レイアウトされていない
    if (typeof el.getClientRects === 'function' && el.getClientRects().length === 0) return false;
    // aria-hidden/hidden 属性など
    const hiddenAttr = el.closest('[hidden], [aria-hidden="true"]');
    if (hiddenAttr) return false;

    return true;
  };

  const findActivePaneScope = (el) => {
    // フォーカス要素が tab-pane 配下なら「active show」のpaneだけに制限
    const pane = el ? el.closest('.tab-pane') : null;
    if (pane && pane.classList.contains('active') && pane.classList.contains('show')) {
      return pane;
    }
    // 画面全体で active pane が 1つだけならそれを採用（保険）
    const active = document.querySelector('.tab-pane.active.show');
    if (active) return active;
    // tab-pane が無い画面は document 全体
    return document;
  };

  const collectCandidatesInScope = (scope) => {
    const root = scope === document ? document : scope;
    const nodes = Array.from(root.querySelectorAll('input, textarea, select'));
    return nodes.filter(isEditableElement);
  };

  const scrollAndFocus = (el) => {
    if (!el) return;
    try {
      // スクロールコンテナ内でも見える位置へ寄せる
      el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
    } catch (e) {
      // ignore
    }
    try {
      el.focus({ preventScroll: true });
    } catch (e) {
      el.focus();
    }
    // 文字入力を想定し、可能なら末尾へ（数値欄でも邪魔になりにくい）
    if (typeof el.setSelectionRange === 'function') {
      const len = (el.value ?? '').toString().length;
      try { el.setSelectionRange(len, len); } catch (e) {}
    }
  };

  const moveByEnter = (current, dir /* +1 next / -1 prev */) => {
    const scope = findActivePaneScope(current);
    const list = collectCandidatesInScope(scope);
    if (list.length === 0) return;
    const idx = list.indexOf(current);
    if (idx < 0) return;

    const nextIdx = idx + dir;
    // 最後/最初は止まる（ループしない）
    if (nextIdx < 0 || nextIdx >= list.length) return;

    scrollAndFocus(list[nextIdx]);
  };

  document.addEventListener('keydown', (e) => {
    // IME 変換中は無視
    if (e.isComposing) return;
    if (e.key !== 'Enter') return;
    // Ctrl/Meta+Enter は何もしない
    if (e.ctrlKey || e.metaKey) return;

    const target = e.target;
    if (!(target instanceof Element)) return;

    // 入力要素だけが対象。ボタンにフォーカス時のEnterは通常挙動でOK（=ここで止めない）
    if (!isEditableElement(target)) return;

    // Enter は submit させない（例外なし）
    e.preventDefault();
    e.stopPropagation();

    // Shift+Enter は前へ、それ以外は次へ
    const dir = e.shiftKey ? -1 : 1;
    moveByEnter(target, dir);
  }, true); // capture で先に止める
})();
</script>
@endpush