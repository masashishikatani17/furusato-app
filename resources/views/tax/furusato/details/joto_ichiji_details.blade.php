<!-- resources/views/tax/furusato/details/joto_ichiji_details.blade.php -->
@extends('layouts.min')

@section('title', '総合譲渡・一時（内訳）')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container mt-2" style="width: 1200px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-2 mt-2">総合譲渡・一時の内訳</h0>
  </div>
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif
  <div class="card-body">
    <div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.joto_ichiji.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
        <input type="hidden" name="redirect_to" value="">

        @php
            $tables = [
                'prev' => $warekiPrevLabel,
                'curr' => $warekiCurrLabel,
            ];
        @endphp

        @foreach ($tables as $period => $label)
          <div class="mb-2">
            <hb class="fw-bold">{{ $label }}</hb>
            <div class="table-responsive">
              <table class="table-base table-bordered align-middle text-center">
                <thead>
                  <tr>
                    <th colspan="2" style="width: 140px;height:30px;"></th>
                    <th>収入金額</th>
                    <th>必要経費</th>
                    <th>差引金額</th>
                    <th>内部通算後</th>
                    <th>特別控除額</th>
                    <th>譲渡・一時所得の通算後</th>
                    <th>損益通算後</th>
                    <th>1/2</th>
                    <th>所得金額</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <th rowspan="2" class="th-cream">総合譲渡</th>
                    <th class="th-cream" nowrap="nowrap">短期</th>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="syunyu_joto_tanki_{{ $period }}" value="{{ old('syunyu_joto_tanki_' . $period, $inputs['syunyu_joto_tanki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="keihi_joto_tanki_{{ $period }}" value="{{ old('keihi_joto_tanki_' . $period, $inputs['keihi_joto_tanki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="sashihiki_joto_tanki_{{ $period }}" value="{{ old('sashihiki_joto_tanki_' . $period, $inputs['sashihiki_joto_tanki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="after_naibutsusan_joto_tanki_{{ $period }}" value="{{ old('after_naibutsusan_joto_tanki_' . $period, $inputs['after_naibutsusan_joto_tanki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji8 text-end bg-light" name="tokubetsukojo_joto_tanki_{{ $period }}" value="{{ old('tokubetsukojo_joto_tanki_' . $period, $inputs['tokubetsukojo_joto_tanki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="after_joto_ichiji_tousan_joto_tanki_{{ $period }}" value="{{ old('after_joto_ichiji_tousan_joto_tanki_' . $period, $inputs['after_joto_ichiji_tousan_joto_tanki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="tsusango_joto_tanki_{{ $period }}" value="{{ old('tsusango_joto_tanki_' . $period, $inputs['tsusango_joto_tanki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td class="text-center align-middle">－</td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="shotoku_joto_tanki_{{ $period }}" value="{{ old('shotoku_joto_tanki_' . $period, $inputs['shotoku_joto_tanki_' . $period] ?? null) }}" readonly>
                    </td>
                  </tr>
                  <tr>
                    <th class="th-cream">長期</th>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="syunyu_joto_choki_{{ $period }}" value="{{ old('syunyu_joto_choki_' . $period, $inputs['syunyu_joto_choki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="keihi_joto_choki_{{ $period }}" value="{{ old('keihi_joto_choki_' . $period, $inputs['keihi_joto_choki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="sashihiki_joto_choki_{{ $period }}" value="{{ old('sashihiki_joto_choki_' . $period, $inputs['sashihiki_joto_choki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="after_naibutsusan_joto_choki_{{ $period }}" value="{{ old('after_naibutsusan_joto_choki_' . $period, $inputs['after_naibutsusan_joto_choki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji8 text-end bg-light" name="tokubetsukojo_joto_choki_{{ $period }}" value="{{ old('tokubetsukojo_joto_choki_' . $period, $inputs['tokubetsukojo_joto_choki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="after_joto_ichiji_tousan_joto_choki_{{ $period }}" value="{{ old('after_joto_ichiji_tousan_joto_choki_' . $period, $inputs['after_joto_ichiji_tousan_joto_choki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="tsusango_joto_choki_{{ $period }}" value="{{ old('tsusango_joto_choki_' . $period, $inputs['tsusango_joto_choki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji8" name="half_joto_choki_{{ $period }}" value="{{ old('half_joto_choki_' . $period, $inputs['half_joto_choki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="shotoku_joto_choki_{{ $period }}" value="{{ old('shotoku_joto_choki_' . $period, $inputs['shotoku_joto_choki_' . $period] ?? null) }}" readonly>
                    </td>
                  </tr>
                  <tr>
                    <th colspan="2" class="th-cream">一時</th>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="syunyu_ichiji_{{ $period }}" value="{{ old('syunyu_ichiji_' . $period, $inputs['syunyu_ichiji_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="keihi_ichiji_{{ $period }}" value="{{ old('keihi_ichiji_' . $period, $inputs['keihi_ichiji_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="sashihiki_ichiji_{{ $period }}" value="{{ old('sashihiki_ichiji_' . $period, $inputs['sashihiki_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="after_naibutsusan_ichiji_{{ $period }}" value="{{ old('after_naibutsusan_ichiji_' . $period, $inputs['after_naibutsusan_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji8 text-end bg-light" name="tokubetsukojo_ichiji_{{ $period }}" value="{{ old('tokubetsukojo_ichiji_' . $period, $inputs['tokubetsukojo_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="after_joto_ichiji_tousan_ichiji_{{ $period }}" value="{{ old('after_joto_ichiji_tousan_ichiji_' . $period, $inputs['after_joto_ichiji_tousan_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="tsusango_ichiji_{{ $period }}" value="{{ old('tsusango_ichiji_' . $period, $inputs['tsusango_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji8" name="half_ichiji_{{ $period }}" value="{{ old('half_ichiji_' . $period, $inputs['half_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="number" step="1" class="form-control suji11" name="shotoku_ichiji_{{ $period }}" value="{{ old('shotoku_ichiji_' . $period, $inputs['shotoku_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        @endforeach
        <hr>
        <div class="text-end me-2 mb-2">
          <button type="submit" class="btn btn-base-blue me-2">戻 る</button>
          <button type="submit"
                  class="btn btn-base-green"
                  name="recalc_all"
                  value="1"
                  data-disable-on-submit
                  data-redirect-to="joto_ichiji">再計算</button>
        </div>
      </form>
    </div>
  </div> 
</div>

@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const Q = (name) => document.querySelector(`[name="${name}"]`);
    const sanitize = (value) => {
      const parsed = parseInt(value, 10);
      return Number.isNaN(parsed) ? 0 : parsed;
    };
    const setValue = (name, value) => {
      const el = Q(name);
      if (el) {
        el.value = String(value ?? 0);
      }
    };
    const getValue = (name) => {
      const el = Q(name);
      if (!el) {
        return 0;
      }
      return sanitize(el.value);
    };
    const recalc = (period) => {
      const rows = [
        { key: 'joto_tanki', hasHalf: false },
        { key: 'joto_choki', hasHalf: true },
        { key: 'ichiji', hasHalf: true },
      ];
      rows.forEach((row) => {
        const base = `${row.key}_${period}`;
        const syunyu = getValue(`syunyu_${base}`);
        const keihi = getValue(`keihi_${base}`);
        setValue(`sashihiki_${base}`, syunyu - keihi);

        const tsusango = getValue(`tsusango_${base}`);
        if (row.hasHalf) {
          const half = Math.floor(tsusango / 2);
          setValue(`half_${base}`, half);
          setValue(`shotoku_${base}`, tsusango - half);
        } else {
          setValue(`shotoku_${base}`, tsusango);
        }
      });
    };

    document.querySelectorAll('input[type="number"]').forEach((el) => {
      if (el.readOnly) {
        return;
      }
      el.addEventListener('blur', () => {
        el.value = String(sanitize(el.value));
        const match = el.name.match(/_(prev|curr)$/);
        if (match) {
          recalc(match[1]);
        }
      });
    });

    recalc('prev');
    recalc('curr');
  });
</script>
@endpush