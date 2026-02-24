@php
  // 使い方:
  //   @includeWhen(config('app.debug'), 'components.furusato.totals_debug', ['payload' => $payload])
  //   payload を渡さない場合は session('furusato_results.payload') を自動参照
  $P = isset($payload) && is_array($payload) ? $payload : (session('furusato_results.payload') ?? []);
  $pPeriods = ['prev' => ($warekiPrev ?? '前年'), 'curr' => ($warekiCurr ?? '当年')];

  $N = function ($v): int {
    if ($v === null || $v === '') return 0;
    if (is_string($v)) $v = str_replace([',',' '], '', $v);
    return is_numeric($v) ? (int) floor((float)$v) : 0;
  };
  // A: 総合課税（最終／0下限）
  $A = function (string $p) use ($P,$N): int {
    return
      max(0, $N($P["shotoku_keijo_{$p}"]           ?? null)) +
      max(0, $N($P["shotoku_joto_tanki_sogo_{$p}"] ?? null)) +
      max(0, $N($P["shotoku_joto_choki_sogo_{$p}"] ?? null)) +
      max(0, $N($P["shotoku_ichiji_{$p}"]          ?? null));
  };
  // B: 退職・山林（0下限）
  $B = function (string $p) use ($P,$N): int {
    return
      max(0, $N($P["shotoku_taishoku_{$p}"] ?? null)) +
      max(0, $N($P["shotoku_sanrin_{$p}"]   ?? null));
  };
  // C_pre: 分離（繰越前=tsusango_*＋先物shotoku）
  $Cpre = function (string $p) use ($P,$N): int {
    return
      max(0, $N($P["tsusango_tanki_ippan_{$p}"]   ?? null)) +
      max(0, $N($P["tsusango_tanki_keigen_{$p}"]  ?? null)) +
      max(0, $N($P["tsusango_choki_ippan_{$p}"]   ?? null)) +
      max(0, $N($P["tsusango_choki_tokutei_{$p}"] ?? null)) +
      max(0, $N($P["tsusango_choki_keika_{$p}"]   ?? null)) +
      max(0, $N($P["tsusango_ippan_joto_{$p}"]    ?? null)) +
      max(0, $N($P["tsusango_jojo_joto_{$p}"]     ?? null)) +
      max(0, $N($P["tsusango_jojo_haito_{$p}"]    ?? null)) +
      max(0, $N($P["shotoku_sakimono_{$p}"]       ?? null));
  };
  // C_after: 分離（繰越後=after_kurikoshi_* 系）
  $Cafter = function (string $p) use ($P,$N): int {
    return
      max(0, $N($P["tsusango_tanki_ippan_{$p}"]   ?? null)) +
      max(0, $N($P["tsusango_tanki_keigen_{$p}"]  ?? null)) +
      max(0, $N($P["tsusango_choki_ippan_{$p}"]   ?? null)) +
      max(0, $N($P["tsusango_choki_tokutei_{$p}"] ?? null)) +
      max(0, $N($P["tsusango_choki_keika_{$p}"]   ?? null)) +    
      max(0, $N($P["shotoku_after_kurikoshi_ippan_joto_{$p}"] ?? null)) +
      max(0, $N($P["shotoku_after_kurikoshi_jojo_joto_{$p}"]  ?? null)) +
      max(0, $N($P["shotoku_after_kurikoshi_jojo_haito_{$p}"] ?? null)) +
      max(0, $N($P["shotoku_sakimono_after_kurikoshi_{$p}"]   ?? null));
  };
  // 新キー（あれば差分比較用に表示）
  $K = function (string $key, string $p) use ($P,$N): ?int {
    $kk = "{$key}_{$p}";
    return array_key_exists($kk, $P) ? $N($P[$kk]) : null;
  };
@endphp

