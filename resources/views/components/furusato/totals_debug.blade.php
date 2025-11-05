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

  $A = function (string $p) use ($P,$N): int {
    return
      max(0, $N($P["shotoku_keijo_{$p}"]           ?? null)) +
      max(0, $N($P["shotoku_joto_tanki_{$p}"]      ?? null)) +
      max(0, $N($P["shotoku_joto_choki_sogo_{$p}"] ?? null)) +
      max(0, $N($P["shotoku_ichiji_{$p}"]          ?? null));
  };
  $B = function (string $p) use ($P,$N): int {
    return
      max(0, $N($P["shotoku_taishoku_{$p}"] ?? null)) +
      max(0, $N($P["shotoku_sanrin_{$p}"]   ?? null));
  };
  $C = function (string $p) use ($P,$N): int {
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
  $SUM = function (string $p) use ($P,$A,$B,$C): int {
    return $A($p) + $B($p) + $C($p);
  };
  $legacy = function (string $p) use ($P,$N): ?int {
    return array_key_exists("shotoku_gokei_{$p}", $P) ? $N($P["shotoku_gokei_{$p}"]) : null;
  };
@endphp

<div class="card mt-3 border-secondary" style="max-width: 1080px;">
  <div class="card-header d-flex align-items-center justify-content-between py-2">
    <strong>🧮 合計所得金額（検算）Debug</strong>
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
            <th class="text-start">C（分離：繰越前）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($C($p)) }}</td>
            @endforeach
          </tr>
          <tr class="table-secondary fw-bold">
            <th class="text-start">A + B + C（=合計所得金額）</th>
            @foreach($pPeriods as $p => $label)
              <td>{{ number_format($SUM($p)) }}</td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">（参考）既存 shotoku_gokei</th>
            @foreach($pPeriods as $p => $label)
              @php($lv = $legacy($p))
              <td class="{{ $lv !== null && $lv !== $SUM($p) ? 'text-danger' : 'text-muted' }}">
                {{ $lv !== null ? number_format($lv) : '－' }}
              </td>
            @endforeach
          </tr>
          <tr>
            <th class="text-start">Δ = 新 − 既存</th>
            @foreach($pPeriods as $p => $label)
              @php($lv = $legacy($p))
              @php($diff = $lv !== null ? ($SUM($p) - $lv) : 0)
              <td class="{{ $diff !== 0 ? 'text-danger fw-bold' : 'text-success' }}">
                {{ $lv !== null ? number_format($diff) : '－' }}
              </td>
            @endforeach
          </tr>
        </tbody>
      </table>
    </div>
    <div class="small text-muted">
      ※表示は <code>config('app.debug')</code> が true のときのみ。合計は
      <code>sum_for_gokeishotoku_*</code> と同式（A+B+C）で再計算しています。
    </div>
  </div>
</div>