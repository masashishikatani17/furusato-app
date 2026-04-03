<?php

/**
 * 分離譲渡（短期・長期）HELP（bunri_joto_details 専用）
 * key はボタンの data-help-key と一致させる
 *
 * 記法（任意）：
 * - 行頭 "○" は自動で太字＋色 (#192C4B)
 * - 行頭 "(1)" などは自動で太字（色はそのまま）
 * - "__下線__" と書くと、その部分だけ下線になります
 */
return [
    'bunri_joto_tansho_choki' => [
        'title' => '分離譲渡（短期・長期）',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-4">　分離譲渡とはその名のとおり他の所得とは分離して単独で課税される譲渡所得のことです。</div>'
            . '<div class="furu-help-head mb-2">○具体例</div>'
            . '<div class="help-text furu-help-line mb-2">　土地や建物などの譲渡から生ずる所得</div>'
            . '<div class="help-text furu-help-note-hanging mb-0">※「譲渡所得の内訳書（確定申告書付表兼計算明細書）【土地・建物用】」で計算し、確定申告書と一緒に提出します。</div>'
            . '<div class="help-text furu-help-body mb-4">　申告書（第一表・第二表）と分離用（第三表）等を使用します。</div>'
            . '<div class="help-text furu-help-body mb-0"><u>なお、このソフトではふるさと納税の計算の仕組みを理解しやすいように第一表と第三表を一緒にした方法を採用しています。</u></div>'
            . '<div class="help-text furu-help-body mb-4"><u>所得税や住民税の税理士試験でもこの方式を採用しています。</u></div>'
            . '<div class="furu-help-head mb-2">○短期と長期の区分</div>'
            . '<div class="help-text furu-help-body mb-1">　短期譲渡：譲渡した年の１月１日現在で所有期間が５年以内のもの</div>'
            . '<div class="help-text furu-help-body mb-2">　長期譲渡：譲渡した年の１月１日現在で所有期間が５年超のもの</div>'
            . '<div class="help-text furu-help-note-hanging mb-5">※総合課税の譲渡所得は、取得した時から売った時までの保有期間によって判定しますのでご注意下さい。</div>'
            . '<div class="furu-help-head mb-2">○計算方法</div>'
            . '<div class="help-text furu-help-body mb-4">　　譲渡所得の金額 ＝ 収入金額 － 必要経費 － 特別控除額</div>'
            . '<div class="furu-help-head mb-2">○税率</div>'
            . '<div class="help-text furu-help-body mb-2"><u>いずれも所得税について復興特別所得税が2.1%あり</u></div>'
            . '<table class="table-base mx-auto mb-4" style="width:460px;">'
            . '  <thead>'
            . '    <tr>'
            . '      <th colspan="3" style="height:28px;">区　分</th>'
            . '      <th style="width:90px;">所得税</th>'
            . '      <th style="width:90px;">住民税</th>'
            . '    </tr>'
            . '  </thead>'
            . '  <tbody>'
            . '    <tr>'
            . '      <th rowspan="2" style="width:40px;">短期</th>'
            . '      <td colspan="2" class="text-start nowrap" style="background:#F7F9FB;">一般分</td>'
            . '      <td class="text-center nowrap">30％</td>'
            . '      <td class="text-center nowrap">9％</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td colspan="2" class="text-start nowrap" style="background:#F7F9FB;">軽減分(注１)</td>'
            . '      <td class="text-center nowrap">15％</td>'
            . '      <td class="text-center nowrap">5％</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <th rowspan="5">長期</th>'
            . '      <td colspan="2" class="text-start nowrap" style="background:#F7F9FB;">一般分</td>'
            . '      <td class="text-center nowrap">15％</td>'
            . '      <td class="text-center nowrap">5％</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td rowspan="2" class="text-start nowrap" style="width:90px;background:#F7F9FB;">特定分(注２)</td>'
            . '      <td class="text-start nowrap" style="width:150px;">2,000万円以下の部分</td>'
            . '      <td class="text-center nowrap">10％</td>'
            . '      <td class="text-center nowrap">4％</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td class="text-start nowrap">2,000万円超の部分</td>'
            . '      <td class="text-center nowrap">15％</td>'
            . '      <td class="text-center nowrap">5％</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td rowspan="2" class="text-start nowrap" style="width:90px;background:#F7F9FB;">軽課分(注３)</td>'
            . '      <td class="text-start nowrap">6,000万円以下の部分</td>'
            . '      <td class="text-center nowrap">10％</td>'
            . '      <td class="text-center nowrap">4％</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td class="text-start nowrap">6,000万円超の部分</td>'
            . '      <td class="text-center nowrap">15％</td>'
            . '      <td class="text-center nowrap">5％</td>'
            . '    </tr>'
            . '  </tbody>'
            . '</table>'
            . '<div class="help-text furu-help-note-hanging ms-12 mb-1">(注1)譲渡先が国、地方公共団体等</div>'
            . '<div class="help-text furu-help-note-hanging ms-12 mb-1">(注2)譲渡先が国、地方公共団体等＋優良住宅地等のための譲渡</div>'
            . '<div class="help-text furu-help-note-hanging ms-12">(注3)譲渡した年の１月１日現在で所有期間が10年を超える居住用財産の譲渡</div>',
     ],
];