<div class="card mt-3 border-secondary" style="max-width: 1080px;">
  <div class="card-header d-flex align-items-center justify-content-between py-2">
    <strong>🧮 合計所得金額 / 総所得金額 / 総所得金額等（検算）Debug</strong>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="
      var b = this.closest('.card').querySelector('.js-debug-body');
      if (b) { b.style.display = (b.style.display === 'none' ? '' : 'none'); }
    ">表示/非表示</button>
  </div>
  <div class="card-body js-debug-body" style="display: none;">
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle text-end">
        <thead class="table-light text-center">
          <tr>
            <th style="width:140px;">区分</th>
            @foreach($pPeriods as $p => $label)
              <th>{{ $label }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          <tr>
            <th class="text-start">A（総合課税）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($A($p)) }}</td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">B（退職・山林）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($B($p)) }}</td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">C_pre（分離：繰越前）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($Cpre($p)) }}</td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">C_after（分離：繰越後）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($Cafter($p)) }}</td>
            @endforeach
          </tr>
          <tr class="table-secondary fw-bold">
            <th class="text-start">A + B + C_pre（= 合計所得金額）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($A($p) + $B($p) + $Cpre($p)) }}</td>
            @endforeach
          </tr>
          <tr class="table-secondary fw-bold">
            <th class="text-start">A（= 総所得金額）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($A($p)) }}</td>
            @endforeach
          </tr>
          <tr class="table-secondary fw-bold">
            <th class="text-start">A + B + C_after（= 総所得金額等）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($A($p) + $B($p) + $Cafter($p)) }}</td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">（参考）既存 shotoku_gokei</th>
            @foreach($pPeriods as $p => $label)
              @php($legacy = array_key_exists("shotoku_gokei_{$p}", $P) ? $N($P["shotoku_gokei_{$p}"]) : null)
              @php($newGokei = $A($p) + $B($p) + $Cpre($p))
              <td class="{{ $legacy !== null && $legacy !== $newGokei ? 'text-danger' : 'text-muted' }}">
                {{ $legacy !== null ? number_format($legacy) : '－' }}
              </td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">Δ(合計所得金額) = 新 − 既存</th>
            @foreach($pPeriods as $p => $label)
              @php($legacy = array_key_exists("shotoku_gokei_{$p}", $P) ? $N($P["shotoku_gokei_{$p}"]) : null)
              @php($diff = $legacy !== null ? (($A($p) + $B($p) + $Cpre($p)) - $legacy) : null)
              <td class="{{ $diff !== null && $diff !== 0 ? 'text-danger fw-bold' : 'text-success' }}">
                {{ $diff !== null ? number_format($diff) : '－' }}
              </td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">（合計所得金額）sum_for_gokeishotoku</th>
            @foreach($pPeriods as $p => $label)
              @php($val = $K('sum_for_gokeishotoku', $p))
              <td class="{{ $val !== null && $val !== ($A($p)+$B($p)+$Cpre($p)) ? 'text-danger' : 'text-muted' }}">
                {{ $val !== null ? number_format($val) : '－' }}
              </td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">（総所得金額）sum_for_sogoshotoku</th>
            @foreach($pPeriods as $p => $label)
              @php($val = $K('sum_for_sogoshotoku', $p))
              <td class="{{ $val !== null && $val !== $A($p) ? 'text-danger' : 'text-muted' }}">
                {{ $val !== null ? number_format($val) : '－' }}
              </td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">（総所得金額等）sum_for_sogoshotoku_etc</th>
            @foreach($pPeriods as $p => $label)
              @php($val = $K('sum_for_sogoshotoku_etc', $p))
              @php($calc = $A($p) + $B($p) + $Cafter($p))
              <td class="{{ $val !== null && $val !== $calc ? 'text-danger' : 'text-muted' }}">
                {{ $val !== null ? number_format($val) : '－' }}
              </td>
            @endforeach
          </tr>
        </tbody>
      </table>
    </div>
    <div class="small text-muted">
      ※表示は <code>config('app.debug')</code> が true のときのみ。<br>
      ・合計所得金額 = A + B + C_pre（分離<small>繰越前</small>）<br>
      ・総所得金額 = A（総合課税のみ）<br>
      ・総所得金額等 = A + B + C_after（分離<small>繰越後</small>）
    </div>
  </div>
</div>