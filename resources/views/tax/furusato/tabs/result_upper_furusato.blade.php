<!-- resources/views/tax/furusato/tabs/result_upper_furusato.blade.php -->
@php
    // Controller から渡された kmax コンテキスト
    /** @var array<string,mixed>|null $kmax */
    $k = $kmax ?? [];

    /**
     * ▼ 実利上限（自己負担<=2,000円）探索の結果
     * - FurusatoController が $furusato_upper を view に渡している想定
     * - 存在しない場合でも壊れないように null ガード
     */
    /** @var array<string,mixed>|null $furusato_upper */
    $upper = $furusato_upper ?? null;

    /** @var array<string,mixed>|null $furusato_upper_scenarios */
    $sc = $furusato_upper_scenarios ?? null;

    // results から参照できる SoT（存在すれば表示）
    $resultsPayload = $results['payload'] ?? $results['upper'] ?? [];
    $getR = static function (string $key, $default = null) use ($resultsPayload) {
        if (is_array($resultsPayload) && array_key_exists($key, $resultsPayload)) {
            return $resultsPayload[$key];
        }
        return $default;
    };

    $S40          = $k['S40']           ?? null;
    $S30          = $k['S30']           ?? null;
    $R            = $k['R']             ?? null;
    $alphaPercent = $k['alpha_percent'] ?? null;

    $DTotal       = $k['D_total']       ?? 0;
    $DFuru        = $k['D_furu']        ?? 0;
    $DOther       = $k['D_other']       ?? 0;

    $K40          = $k['kmax_40']       ?? null;
    $K30          = $k['kmax_30']       ?? null;
    $K20          = $k['kmax_20']       ?? null;
    $Kmax         = $k['kmax']          ?? null;
    $binding      = $k['binding']       ?? null;
    $remaining    = $k['remaining']     ?? null;

    $fmtYen = static fn($v) => $v === null ? '－' : number_format((int)$v) . ' 円';
    $fmtInt = static fn($v) => $v === null ? '－' : number_format((int)$v);
    $fmtPercent = static function ($v, int $dec = 3): string {
        if ($v === null) {
            return '－';
        }
        return number_format((float)$v, $dec) . ' %';
    };
@endphp

