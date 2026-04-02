<?php

/**
 * HELP本文辞書（山林所得画面専用）
 * key はボタンの data-help-key と一致させる
 */
return [
    'sanrin_shotoku' => [
        'title' => '山林所得',
        'html'  => ''
            . '<div class="help-text furu-help-line">　山林所得とは山林を伐採して譲渡したり立木のままで譲渡することによって生ずる所得をいいます。ただし、山林を取得してから５年以内に譲渡した場合は山林所得ではなく事業所得か雑所得になります。</div>'
            . '<div class="help-text furu-help-line">　山林を土地付で譲渡する場合の土地の部分は譲渡所得となり通常の不動産を譲渡した場合の譲渡所得の計算式が適用されます。</div>'
            . "\n"
            . '<div class="furu-help-head">○計算方法</div>'
            . '<div class="help-text furu-help-body-last">　譲渡所得の金額 ＝ 総収入金額 －必要経費(※) －特別控除額(最高50万円)</div>'
            . '<div class="help-text furu-help-body-last">　※必要経費には実際にかかった経費で計算する方法と、概算経費で計算する方法の２種類ありますが<br>　　山林所得が発生した方は国税庁のホームページなどで調べて下さい。</div>'
            . "\n"
            . '<div class="furu-help-head">○税額計算</div>'
            . '<div class="help-text furu-help-body-last">　山林所得の税額計算については所得税と住民税で大きく異なります。それは所得税については５分乗条方式が適用されるが住民税には適用されないということです。以下、簡単に解説しておきます。</div>'
            . "\n"
            . '<div class="help-text furu-help-body-last"><span style="color:#701616;">　５分５乗方式</span>とは課税所得金額をいったん５で割り、その額に税率を適用して計算した額を５倍するというものです。</div>'
            . '<div class="help-text furu-help-body-last">　山林所得は通常、数十年かけて育てた山林を譲渡した場合に一挙に発生する所得ですが、所得税は累進課税になっているため所得が多いと高い税率が適用されます。それではどなたも納得できないと思われるのでこのような方式を採用しているわけです。</div>'
            . "\n"
            . '<div class="help-text furu-help-body-last">　一方の住民税ですが、山林所得に係る税率は10％で総合課税と同じです。つまり、いずれも10％の比例税率なので５分５乗方式を適用しても税額は変わらないのです。</div>'
            . "\n"
            . '<div class="help-text furu-help-body-last">　なお所得税に関しては通常の税額表を適用して計算します(※)が、分離課税なので他の所得とは独立して税額を計算します。次に説明する退職所得も同じ仕組みです。</div>'
            . "\n"
            . '<div class="help-text furu-help-note-hanging">※「通常の税額表を適用して計算します」と書いていますが、「課税される山林所得金額」に対する所得税の税額表というものもあります。これは５分５乗方式が織り込まれた税額表です。紛らわしいのでこんな税額表は無い方が良いと思うのですが･･･。</div>',
    ],
];