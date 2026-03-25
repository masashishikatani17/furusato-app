<?php

/**
 * HELP jintekikojo本文辞書（このページ専用）
 * key はボタンの data-help-key と一致させる
 */


return [
   'kafu_hitorioya' => [
    'title' => '寡婦控除・ひとり親控除',
    'html'  => ''
        . '<div class="help-text">　以下の控除額は全て所得税に関するものです。住民税については「所得税と住民税の人的控除額の比較」をご覧ください。</div>'
        . '<div class="help-text">　納税者が<span style="color:#d90000;">寡婦</span>（★）であるときは、27万円を寡婦控除として所得金額から控除できます。また、納税者が<span style="color:#d90000;">ひとり親</span>（★）であるときは、35万円をひとり親控除として所得金額から控除できます。</div>'
        . '<div class="help-text" style="padding-left:2.5em; text-indent:-2.5em;">★<span style="color:#d90000;">寡婦</span><br>原則としてその年の12月31日の現況で、本人の合計所得金額が500万円以下で、次のいずれかに当てはまる人（ひとり親に該当する人を除く）です（事実上婚姻関係と同様の事情にあると認められる一定の人がいる場合を除く。）。<br>'
        . '　① 夫と死別した後、婚姻をしていない人または夫の生死が明らかでない人<br>'
        . '　② 夫と離婚した後、婚姻をしておらず扶養親族がいる人</div>'
        . '<div class="help-text" style="padding-left:2.5em; text-indent:-2.5em;">★<span style="color:#d90000;">ひとり親</span><br>原則としてその年の12月31日の現況で、本人の合計所得金額が500万円以下で、婚姻をしていないこと、または配偶者の生死の明らかでない一定の人のうち、その者と生計を一にする子（その年分の総所得金額等が58万円以下で、他の人の同一生計配偶者や扶養親族になっていない子）がいる人をいいます（事実上婚姻関係と同様の事情にあると認められる一定の人がいる場合を除く。）。</div>'
        . '<div class="help-text">　具体的には次の「寡婦控除・ひとり親控除の判定」で行います。より詳しくは令和２年５月に国税庁が公表した「ひとり親控除及び寡婦控除に関するＱ＆Ａ」を参照してください。</div>'
        . '<div><strong>○寡婦控除・ひとり親控除の判定</strong></div>'
       . '<table class="help-tax-table">'
        . '  <thead>'
        . '    <tr>'
        . '      <th colspan="6">寡婦控除・ひとり親控除の判定</th>'
        . '      <th>控除区分</th>'
        . '      <th>控除額</th>'
        . '    </tr>'
        . '  </thead>'
        . '  <tbody>'
        . '    <tr>'
        . '      <th rowspan="10">本人の合計<br>所得金額が<br>500万円以下</th>'
        . '      <th rowspan="9">婚姻なし<br>（※３）</th>'
        . '      <th rowspan="2" colspan="2">男性</th>'
        . '      <td colspan="2" class="text-start nowrap">扶養する子あり（※２）</td>'
        . '      <td>ひとり親控除</td>'
        . '      <td>35万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td colspan="2" class="text-start nowrap">扶養する子なし</td>'
        . '      <td>非該当</td>'
        . '      <td>－</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <th rowspan="7" style="width:44px;">女性</th>'
        . '      <th rowspan="2" style="width:48px;">死別<br>（※１）</th>'
        . '      <td colspan="2" class="text-start nowrap">扶養する子あり（※２）</td>'
        . '      <td>ひとり親控除</td>'
        . '      <td>35万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td colspan="2" class="text-start nowrap">扶養する子なし</td>'
        . '      <td>寡婦控除</td>'
        . '      <td>27万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <th rowspan="3">離婚</th>'
        . '      <td colspan="2" class="text-start nowrap">扶養する子あり（※２）</td>'
        . '      <td>ひとり親控除</td>'
        . '      <td>35万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td rowspan="2" class="text-start nowrap">扶養する子なし</td>'
        . '      <td class="text-start nowrap">扶養親族あり</td>'
        . '      <td>寡婦控除</td>'
        . '      <td>27万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td class="text-start nowrap">扶養親族なし</td>'
        . '      <td>非該当</td>'
        . '      <td>－</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <th rowspan="2">未婚</th>'
        . '      <td colspan="2" class="text-start nowrap">扶養する子あり（※２）</td>'
        . '      <td>ひとり親控除</td>'
        . '      <td>35万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td colspan="2" class="text-start nowrap">扶養する子なし</td>'
        . '      <td>非該当</td>'
        . '      <td>－</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <th colspan="5" class="text-start">婚姻あり</th>'
        . '      <td>非該当</td>'
        . '      <td>－</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <th colspan="6" class="text-start">上記以外</th>'
        . '      <td>非該当</td>'
        . '      <td>－</td>'
        . '    </tr>'
        . '  </tbody>'
        . '</table>'
        . '<div class="help-text"><h14u>※１：死別・生死不明を含む。</br>
         ※２：総所得金額等が58万円以下の生計を一にする子であること。なお、扶養親族には事業専従者は含まれない<br>　　　が、上記の生計を一にする子には事業専従者である場合を含む。<br>
         ※３：婚姻の判定に関する細かな取扱いは、国税庁公表のＱ＆Ａも併せて確認してください。</h14u></div>',
],

    'kinrogakusei' => [
        'title' => '勤労学生控除',
        'body'  => "準備中です。",
    ],

    'shogaisha' => [
        'title' => '障害者控除',
        'body'  => "準備中です。",
    ],

    'haigusha' => [
        'title' => '配偶者控除',
        'body'  => "準備中です。",
    ],

    'haigusha_tokubetsu' => [
        'title' => '配偶者特別控除',
        'body'  => "準備中です。",
    ],

    'fuyo' => [
        'title' => '扶養控除',
        'body'  => "準備中です。",
    ],

    'tokutei_shinzoku_tokubetsu' => [
        'title' => '特定親族特別控除',
        'body'  => "準備中です。",
    ],
];