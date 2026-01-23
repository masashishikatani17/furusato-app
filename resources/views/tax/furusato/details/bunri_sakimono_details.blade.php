<!-- views/tax/furusato/details/bunri_sakimono_details.blade.php -->
@extends('layouts.min')

@section('title', '先物取引 内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $bunriPrevOff = (int)($syoriSettings['bunri_flag_prev'] ?? $syoriSettings['bunri_flag'] ?? 0) === 0;
    $bunriCurrOff = (int)($syoriSettings['bunri_flag_curr'] ?? $syoriSettings['bunri_flag'] ?? 0) === 0;
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originSubtabRaw = request()->input('origin_subtab', 'bunri');
    $originSubtabCandidate = is_string($originSubtabRaw) ? preg_replace('/[^A-Za-z0-9_-]/', '', trim($originSubtabRaw)) : '';
    $originSubtab = in_array($originSubtabCandidate, ['bunri', 'sogo', 'prev', 'curr'], true) ? $originSubtabCandidate : 'bunri';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container-blue mt-2" style="width: 800px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 mt-2 ms-2">内訳－先物取引</h0>
    </div>
  </div>
  <div class="card-body">
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.bunri_sakimono.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_subtab" value="{{ $originSubtab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
        <input type="hidden" name="redirect_to" value="input">
        <input type="hidden" name="recalc_all" value="1">
        <input type="hidden" name="stay_on_details" id="stay-on-details-flag" value="0">

        @if ($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @foreach (['prev' => $warekiPrevLabel, 'curr' => $warekiCurrLabel] as $period => $label)
          <div class="fw-bold ms-2 mb-1">{{ $label }}</div>
          @php $off = ($period === 'prev') ? $bunriPrevOff : $bunriCurrOff; @endphp
          <div class="table-responsive mb-2">
            <table class="table-base table-bordered align-middle text-center">
              <thead>
                <tr>
                  <th style="height:30px;">収入金額</th>
                  <th>必要経費</th>
                  <th>所得金額</th>
                  <th>繰越損失の金額</th>
                  <th>繰越控除後の所得金額</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  @php($name = 'syunyu_sakimono_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="number" min="0" step="1"
                             class="form-control suji11 text-end"
                             data-name="{{ $name }}"
                             value="{{ $inputs[$name] ?? null }}">
                      <input type="hidden" name="{{ $name }}" value="{{ $inputs[$name] ?? null }}">
                    @endif
                  </td>
                  @php($name = 'keihi_sakimono_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="number" min="0" step="1"
                             class="form-control suji11 text-end"
                             data-name="{{ $name }}"
                             value="{{ $inputs[$name] ?? null }}">
                      <input type="hidden" name="{{ $name }}" value="{{ $inputs[$name] ?? null }}">
                    @endif
                  </td>
                  @php($name = 'shotoku_sakimono_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="number" step="1"
                             class="form-control suji11 text-end bg-light"
                             data-name="{{ $name }}"
                             value="{{ $inputs[$name] ?? null }}" readonly>
                      <input type="hidden" name="{{ $name }}" value="{{ $inputs[$name] ?? null }}">
                    @endif
                  </td>
                  @php($name = 'kurikoshi_sakimono_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="number" min="0" step="1"
                             class="form-control suji11 text-end"
                             data-name="{{ $name }}"
                             value="{{ $inputs[$name] ?? null }}">
                      <input type="hidden" name="{{ $name }}" value="{{ $inputs[$name] ?? null }}">
                    @endif
                  </td>
                  @php($name = 'shotoku_sakimono_after_kurikoshi_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="number" step="1"
                             class="form-control suji11 text-end bg-light"
                             data-name="{{ $name }}"
                             value="{{ $inputs[$name] ?? null }}" readonly>
                      <input type="hidden" name="{{ $name }}" value="{{ $inputs[$name] ?? null }}">
                    @endif
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        @endforeach
        <hr>
        <div class="text-end me-2 mb-2">
          <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
          <button type="submit"
                  class="btn-base-green ms-2"
                  id="btn-recalc"
                  data-disable-on-submit>再計算</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== stay_on_details フラグの確実送信（プログラム submit でも失われないように） =====
  (function ensureStayFlag() {
    const form = document.querySelector('form');
    if (!form) return;
    const stayFlag = form.querySelector('#stay-on-details-flag');
    const btnBack = form.querySelector('#btn-back');
    const btnRecalc = form.querySelector('#btn-recalc');

    if (btnBack) {
      btnBack.addEventListener('click', () => { if (stayFlag) stayFlag.value = '0'; });
    }
    if (btnRecalc) {
      btnRecalc.addEventListener('click', () => { if (stayFlag) stayFlag.value = '1'; });
    }
    // 念のため submit 直前にも最終補正（submitterが失われても安全）
    form.addEventListener('submit', () => {
      if (!stayFlag || (stayFlag.value !== '0' && stayFlag.value !== '1')) {
        stayFlag.value = '0';
      }
    });
  })();
  // --- 見える input(data-name) と hidden(name) の同期ユーティリティ ---
  const byData = (name) => document.querySelector(`[data-name="${name}"]`);
  const byName = (name) => document.querySelector(`[name="${name}"]`);
  const toInt = (v) => {
    const s = String(v ?? '').replace(/[^\-0-9]/g, '').trim();
    if (s === '' || s === '-') return 0;
    const n = Number(s);
    return Number.isFinite(n) ? Math.trunc(n) : 0;
  };
  const get = (name) => {
    const vis = byData(name);
    if (!vis) return 0;
    return toInt(vis.value);
  };
  const setBoth = (name, value) => {
    const vis = byData(name);
    const hid = byName(name);
    const v = toInt(value);
    if (vis) vis.value = String(v);
    if (hid) hid.value = String(v);
  };
  const syncHiddenFromVisible = (name) => {
    const vis = byData(name);
    const hid = byName(name);
    if (!vis || !hid) return;
    hid.value = String(toInt(vis.value));
  };

  const recalc = (period) => {
    const base = `sakimono_${period}`;
    const syunyu = get(`syunyu_${base}`);
    const keihi  = get(`keihi_${base}`);
    const shotoku = syunyu - keihi;
    setBoth(`shotoku_${base}`, shotoku);

    const kurikoshi = get(`kurikoshi_${base}`);
    const after = Math.max(0, shotoku - kurikoshi);
    setBoth(`shotoku_sakimono_after_kurikoshi_${period}`, after);
  };

  const bindBlur = () => {
    document.querySelectorAll('input[type="number"][data-name]').forEach((el) => {
      if (el.readOnly) return;
      const dn = el.getAttribute('data-name') || '';
      const m = dn.match(/_(prev|curr)$/);
      if (!m) return;
      const period = m[1];
      // 入力のたびに hidden へ同期し、blur で再計算（input 時にも軽く同期）
      el.addEventListener('input', () => syncHiddenFromVisible(dn));
      el.addEventListener('blur',  () => { syncHiddenFromVisible(dn); recalc(period); });
    });
  };

  bindBlur();
  // 初期表示：hidden と visible の整合を取り、両年分を再計算
  ['prev','curr'].forEach((p) => {
    ['syunyu','keihi','kurikoshi','shotoku','shotoku_sakimono_after_kurikoshi'].forEach((k) => {
      const key = k === 'shotoku_sakimono_after_kurikoshi'
        ? `${k}_${p}` : `${k}_sakimono_${p}`;
      // 初期は visible を正とみなし hidden を同期（old()撤廃に伴う確定表示）
      syncHiddenFromVisible(key);
    });
    recalc(p);
  });

  // 送信直前ガード：全 data-name を hidden へ同期
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', () => {
      document.querySelectorAll('input[data-name]').forEach((el) => {
        const dn = el.getAttribute('data-name');
        if (dn) syncHiddenFromVisible(dn);
      });
    });
  }
});
</script>
@endpush 

{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')

@endsection