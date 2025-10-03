<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ふるさと納税 上限計算（計算結果）</title>
  <link rel="stylesheet" href="https://unpkg.com/sanitize.css">
  <style>
    body { font-family: system-ui, sans-serif; margin: 24px; }
    .container { max-width: 960px; margin: 0 auto; }
    table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
    th { background:#f6f6f6; }
    .left { text-align:left; }
    .actions { margin-top: 24px; }
  </style>
</head>
<body>
<div class="container">
  <h1>計算結果</h1>
  <div class="actions"><a href="{{ route('furusato.index') }}">← インプットへ戻る</a></div>

  <h2>入力サマリ</h2>
  <table>
    <tr><th class="left">指定都市</th><td>{{ $dto->designatedCity ? '指定都市（市8%/県2%）' : '非指定（市6%/県4%）' }}</td></tr>
    <tr><th class="left">総所得等の合計額</th><td>{{ number_format($dto->grossIncomeTotal) }} 円</td></tr>
    <tr><th class="left">課税総所得金額（丸め前）</th><td>{{ number_format($dto->taxableIncomeTotal) }} 円</td></tr>
    <tr><th class="left">課税総所得金額（1,000円未満切捨て後）</th><td>{{ number_format($result['taxableRounded']) }} 円</td></tr>
    <tr><th class="left">人的控除差（基礎以外）</th><td>{{ number_format($dto->personalDiffExclBase) }} 円</td></tr>
    <tr><th class="left">基礎控除差 50,000 を適用</th><td>{{ $dto->applyBaseDiff50k ? 'はい' : 'いいえ' }}</td></tr>
    <tr><th class="left">寄付額</th><td>{{ number_format($dto->donationAmount) }} 円</td></tr>
    <tr><th class="left">方式</th><td>{{ $dto->filingMethod === 'kakutei' ? '確定申告' : 'ワンストップ' }}</td></tr>
  </table>

  <h2>住民税（市/県）算定</h2>
  <table>
    <tr>
      <th class="left">項目</th>
      <th>市（円）</th>
      <th>県（円）</th>
      <th>合計（円）</th>
    </tr>
    <tr>
      <td class="left">算出所得割</td>
      <td>{{ number_format($result['cityLevyBeforeAdj']) }}</td>
      <td>{{ number_format($result['prefLevyBeforeAdj']) }}</td>
      <td>{{ number_format($result['cityLevyBeforeAdj'] + $result['prefLevyBeforeAdj']) }}</td>
    </tr>
    <tr>
      <td class="left">調整控除ベース a</td>
      <td class="left" colspan="3">{{ number_format($result['adjBase']) }}</td>
    </tr>
    <tr>
      <td class="left">調整控除額</td>
      <td>{{ number_format($result['adjCity']) }}</td>
      <td>{{ number_format($result['adjPref']) }}</td>
      <td>{{ number_format($result['adjCity'] + $result['adjPref']) }}</td>
    </tr>
    <tr>
      <td class="left">調整控除後の所得割額</td>
      <td>{{ number_format($result['cityLevyAfterAdj']) }}</td>
      <td>{{ number_format($result['prefLevyAfterAdj']) }}</td>
      <td>{{ number_format($result['cityLevyAfterAdj'] + $result['prefLevyAfterAdj']) }}</td>
    </tr>
  </table>

  <h2>寄附金税額控除</h2>
  <table>
    <tr><th class="left">基本控除（市/県）</th><td>{{ number_format($result['basicCity']) }} / {{ number_format($result['basicPref']) }}（合計 {{ number_format($result['basicTotal']) }}）</td></tr>
    <tr><th class="left">特例控除（市/県）</th><td>{{ number_format($result['specialCity']) }} / {{ number_format($result['specialPref']) }}（上限 {{ number_format($result['cap20']) }}、候補 {{ number_format($result['specialCandidate']) }}）</td></tr>
    <tr><th class="left">住民税 減税額：理論（丸め前）</th><td>{{ number_format($result['residentTheory']) }} 円</td></tr>
    <tr><th class="left">住民税 減税額：実額（切捨て後差額）</th><td>{{ number_format($result['residentActual']) }} 円</td></tr>
  </table>

  <h2>所得税（確定申告）</h2>
  <table>
    <tr><th class="left">所得税側控除額</th><td>{{ number_format($result['itCredit']) }} 円</td></tr>
  </table>

  <h2>合計の減税額</h2>
  <table>
    <tr><th class="left">理論合計（丸め前）</th><td>{{ number_format($result['totalTheory']) }} 円</td></tr>
    <tr><th class="left">実額合計（丸め・配分後）</th><td><strong>{{ number_format($result['totalActual']) }} 円</strong></td></tr>
    <tr><th class="left">丸め差（実額−理論）</th><td>{{ number_format($result['roundingDelta']) }} 円</td></tr>
  </table>

  <h2>上限ガイド</h2>
  <table>
    <tr><th class="left">理論X（20%上限に達する寄付）</th><td>{{ number_format($result['xCap20Theory']) }} 円</td></tr>
    <tr><th class="left">おすすめ上限（丸め考慮）</th><td><strong>{{ number_format($result['suggestedMax']) }} 円</strong>（理論−200円）</td></tr>
  </table>

  <div class="actions" style="margin-top:24px;">
    <a href="{{ route('furusato.index') }}">← インプットへ戻る</a>
  </div>
</div>
</body>
</html>