<div class="wrapper pt-2">
  <div class="card-header d-flex align-items-start">
    <h0 class="mb-0 mt-2">ふるさと納税の理論上限額</h0>
  </div>

  <div class="card-body">
    @if ($Kmax === null)
      <p class="text-danger">
        ふるさと納税上限額を算出するために必要な所得情報または寄附金情報が不足しているため、<br>
        現時点では理論上限額を表示できません。
      </p>
    @endif


    {{-- ============================================================
         ▼ デバッグ表示（実利上限探索）
         - 表示位置は「どこかに適当に」でよいとのことなので末尾に配置
         - 上限探索の前提値/重要値/結果を簡易に可視化
       ============================================================ --}}
    <hr>
    <h6 class="mt-2">（参考）実利上限（自己負担 2,000 円）探索の計算値</h6>
    @if (!is_array($upper))
      <p class="text-muted mb-0">探索結果が未生成のため、参考情報は表示できません。</p>
    @else
      @php
        $yMaxTotal = $upper['y_max_total'] ?? null;
        $yCurrent  = $upper['y_current']   ?? null;
        $yAdd      = $upper['y_add']       ?? null;
        $payBase   = $upper['pay_base']    ?? null;
        $payAtMax  = $upper['pay_at_max']  ?? null;
        $taxSaved  = $upper['tax_saved']   ?? null;
        $burden    = $upper['burden']      ?? null;
        $ubound    = $upper['upper_bound'] ?? null;

        // ベース（ふるさと=0）との差分での自己負担判定：burden = y - (pay_base - pay_y)
        $payCurr = null;
        $itaxPay = $getR('tax_gokei_shotoku_curr', null);
        $rtaxPay = $getR('tax_gokei_jumin_curr', null);
        if ($itaxPay !== null || $rtaxPay !== null) {
            $payCurr = (int)($itaxPay ?? 0) + (int)($rtaxPay ?? 0);
        }
        $S40SoT = $getR('sum_for_sogoshotoku_etc_curr', null);
      @endphp
      <div class="table-responsive mb-2">
        <table class="table-base table-bordered align-middle text-start">
          <tr>
            <th style="width:260px;">項目</th>
            <th style="width:200px;" class="text-end">値</th>
            <th>備考</th>
          </tr>
          <tr>
            <td>探索上界（upper_bound）</td>
            <td class="text-end">{{ $fmtYen($ubound) }}</td>
            <td class="text-start ps-1">探索の上限（原則 0.4×S40 を採用）</td>
          </tr>
          <tr>
            <td>S40（SoT: sum_for_sogoshotoku_etc_curr）</td>
            <td class="text-end">{{ $fmtYen($S40SoT) }}</td>
            <td class="text-start ps-1">探索上界の根拠となる総所得金額等（SoT）</td>
          </tr>
          <tr>
            <td>当年ふるさと寄附の現在値（y_current）</td>
            <td class="text-end">{{ $fmtYen($yCurrent) }}</td>
            <td class="text-start ps-1">SoT: shotokuzei_shotokukojo_furusato_curr</td>
          </tr>
          <tr>
            <td>当年ふるさと寄附の最大値（y_max_total）</td>
            <td class="text-end"><strong>{{ $fmtYen($yMaxTotal) }}</strong></td>
            <td class="text-start ps-1">自己負担≦2,000円かつNG条件を満たす最大値</td>
          </tr>
          <tr>
            <td>追加で寄附可能（y_add）</td>
            <td class="text-end"><strong>{{ $fmtYen($yAdd) }}</strong></td>
            <td class="text-start ps-1">y_max_total − y_current</td>
          </tr>
          <tr>
            <td>支払税額（ベース：ふるさと=0）pay_base</td>
            <td class="text-end">{{ $fmtYen($payBase) }}</td>
            <td class="text-start ps-1">tax_gokei_shotoku + tax_gokei_jumin の合計（ふるさと=0時）</td>
          </tr>
          <tr>
            <td>支払税額（y_max_total時）pay_at_max</td>
            <td class="text-end">{{ $fmtYen($payAtMax) }}</td>
            <td class="text-start ps-1">tax_gokei_shotoku + tax_gokei_jumin の合計（上限時）</td>
          </tr>
          <tr>
            <td>減税額（tax_saved）</td>
            <td class="text-end">{{ $fmtYen($taxSaved) }}</td>
            <td class="text-start ps-1">pay_base − pay_at_max</td>
          </tr>
          <tr>
            <td>自己負担（burden）</td>
            <td class="text-end"><strong>{{ $fmtYen($burden) }}</strong></td>
            <td class="text-start ps-1">y_max_total − tax_saved（2,000円以下が条件）</td>
          </tr>
          <tr>
            <td>参考：現在の支払税額（currのSoT）</td>
            <td class="text-end">{{ $fmtYen($payCurr) }}</td>
            <td class="text-start ps-1">results.payload の tax_gokei_* があれば表示</td>
          </tr>
        </table>
      </div>
    @endif


    {{-- ============================================================
         ▼ ①〜④の税額スナップショット（当年）
         - 減税額は「①−各ケース」（プラスが得）で表示
         - 別枠で「②−③/④」も表示
       ============================================================ --}}
    <hr>
    <h6 class="mt-2">（参考）①〜④の税額比較（当年）</h6>
    @if (!is_array($sc))
      <p class="text-muted mb-0">税額比較の結果が未生成のため表示できません。</p>
    @else
      @php
        $c1 = $sc['case1'] ?? [];
        $c2 = $sc['case2'] ?? [];
        $c3 = $sc['case3'] ?? [];
        $c4 = $sc['case4'] ?? [];
        $s12 = $sc['saved_1_2'] ?? ['itax'=>0,'jumin'=>0];
        $s13 = $sc['saved_1_3'] ?? ['itax'=>0,'jumin'=>0];
        $s14 = $sc['saved_1_4'] ?? ['itax'=>0,'jumin'=>0];
        $s23 = $sc['saved_2_3'] ?? ['itax'=>0,'jumin'=>0];
        $s24 = $sc['saved_2_4'] ?? ['itax'=>0,'jumin'=>0];
        $s34 = $sc['saved_3_4'] ?? ['itax'=>0,'jumin'=>0];

        $row = static function(array $t, array $saved) use ($fmtYen) {
          $itax = (int)($t['itax'] ?? 0);
          $jm   = (int)($t['j_muni'] ?? 0);
          $jp   = (int)($t['j_pref'] ?? 0);
          $tot  = (int)($t['total'] ?? 0);
          $sit  = (int)($saved['itax'] ?? 0);
          $sjm  = (int)($saved['jumin'] ?? 0);
          return [
            $fmtYen($itax),
            $fmtYen($jm),
            $fmtYen($jp),
            $fmtYen($tot),
            $fmtYen($sit),
            $fmtYen($sjm),
          ];
        };
        [$r1_it,$r1_jm,$r1_jp,$r1_tot,$r1_sit,$r1_sjm] = $row($c1, ['itax'=>0,'jumin'=>0]);
        [$r2_it,$r2_jm,$r2_jp,$r2_tot,$r2_sit,$r2_sjm] = $row($c2, $s12);
        [$r3_it,$r3_jm,$r3_jp,$r3_tot,$r3_sit,$r3_sjm] = $row($c3, $s13);
        [$r4_it,$r4_jm,$r4_jp,$r4_tot,$r4_sit,$r4_sjm] = $row($c4, $s14);
      @endphp

      <div class="table-responsive mb-3">
        <table class="table-base table-bordered align-middle text-start">
          <tr>
            <th style="width:120px;">ケース</th>
            <th class="text-end" style="width:140px;">所得税</th>
            <th class="text-end" style="width:160px;">住民税（市）</th>
            <th class="text-end" style="width:160px;">住民税（県）</th>
            <th class="text-end" style="width:160px;">税額合計</th>
            <th class="text-end" style="width:160px;">減税額（所得税）</th>
            <th class="text-end" style="width:160px;">減税額（住民税）</th>
          </tr>
          <tr>
            <td>① 寄付ゼロ</td>
            <td class="text-end">{{ $r1_it }}</td>
            <td class="text-end">{{ $r1_jm }}</td>
            <td class="text-end">{{ $r1_jp }}</td>
            <td class="text-end">{{ $r1_tot }}</td>
            <td class="text-end">{{ $r1_sit }}</td>
            <td class="text-end">{{ $r1_sjm }}</td>
          </tr>
          <tr>
            <td>② その他のみ</td>
            <td class="text-end">{{ $r2_it }}</td>
            <td class="text-end">{{ $r2_jm }}</td>
            <td class="text-end">{{ $r2_jp }}</td>
            <td class="text-end">{{ $r2_tot }}</td>
            <td class="text-end">{{ $r2_sit }}</td>
            <td class="text-end">{{ $r2_sjm }}</td>
          </tr>
          <tr>
            <td>③ 現在入力</td>
            <td class="text-end">{{ $r3_it }}</td>
            <td class="text-end">{{ $r3_jm }}</td>
            <td class="text-end">{{ $r3_jp }}</td>
            <td class="text-end">{{ $r3_tot }}</td>
            <td class="text-end">{{ $r3_sit }}</td>
            <td class="text-end">{{ $r3_sjm }}</td>
          </tr>
          <tr>
            <td>④ 上限まで</td>
            <td class="text-end"><strong>{{ $r4_it }}</strong></td>
            <td class="text-end"><strong>{{ $r4_jm }}</strong></td>
            <td class="text-end"><strong>{{ $r4_jp }}</strong></td>
            <td class="text-end"><strong>{{ $r4_tot }}</strong></td>
            <td class="text-end"><strong>{{ $r4_sit }}</strong></td>
            <td class="text-end"><strong>{{ $r4_sjm }}</strong></td>
          </tr>
        </table>
      </div>

      <h6 class="mt-3">（参考）②を基準にした減税額（②−③ / ②−④）</h6>
      <div class="table-responsive mb-2">
        <table class="table-base table-bordered align-middle text-start">
          <tr>
            <th style="width:160px;">差分</th>
            <th class="text-end" style="width:220px;">減税額（所得税）</th>
            <th class="text-end" style="width:220px;">減税額（住民税）</th>
          </tr>
          <tr>
            <td>① − ②</td>
            <td class="text-end">{{ $fmtYen($s12['itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmtYen($s12['jumin'] ?? 0) }}</td>
          </tr>
          <tr>
            <td>② − ③</td>
            <td class="text-end">{{ $fmtYen($s23['itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmtYen($s23['jumin'] ?? 0) }}</td>
          </tr>
          <tr>
            <td>③ − ④</td>
            <td class="text-end">{{ $fmtYen($s34['itax'] ?? 0) }}</td>
            <td class="text-end">{{ $fmtYen($s34['jumin'] ?? 0) }}</td>
          </tr>
          <tr>
            <td>② − ④</td>
            <td class="text-end"><strong>{{ $fmtYen($s24['itax'] ?? 0) }}</strong></td>
            <td class="text-end"><strong>{{ $fmtYen($s24['jumin'] ?? 0) }}</strong></td>
          </tr>
        </table>
      </div>
    @endif

    {{-- 1. 前提となる金額・率 --}}
    <h6 class="mt-2">1. 前提となる金額・率</h6>
    <div class="table-responsive mb-3">
      <table class="table-base table-bordered align-middle text-start">
        <tr>
          <th style="width:180px;">項目</th>
          <th style="width:150px;" class="text-end">金額／率</th>
          <th>定義・備考</th>
        </tr>
        <tr>
          <td>所得税の総所得金額等</td>
          <td class="text-end">{{ $fmtYen($S40) }}</td>
          <td class="text-start align-middle ps-1">
            所得税の計算に用いる「総所得金額等」です。<br>
            総合課税の所得に加え、山林所得・退職所得・分離課税の所得などを合計した金額です。<br>
            所得税側の上限判定で用いるベースの所得額になります。
          </td>
        </tr>
        <tr>
          <td>住民税の総所得金額等</td>
          <td class="text-end">{{ $fmtYen($S30) }}</td>
          <td class="text-start align-middle ps-1">
            住民税の計算に用いる「総所得金額等」です。<br>
            総合課税の所得に加え、山林所得・退職所得・分離課税の所得などを合計した金額です。<br>
            住民税側の上限判定で用いるベースの所得額になります。
          </td>
        </tr>
        <tr>
          <td>調整控除後所得割額のベース</td>
          <td class="text-end">{{ $fmtYen($R) }}</td>
          <td class="text-start align-middle ps-1">
            調整控除適用後の、都道府県民税・市区町村民税の「所得割額」の合計です。<br>
            住民税所得割の 20％ルール の判定に使うベース金額です。
          </td>
        </tr>
        <tr>
          <td>特例控除 最終率</td>
          <td class="text-end">
            {{ $alphaPercent !== null && $alphaPercent > 0 ? $fmtPercent($alphaPercent) : '－' }}
          </td>
          <td class="text-start align-middle ps-1">ふるさと納税による特例控除の最終的な割合です。復興特別所得税や山林・退職等の要素を加味した実効率に相当します。</td>
        </tr>
        <tr>
          <td>今年の寄附金合計</td>
          <td class="text-end">{{ $fmtYen($DTotal) }}</td>
          <td class="text-start align-middle ps-1">今年 1 年間に支払った寄附金の合計額です（ふるさと納税と、それ以外の寄附をすべて含みます）。</td>
        </tr>
        <tr>
          <td>うち ふるさと納税額</td>
          <td class="text-end">{{ $fmtYen($DFuru) }}</td>
          <td class="text-start align-middle ps-1">上記のうち、ふるさと納税として支払った寄附金額です。</td>
        </tr>
        <tr>
          <td>ふるさと納税以外の寄附額</td>
          <td class="text-end">{{ $fmtYen($DOther) }}</td>
          <td class="text-start align-middle ps-1">今年の寄附金合計から、ふるさと納税分を差し引いた「ふるさと納税以外の寄附金額」です。</td>
        </tr>
      </table>
    </div>

    {{-- 2. 各上限制約ごとの Kmax --}}
    <h6 class="mt-3">2. 各上限制約ごとのふるさと納税上限額</h6>

    <div class="mb-3">
      <h6>① 所得税の 40％上限によるふるさと納税上限額</h6>
      <p class="mb-1">
        <strong>説明：</strong><br>
        「今年の寄附金合計（ふるさと納税＋その他の寄附）」が
        「所得税の総所得金額等 × 40％」以内に収まるように計算したときの、
        ふるさと納税として出せる最大額がふるさと納税上限額です。<br>
        すでに行っている「ふるさと納税以外の寄附」は、この 40％枠をあらかじめ使っているとみなされるため、
        その分を差し引いた残りの枠が、ふるさと納税の上限になります。
      </p>
      <p class="mb-1">
        <strong>条件：</strong><br>
        寄附金合計（ふるさと納税＋その他の寄附）≦
        所得税の総所得金額等 × 40％<br>
        ふるさと納税額 ≦ 所得税の総所得金額等 × 40％ − ふるさと納税以外の寄附額
      </p>
      <p class="mb-0">
        <strong>このケースでの計算：</strong><br>
        <br>
        0.4 × {{ $fmtYen($S40) }}
        − {{ $fmtYen($DOther) }}
        ＝ <strong>{{ $fmtYen($K40) }}</strong>
      </p>
    </div>

    <div class="mb-3">
      <h6>② 住民税の 30％ガードによるふるさと納税上限額</h6>
      <p class="mb-1">
        <strong>説明：</strong><br>
        「今年の寄附金合計（ふるさと納税＋その他の寄附）」が
        「住民税の対象となる所得 × 30％」以内に収まるように計算したときの、
        ふるさと納税として出せる最大額がふるさと納税上限額です。<br>
        こちらも、すでに「ふるさと納税以外の寄附」をしている場合、その分だけ 30％の枠があらかじめ減ることになります。
      </p>
      <p class="mb-1">
        <strong>条件：</strong><br>
        寄附金合計（ふるさと納税＋その他の寄附）≦
        所得税の総所得金額等 × 30％<br>
        ふるさと納税額 ≦ 住民税の対象所得 × 30％ − ふるさと納税以外の寄附額
      </p>
      <p class="mb-0">
        <strong>このケースでの計算：</strong><br>
        <br>
        0.3 × {{ $fmtYen($S30) }}
        − {{ $fmtYen($DOther) }}
        ＝ <strong>{{ $fmtYen($K30) }}</strong>
      </p>
    </div>

    <div class="mb-3">
      <h6>③ 住民税所得割の 20％上限によるふるさと納税上限額</h6>
      @if ($K20 === null)
        <p class="mb-0">
          R（調整控除後の住民税所得割のベース）が 0 以下、または特例控除の最終率が 0％となっているため、<br>
          今年は「所得税の 40％ルール」と「住民税の 30％ルール」のどちらかが上限を決める形になります。
        </p>
      @else
        <p class="mb-1">
          <strong>説明：</strong><br>
          ふるさと納税などによる特例控除の合計が、
          住民税所得割額のおおむね 20％以内になるように、ふるさと納税の上限額を求めています。<br>
          実務上は、「特例控除の最終率（α）」と「住民税所得割額のベース（R）」を用いて、
          K ≦ 0.2 × R / α ＋ 2,000 という形の上限式になります。
        </p>
        <p class="mb-1">
          <strong>条件：</strong><br>
          「特例控除の最終率（α）」×「（ふるさと納税額 − 2,000 円）」≦
          「住民税所得割額のベース × 20％」<br>
          ふるさと納税額 ≦ 住民税所得割額のベース × 20％ ÷ 特例控除の最終率 ＋ 2,000 円
        </p>
        <p class="mb-0">
          <strong>このケースでの計算：</strong><br>
          <br>
          0.2 × {{ $fmtYen($R) }}
          ÷ {{ $alphaPercent !== null && $alphaPercent > 0 ? $fmtPercent($alphaPercent) : '－' }}
          ＋ 2,000
          ≒ <strong>{{ $fmtYen($K20) }}</strong>
        </p>
      @endif
    </div>

    {{-- 3. 最終 Kmax と残り寄附可能額 --}}
    <h6 class="mt-4">3. 最終的なふるさと納税の理論上限額 Kmax</h6>

    @if ($Kmax === null)
      <p class="text-danger">
        所得や寄附金額の情報が不足しているため、ふるさと納税上限額を算出できません。<br>
        第一表の所得・第三表の分離所得・寄附金の入力内容をご確認ください。
      </p>
    @else
      <p class="mb-1">
        候補となる上限は次のとおりです：
        <br>
        ・所得税の総所得金額等40%制約 = {{ $fmtYen($K40) }}<br>
        ・住民税の総所得金額等30%制約 = {{ $fmtYen($K30) }}<br>
        ・住民税の所得割額20%制約 = {{ $K20 === null ? '（本年は制約なし）' : $fmtYen($K20) }}
      </p>
      <p class="mb-1">
        したがって、これらのうち最も小さい値が<strong>理論上のふるさと納税上限額</strong>となります。
        <br>
        このケースでは
        <strong>
          @if ($binding === '20')
            住民税の所得割額20%制約
          @elseif ($binding === '30')
            住民税の総所得金額等30%制約
          @else
            所得税の総所得金額等40%制約
          @endif
        </strong>
        が支配的となり、
        <br>
        <strong>今年のふるさと納税の理論上限額は {{ $fmtYen($Kmax) }}</strong> です。
      </p>
      <p class="mb-0">
        すでに入力されているふるさと納税額：{{ $fmtYen($DFuru) }}<br>
        ⇒ <strong>追加で寄附可能な上限＝ {{ $fmtYen($remaining) }}</strong>
      </p>
    @endif

    {{-- 4. 寄附金限度額計算の詳細（表） --}}
    <h6 class="mt-4">4. 寄附金限度額計算の詳細</h6>

    <style>
      /* result_upper: 寄附金限度額詳細テーブル用 */
      .kifu-limit-table { width: 100%; max-width: 100%; table-layout: fixed; }
      .kifu-limit-table th,
      .kifu-limit-table td { vertical-align: middle; }
      .kifu-limit-table .th-center { text-align: center; }
      .kifu-limit-table .td-center { text-align: center; }
      .kifu-limit-table .td-end { text-align: end; }

      /* 左の縦書き */
      .kifu-limit-table .vtext {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        white-space: nowrap;
        text-align: center;
        letter-spacing: 0.05em;
      }

      /* 横線を消す対象（3～8列相当） */
      .kifu-limit-table .detail-cell { background-clip: padding-box; }
      .kifu-limit-table tr.no-bb .detail-cell { border-bottom: 0 !important; }
      .kifu-limit-table tr.no-bt .detail-cell { border-top: 0 !important; }
    </style>

    <div class="table-responsive mb-3">
      <table class="table-base align-middle">
        <colgroup>
          <col style="width:40px;">   {{-- 1列目（縦書き） --}}
          <col style="width:90px;">   {{-- 2列目（所得控除/税額控除） --}}
          <col style="width:140px;">  {{-- 3列目（上限/今まで/残り/差額） --}}
          <col style="width:120px;">  {{-- 4列目（所得税） --}}
          <col style="width:120px;">  {{-- 5列目（住民税：市） --}}
          <col style="width:120px;">  {{-- 6列目（住民税：県） --}}
          <col style="width:120px;">  {{-- 7列目（住民税：小計） --}}
          <col style="width:120px;">  {{-- 8列目（合計） --}}
        </colgroup>
        <thead>
          <tr>
            <th class="th-ccc diag-cell th-center" colspan="3" rowspan="2"></th>
            <th class="th-ccc th-center" rowspan="2">所得税</th>
            <th class="th-ccc th-center" colspan="3">住民税</th>
            <th class="th-ccc th-center" rowspan="2">合計</th>
          </tr>
          <tr>
            <th class="th-ccc th-center">市区町村民税</th>
            <th class="th-ccc th-center">都道府県民税</th>
            <th class="th-ccc th-center">小計</th>
          </tr>
        </thead>
        <tbody>
          {{-- ふるさと納税（3～8行） --}}
          <tr>
            <th class="th-ccc vtext" rowspan="6">ふるさと納税</th>
            <th class="th-ccc th-center" rowspan="3">所得控除</th>
            <th class="th-ddd th-center detail-cell b-b-no">上限額</th>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-center detail-cell b-b-no">ー</td>
            <td class="td-center detail-cell b-b-no">ー</td>
            <td class="td-center detail-cell b-b-no">ー</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell b-t-no">今までに寄付した額</th>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-center detail-cell b-t-no">ー</td>
            <td class="td-center detail-cell b-t-no">ー</td>
            <td class="td-center detail-cell b-t-no">ー</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell">残りの寄付可能額</th>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-center detail-cell">ー</td>
            <td class="td-center detail-cell">ー</td>
            <td class="td-center detail-cell">ー</td>
            <td class="td-end detail-cell">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ccc th-center" rowspan="3">税額控除</th>
            <th class="th-ddd th-center detail-cell b-b-no">上限額</th>
            <td class="td-center detail-cell b-b-no">ー</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell b-t-no">今までに寄付した額</th>
            <td class="td-center detail-cell b-t-no">ー</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell">差額</th>
            <td class="td-center detail-cell">ー</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
          </tr>

          {{-- その他の寄付（9～14行） --}}
          <tr>
            <th class="th-ccc vtext" rowspan="6">その他の寄付</th>
            <th class="th-ccc th-center" rowspan="3">所得控除</th>
            <th class="th-ddd th-center detail-cell b-b-no">上限額</th>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-center detail-cell b-b-no">ー</td>
            <td class="td-center detail-cell b-b-no">ー</td>
            <td class="td-center detail-cell b-b-no">ー</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell b-t-no">今までに寄付した額</th>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-center detail-cell b-t-no">ー</td>
            <td class="td-center detail-cell b-t-no">ー</td>
            <td class="td-center detail-cell b-t-no">ー</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell">残りの寄付可能額</th>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-center detail-cell">ー</td>
            <td class="td-center detail-cell">ー</td>
            <td class="td-center detail-cell">ー</td>
            <td class="td-end detail-cell">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ccc th-center" rowspan="3">税額控除</th>
            <th class="th-ddd th-center detail-cell b-b-no">上限額</th>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell b-t-no">今までに寄付した額</th>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell">残りの寄付可能額</th>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
          </tr>

          {{-- 合計（15～17行：列1-2結合） --}}
          <tr>
            <th class="th-ccc th-center" colspan="2" rowspan="3">合計</th>
            <th class="th-ddd th-center detail-cell b-b-no">上限額</th>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
            <td class="td-end detail-cell b-b-no">&nbsp;</td>
          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell b-t-no">今までに寄付した額</th>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>
            <td class="td-end detail-cell b-t-no">&nbsp;</td>

          </tr>
          <tr>
            <th class="th-ddd th-center detail-cell">差額</th>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
            <td class="td-end detail-cell">&nbsp;</td>
          </tr>
        </tbody>
      </table>
    </div>
    <hr>
    <p class="p-small mb-0">
      ※ この画面で示しているふるさと納税上限額は、所得税法・地方税法に定められた上限式をベースとした
      「理論上の上限額」です。<br>
      &nbsp;&nbsp;住宅ローン控除・配当控除など他の税額控除の適用状況によっては、
      実際の税額計算上はふるさと納税上限額まで寄附しても控除しきれない場合があります。
    </p>
  </div>
</div>