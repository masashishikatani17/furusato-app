<?php

/**
 * 内訳－株式等の譲渡所得等HELP（bunri_kabuteki_details 専用）
 * key はボタンの data-help-key と一致させる
 *
 * 記法（任意）：
 * - 行頭 "○" は自動で太字（見出し）になります
 * - "__下線__" と書くと、その部分だけ下線になります（このページのJSが変換）
 */
return [

    'bunri_kabuteki' => [
        'title' => '株式等の譲渡所得等',
        'html' => ''
            . '<div class="help-text furu-help-line mb-4">　ここでは一般株式等の譲渡、上場株式等の譲渡、上場株式等の配当等についてまとめて入力します。</div>'
            . '<div class="furu-help-head mb-2">○入力に当たっての注意点</div>'
            . '<div class="help-text furu-help-item mb-1">①一般株式等の譲渡損失</div>'
            . '<div class="help-text furu-help-body mb-0">　　上場株式等の譲渡益や上場株式等の配当等とは損益通算できない。その損失はその年度で打ち切りとなる。</div>'
            . '<div class="help-text furu-help-body mb-3">　　ただし非上場株式であっても特定中小会社株式等（いわゆるエンジェル税制等）は除く。</div>'
            . '<div class="help-text furu-help-item mb-1">②上場株式等の譲渡損失</div>'
            . '<div class="help-text furu-help-body mb-4">　　上場株式等の配当等と損益通算できる（ただし申告分離課税を選択した配当所得に限る）し、控除できない額は<br>　　翌期以降３年間まで繰越可能。</div>'
            . '<div class="furu-help-head mb-2">○税率</div>'
            . '<div class="help-text furu-help-body ms-10 mb-4"><u>いずれも所得税について復興特別所得税が2.1％あります。</u></div>'
            . '<table class="table-base mx-auto mb-2" style="width:340px;">'
            . '  <thead>'
            . '    <tr>'
            . '      <th colspan="2" style="width:160px;">区　分</th>'
            . '      <th style="width:90px;">所得税</th>'
            . '      <th style="width:90px;">住民税</th>'
            . '    </tr>'
            . '  </thead>'
            . '  <tbody>'
            . '    <tr>'
            . '      <td colspan="2" class="text-start nowrap">一般株式等の譲渡所得</td>'
            . '      <td rowspan="3" class="text-center align-middle nowrap">15％</td>'
            . '      <td rowspan="3" class="text-center align-middle nowrap">5％</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td colspan="2" class="text-start nowrap">上場株式等の譲渡所得</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td colspan="2" class="text-start nowrap">上場株式等の配当所得</td>'
            . '    </tr>'
            . '  </tbody>'
            . '</table>',
     ],
];