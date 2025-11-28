<!-- resources/views/tax/furusato/tabs/result_upper_furusato.blade.php -->
@php
    // Controller から渡された kmax コンテキスト
    /** @var array<string,mixed>|null $kmax */
    $k = $kmax ?? [];

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
    $fmtPercent = static function ($v, int $dec = 3): string {
        if ($v === null) {
            return '－';
        }
        return number_format((float)$v, $dec) . ' %';
    };
@endphp

<div class="container-blue mt-2">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">ふるさと納税の理論上限額（Kmax）</h0>
  </div>

  <div class="card-body">
    @if ($Kmax === null)
      <p class="text-danger">
        Kmax を算出するために必要な所得情報または寄附金情報が不足しているため、<br>
        現時点では理論上限額を表示できません。
      </p>
    @endif

    {{-- 1. 前提となる金額・率 --}}
    <h6 class="mt-2">1. 前提となる金額・率</h6>
    <div class="table-responsive mb-3">
      <table class="table-base table-bordered align-middle text-start">
        <tr>
          <th style="width:260px;">項目</th>
          <th style="width:220px;" class="text-end">金額／率</th>
          <th>定義・備考</th>
        </tr>
        <tr>
          <td>所得税の総所得金額等</td>
          <td class="text-end">{{ $fmtYen($S40) }}</td>
          <td>
            所得税の計算に用いる「総所得金額等」です。<br>
            総合課税の所得に加え、山林所得・退職所得・分離課税の所得などを合計した金額です。
          </td>
        </tr>
        <tr>
          <td>総所得＋退職＋山林</td>
          <td class="text-end">{{ $fmtYen($S30) }}</td>
          <td>
            住民税の計算に用いる「総所得」「山林所得」「退職所得」を合計した金額です。<br>
            住民税側の上限判定で用いるベースの所得額（S₃₀）になります。
          </td>
        </tr>
        <tr>
          <td>調整控除後所得割額のベース</td>
          <td class="text-end">{{ $fmtYen($R) }}</td>
          <td>
            調整控除適用後の、都道府県民税・市区町村民税の「所得割額」の合計です。<br>
            住民税所得割の 20％ルール の判定に使うベース金額です。
          </td>
        </tr>
        <tr>
          <td>特例控除 最終率</td>
          <td class="text-end">
            {{ $alphaPercent !== null && $alphaPercent > 0 ? $fmtPercent($alphaPercent) : '－' }}
          </td>
          <td>ふるさと納税による特例控除の最終的な割合です。復興特別所得税や山林・退職等の要素を加味した実効率に相当します。</td>
        </tr>
        <tr>
          <td>今年の寄附金合計</td>
          <td class="text-end">{{ $fmtYen($DTotal) }}</td>
          <td>今年 1 年間に支払った寄附金の合計額です（ふるさと納税と、それ以外の寄附をすべて含みます）。</td>
        </tr>
        <tr>
          <td>うち ふるさと納税額</td>
          <td class="text-end">{{ $fmtYen($DFuru) }}</td>
          <td>上記のうち、ふるさと納税として支払った寄附金額です。</td>
        </tr>
        <tr>
          <td>ふるさと以外の寄附額</td>
          <td class="text-end">{{ $fmtYen($DOther) }}</td>
          <td>今年の寄附金合計から、ふるさと納税分を差し引いた「ふるさと納税以外の寄附金額」です。</td>
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
        住民税の対象所得（総所得＋退職＋山林）× 30％<br>
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
        ⇒ <strong>追加で寄附可能な上限（残り余力）＝ {{ $fmtYen($remaining) }}</strong>
      </p>
    @endif

    <hr>
    <p class="p-small mb-0">
      ※ この画面で示しているふるさと納税上限額は、所得税法・地方税法に定められた上限式をベースとした
      「理論上の上限額」です。<br>
      &nbsp;&nbsp;住宅ローン控除・配当控除など他の税額控除の適用状況によっては、
      実際の税額計算上はふるさと納税上限額まで寄附しても控除しきれない場合があります。
    </p>
  </div>
</div>