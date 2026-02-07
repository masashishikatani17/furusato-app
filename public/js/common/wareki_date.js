/* global window, document */
(function () {
  'use strict';

  // 元号境界（開始日）
  const ERAS = [
    { key: 'taisho', label: '大正', start: '1912-07-30' },
    { key: 'showa',  label: '昭和', start: '1926-12-25' },
    { key: 'heisei', label: '平成', start: '1989-01-08' },
    { key: 'reiwa',  label: '令和', start: '2019-05-01' },
  ];

  const pad2 = (n) => String(n).padStart(2, '0');
  const toIso = (y, m, d) => `${String(y).padStart(4, '0')}-${pad2(m)}-${pad2(d)}`;
  const parseIso = (iso) => {
    if (typeof iso !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
    const [y, m, d] = iso.split('-').map((v) => Number(v));
    if (!y || !m || !d) return null;
    return { y, m, d };
  };
  const isoToDate = (iso) => {
    const p = parseIso(iso);
    if (!p) return null;
    const dt = new Date(p.y, p.m - 1, p.d);
    // JS Date の丸め対策（2/30など）
    if (dt.getFullYear() !== p.y || (dt.getMonth() + 1) !== p.m || dt.getDate() !== p.d) return null;
    return dt;
  };
  const daysInMonth = (y, m) => new Date(y, m, 0).getDate(); // mは1-12

  const getEraIndexByIso = (iso) => {
    const dt = isoToDate(iso);
    if (!dt) return -1;
    for (let i = ERAS.length - 1; i >= 0; i--) {
      const startDt = isoToDate(ERAS[i].start);
      if (startDt && dt >= startDt) return i;
    }
    return -1;
  };

  const eraMaxYear = (eraIdx) => {
    // 次の元号開始日の前日までを上限にする（令和は一旦 99年まで）
    if (eraIdx < 0 || eraIdx >= ERAS.length) return 99;
    if (eraIdx === ERAS.length - 1) return 99; // 令和
    const era = ERAS[eraIdx];
    const next = ERAS[eraIdx + 1];
    const start = parseIso(era.start);
    const nextStart = parseIso(next.start);
    if (!start || !nextStart) return 99;
    // 上限年数（概算）：次の開始年 - 開始年 + 1
    // ただし末年が途中で切れるのは日付チェックで弾く
    return Math.max(1, (nextStart.y - start.y) + 1);
  };

  const toWarekiParts = (iso) => {
    const idx = getEraIndexByIso(iso);
    if (idx < 0) return null;
    const era = ERAS[idx];
    const start = parseIso(era.start);
    const p = parseIso(iso);
    if (!start || !p) return null;
    const eraYear = (p.y - start.y) + 1;
    if (eraYear <= 0) return null;
    return { eraKey: era.key, eraYear, month: p.m, day: p.d, eraIdx: idx };
  };

  const fromWarekiParts = (eraKey, eraYear, month, day) => {
    const idx = ERAS.findIndex((e) => e.key === eraKey);
    if (idx < 0) return { ok: false, iso: '', err: '元号が不正です。' };
    const era = ERAS[idx];
    const start = parseIso(era.start);
    if (!start) return { ok: false, iso: '', err: '元号開始日の定義が不正です。' };

    const y = start.y + (eraYear - 1);
    const iso = toIso(y, month, day);
    const dt = isoToDate(iso);
    if (!dt) return { ok: false, iso: '', err: '日付が不正です。' };

    // 元号開始日より前はNG
    const startDt = isoToDate(era.start);
    if (startDt && dt < startDt) return { ok: false, iso: '', err: '元号の開始日より前の日付です。' };

    // 次の元号開始日以降はNG（平成31年5月1日など）
    if (idx < ERAS.length - 1) {
      const nextStartDt = isoToDate(ERAS[idx + 1].start);
      if (nextStartDt && dt >= nextStartDt) return { ok: false, iso: '', err: '選択した元号では存在しない日付です。' };
    }

    return { ok: true, iso, err: '' };
  };

  const find = (wrap, role) => wrap.querySelector(`[data-role="${role}"]`);
  const showError = (wrap, msg) => {
    const el = find(wrap, 'error');
    if (!el) return;
    if (msg) {
      el.textContent = msg;
      el.style.display = '';
    } else {
      el.textContent = '';
      el.style.display = 'none';
    }
  };

  const rebuildYearOptions = (wrap, eraKey) => {
    const yearSel = find(wrap, 'year');
    if (!yearSel) return;
    const idx = ERAS.findIndex((e) => e.key === eraKey);
    const max = idx >= 0 ? eraMaxYear(idx) : 99;
    const current = Number(yearSel.value || 0) || 0;
    yearSel.innerHTML = '<option value="">選択してください</option>';
    for (let y = 1; y <= max; y++) {
      const opt = document.createElement('option');
      opt.value = String(y);
      opt.textContent = String(y);
      yearSel.appendChild(opt);
    }
    if (current >= 1 && current <= max) yearSel.value = String(current);
  };

  const rebuildDayOptions = (wrap, y, m) => {
    const daySel = find(wrap, 'day');
    if (!daySel) return;
    const maxD = daysInMonth(y, m);
    const current = Number(daySel.value || 0) || 0;
    daySel.innerHTML = '<option value="">選択してください</option>';
    for (let d = 1; d <= maxD; d++) {
      const opt = document.createElement('option');
      opt.value = String(d);
      opt.textContent = String(d);
      daySel.appendChild(opt);
    }
    if (current >= 1 && current <= maxD) daySel.value = String(current);
  };

  const updateHidden = (wrap) => {
    const readonly = (wrap.getAttribute('data-readonly') === '1');
    if (readonly) return;

    const gengo = (find(wrap, 'gengo')?.value || '').trim();
    const yy = Number(find(wrap, 'year')?.value || 0) || 0;
    const mm = Number(find(wrap, 'month')?.value || 0) || 0;
    const dd = Number(find(wrap, 'day')?.value || 0) || 0;

    const hidden = find(wrap, 'hidden');
    if (!hidden) return;

    // 未入力は空にする（required判定は submit 時に行う）
    if (!gengo || yy <= 0 || mm <= 0 || dd <= 0) {
      hidden.value = '';
      showError(wrap, '');
      return;
    }

    const res = fromWarekiParts(gengo, yy, mm, dd);
    if (!res.ok) {
      hidden.value = '';
      showError(wrap, res.err || '日付が不正です。');
      return;
    }

    hidden.value = res.iso;
    showError(wrap, '');
  };

  const setIsoToWrap = (wrap, iso) => {
    const readonly = (wrap.getAttribute('data-readonly') === '1');
    const hidden = find(wrap, 'hidden');
    if (hidden) hidden.value = (iso || '');
    if (readonly) return;

    const parts = iso ? toWarekiParts(iso) : null;
    const gengoSel = find(wrap, 'gengo');
    const yearSel = find(wrap, 'year');
    const monthSel = find(wrap, 'month');
    const daySel = find(wrap, 'day');

    if (!parts) {
      if (gengoSel) gengoSel.value = '';
      if (yearSel) yearSel.value = '';
      if (monthSel) monthSel.value = '';
      if (daySel) daySel.value = '';
      showError(wrap, '');
      return;
    }

    if (gengoSel) gengoSel.value = parts.eraKey;
    rebuildYearOptions(wrap, parts.eraKey);
    if (yearSel) yearSel.value = String(parts.eraYear);
    if (monthSel) monthSel.value = String(parts.month);

    // day は年月に依存
    const era = ERAS.find((e) => e.key === parts.eraKey);
    const start = era ? parseIso(era.start) : null;
    const y = start ? (start.y + (parts.eraYear - 1)) : (new Date()).getFullYear();
    rebuildDayOptions(wrap, y, parts.month);
    if (daySel) daySel.value = String(parts.day);

    updateHidden(wrap);
  };

  const initWrap = (wrap) => {
    if (!wrap || wrap._warekiInitDone) return;
    wrap._warekiInitDone = true;

    const readonly = (wrap.getAttribute('data-readonly') === '1');
    if (readonly) return;

    const gengoSel = find(wrap, 'gengo');
    const yearSel = find(wrap, 'year');
    const monthSel = find(wrap, 'month');
    const daySel = find(wrap, 'day');

    const onChange = () => {
      const gengo = (gengoSel?.value || '').trim();
      if (gengo) rebuildYearOptions(wrap, gengo);

      // 年月が揃ったら day を整備
      const yy = Number(yearSel?.value || 0) || 0;
      const mm = Number(monthSel?.value || 0) || 0;
      if (gengo && yy > 0 && mm > 0) {
        const era = ERAS.find((e) => e.key === gengo);
        const start = era ? parseIso(era.start) : null;
        const gy = start ? (start.y + (yy - 1)) : (new Date()).getFullYear();
        rebuildDayOptions(wrap, gy, mm);
      }
      updateHidden(wrap);
    };

    gengoSel?.addEventListener('change', onChange);
    yearSel?.addEventListener('change', onChange);
    monthSel?.addEventListener('change', onChange);
    daySel?.addEventListener('change', onChange);

    // 初期値（hidden or data-initial-iso）
    const hidden = find(wrap, 'hidden');
    const initial = (hidden?.value || wrap.getAttribute('data-initial-iso') || '').trim();
    if (initial) {
      setIsoToWrap(wrap, initial);
    } else {
      // 初期：未選択
      showError(wrap, '');
    }
  };

  const initAll = () => {
    document.querySelectorAll('[data-wareki-date]').forEach(initWrap);
  };

  const setIsoByName = (name, iso) => {
    const n = (name || '').toString();
    if (!n) return;
    document.querySelectorAll(`[data-wareki-date][data-name="${CSS.escape(n)}"]`).forEach((wrap) => {
      initWrap(wrap);
      setIsoToWrap(wrap, iso);
    });
  };

  const validateRequiredInForm = (form) => {
    let ok = true;
    const wraps = form.querySelectorAll('[data-wareki-date][data-required="1"][data-readonly="0"]');
    wraps.forEach((wrap) => {
      initWrap(wrap);
      const hidden = find(wrap, 'hidden');
      if (!hidden || !hidden.value) {
        ok = false;
        showError(wrap, '必須項目です。');
        // 最初の未入力へフォーカス
        const gengoSel = find(wrap, 'gengo');
        if (gengoSel && typeof gengoSel.focus === 'function') gengoSel.focus();
      }
    });
    return ok;
  };

  const attachFormGuards = () => {
    const forms = [
      document.getElementById('data-create-form'),
      document.getElementById('data-copy-form'),
      document.getElementById('data-edit-form'),
    ].filter(Boolean);
    forms.forEach((form) => {
      if (form._warekiGuardDone) return;
      form._warekiGuardDone = true;
      form.addEventListener('submit', (e) => {
        // 全wrapのhiddenを最新化
        form.querySelectorAll('[data-wareki-date]').forEach((w) => {
          initWrap(w);
          updateHidden(w);
        });
        if (!validateRequiredInForm(form)) {
          e.preventDefault();
          e.stopPropagation();
        }
      });
    });
  };

  // 外部（ページ固有JS）から使うAPI
  window.WarekiDatePicker = {
    initAll,
    setIsoByName,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initAll();
      attachFormGuards();
    });
  } else {
    initAll();
    attachFormGuards();
  }
})();