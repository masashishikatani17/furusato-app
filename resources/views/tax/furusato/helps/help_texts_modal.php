<?php

/**
 * HELP本文辞書（このページ専用）
 * key はボタンの data-help-key と一致させる
 */
return [
    'chousei_koujo' => [
        'title' => '調整控除',
        'body'  => ''
            . "　調整控除とは所得税と住民税で人的控除額（配偶者控除、扶養控除、基礎控除など）が違うことによって、住民税が不利（負担増）にならないようにするためのものです。"
            . "詳細については帳表の６ページにある「人的控除差額控除」をご覧下さい。\n"
            . "\n"
            . "　次に同じ帳票の３ページにある「課税所得金額・税額の予測」の中の税額控除の欄をご覧下さい。"
            . "寄附金税額控除の上に調整控除①、配当控除②、住宅借入金等特別控除③があります。"
            . "政党等寄附金等特別控除④、住宅耐震改修特別控除⑤は所得税にのみ関係するので無視して下さい。\n"
            . "　これらの税額控除はあくまで納税額から控除されるだけで控除不足額があっても還付されません。"
            . "つまり数値がマイナスの場合はゼロ（0）になるのです。\n"
            . "\n"
            . "　ところで寄附金税額控除は「下記以外」と「ふるさと（※）」の２つから構成されています。"
            . "このうち「下記以外」というのは「ふるさと（※）」以外を指しますが、具体的にはこの帳表の１ページにある寄附先が「住所地の共同募金、日赤等」、「認定NPO法人等」、「公益社団法人等」、「その他」の４つです。\n"
            . "　住民税は全て税額控除ですから、これらの寄附金があれば当然ながらふるさと納税の上限額はそれだけ少なくなります。\n"
            . "\n"
            . "　このようなことから住民税のふるさと納税上限額を算定する場合は、上記のような様々な税額控除の有無を考慮する必要があるのです。\n",
    ],

    'zasson_kojo' => [
        'title' => '雑損控除',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-3">　災害、盗難または横領によって資産に損害を受けた場合や、災害等に関連してやむを得ない支出をした場合に一定の金額を雑損控除して所得金額から控除できます。</div>'
            . '<div class="help-text furu-help-line">　ここではこれ以上の解説はしませんが、該当する方はご自分で計算の上、雑損控除額を入力して下さい。</div>',
    ],
     
    'kazei_shotoku_sogo' => [
        'title' => '総合課税',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-3">　所得金額等のうち最初に総合課税の所得から所得控除の額(所得から差し引かれる金額)を控除します。ここに表示されている課税所得金額(総合課税)は所得控除の額を控除した後の金額です。</div>'
            . '<div class="help-text furu-help-line mb-1">　総合課税の所得より所得控除の額が大きくて控除しきれない場合には分離課税のうち税率の高い所得から控除するようになっています。</div>'
            . '<div class="help-text furu-help-line">　ただし山林所得と退職所得に関しては所得金額に応じて適用税率が異なりますので、このシステムでは最後に適用するようになっています。</div>',
    ],
    
    'gokei_chosei_mae_shotokuwari' => [
        'title' => '合計(調整控除前所得割額)',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-3">　総合課税所得に係る税額から退職所得に係る税額までの合計です。住民税については調整控除前所得割額という名称を使います。この額から一つ下にある調整控除の額を差し引きます。</div>'
            . '<div class="help-text furu-help-line">内容については調整控除のヘルプに詳しい解説がありますので参考にして下さい。</div>',
    ],
    
    'kijun_shotokuzei_gaku_shotokuwari' => [
        'title' => '基準所得税額(所得割額)',
        'html'  => ''
            . '<div class="help-text furu-help-line">　上記、「合計(調整控除前所得割額)」から税額控除の額を差し引いた後の金額です。所得税では基準所得税額と言い、住民税では所得割額と言います。</div>',
    ],

    'fukkou_tokubetsu_shotokuzei_gaku' => [
        'title' => '復興特別所得税額',
        'html'  => ''
            . '<div class="help-text furu-help-line">　所得税では上記、東日本大震災からの復興のための復興特別所得税として基準所得税額の2.1％が加算されます。令和19年12月31日までです。</div>',
    ],

    'zeigaku_gokei' => [
        'title' => '合計',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-3">　この「ふるさと名人」はふるさと納税の上限額を求めることがメインなので税額計算はここまでです。実際の申告書では源泉徴収税額や予定納税額を控除して納付税額まで計算します。</div>'
            . '<div class="help-text furu-help-line">　また住民税では、ここで計算した所得割以外に均等割や森林環境税(国税)があります。</div>',
    ],
     
    'haitou_koujo' => [
        'title' => '配当控除',
        'body'  => ''
            . "　上場株式の配当所得については所得税、住民税とも総合課税方式、申告分離課税方式、申告不要から選択できることになっています。\n"
            . "\n"
            . "○申告不要\n"
            . "　　源泉徴収（所得税15.315%、住民税5%）で課税を終了させるというもので金額が少ない場合はこの方\n"
            . "　式を選択する人が多いと思います。\n"
            . "\n"
            . "○総合課税方式\n"
            . "　　配当所得を他の総合課税所得である給与所得、事業所得、不動産所得などと合算して税金を計算し、\n"
            . "　それから所定の方法で算定した配当控除の額を控除するというものです。\n"
            . "\n"
            . "○申告分離課税方式\n"
            . "　　他の総合課税所得とは切り離して配当所得だけで税額を計算するというものです。税率は上記申告不要\n"
            . "　の源泉徴収税率（所得税15.315%、住民税5%）と同じです。\n"
            . "\n"
            . "　これらのいずれを採用すべきは実際に最終納付税額を計算して比較することになりますが、配当所得は一般的にそれほど多くないので申告不要を選択する方が大多数であること、ふるさと納税上限額を計算する時点で３つの選択肢から優劣を比較するというのも現実的ではないことから、このシステムでは必要に応じてご自分で計算の上、配当控除の額を入力するようにしています。\n"
            . "\n"
            . "　ところで上記、調整控除のところで説明していますが、もし総合課税を選択して配当控除を選ぶ場合にはふるさと納税上限額が少なくなる可能性がありますのでご注意下さい。\n",
    ],

    'seitoto_kifu_tokubetsu' => [
        'title' => '政党等寄附金等特別控除',
        'body'  => ''
            . "　この政党等寄附金等特別控除は帳表の１ページにある寄附先が「政党等」、「認定NPO法人等」、「公益社団法人等」の３つについて税額控除を選択したものです。\n"
            . "　このシステムでは寄附金に関するインプット表に寄附額を入力しますと税額控除額を自動計算して、ここに数値が表示されるようになっています。\n"
            . "\n"
            . "　なお、これらの寄附金については所得控除を選択することもできますが、その場合には上欄にある「所得から差し引かれる金額」の寄附金控除欄に合計金額が表示されます。\n",
    ],

    'kifukin_zeigaku_koujo' => [
        'title' => '寄附金税額控除',
        'body'  => ''
            . "　この寄附金税額控除欄には住民税に係る寄附金税額控除の合計額が計算・表示されるようになっています。\n"
            . "　具体的な計算過程は帳表の３ページ「課税所得金額・税額の予測」と４ページ「所得税・住民税の軽減額の計算過程」（ワンストップ特例の場合には「住民税の軽減額の計算過程」）に表示されます。\n"
            . "\n"
            . "　備考欄に各帳表の関連を書いていますのでジックリと勉強してみて下さい。とにかく、ふるさと納税の計算の仕組みは複雑難解です。個人の住民税なんか勉強したことがない方が圧倒的に多いと思いますが、一度は完璧に理解されることをお勧めします。\n",
    ],

    'saigai_genmen' => [
        'title' => '災害減免額',
        'body'  => ''
            . "　災害で住宅や家財に甚大な損害を受けた場合に所得税及び住民税で一定の額を減免してくれます。これを災害減免額と言いますが、税額控除の一種です。\n"
            . "　この災害については所得控除の一種である雑損控除として処理することもできますので、両方で計算した上で有利な方を選択するようにして下さい。ここでは詳細は省略します。\n",
    ],

    'nogyo' => [
        'title' => '事業所得「農業」',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-3">　この「農業」には次のような事業から生ずる所得が含まれます。</div>'
            . '<div class="furu-help-head mb-1">○「農業」に含まれる所得の種類</div>'
            . '<div class="help-text furu-help-item">・農産物の生産、果樹などの栽培</div>'
            . '<div class="help-text furu-help-item">・養蚕、農家が兼営する家畜・家禽の飼育</div>'
            . '<div class="help-text furu-help-item-last mb-2">・酪農品の生産　etc.</div>'
            . '<div class="help-text furu-help-line mb-2">　この農業については「営業等」や「不動産」のように入力するための別画面は用意していません。その理由は一部の大規模農家以外は規模がそれほど大きくはないこと、規模が大きい場合には農業法人を設立してそこから給与をもらっているところが多いことから収入金額や所得金額を直接入力するようにしました。</div>'
            . '<div class="help-text furu-help-line">　規模がそれほど大きくないのに別画面でデータを入力するというのは面倒ではないかと考えた次第です。なお所得が赤字の場合には他の所得と損益通算できますので、数値の先頭にマイナス「－」を入れて下さい。</div>'
     ],

    'taishoku' => [
        'title' => '退職所得',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-4">　ふるさと納税上限額を計算する場合、退職所得に関して所得税と住民税では次のように取り扱いが異なります。</div>'
            . '<div class="furu-help-head">○税種別</div>'
            . '<div class="help-text furu-help-star">★<span class="furu-help-star-label">所得税</span></div>'
            . '<div class="help-text furu-help-body">　退職所得については通常、退職時点で課税関係が終了しますのでそれ以降は原則として登場することはないのですが、寄附金控除を受ける場合には登場してもらう必要があります。</div>'
            . '<div class="help-text furu-help-body-last">　というのも寄附金控除の上限額は総所得金額等の40％となっているのですが、この総所得金額等に退職所得も含まれるからです。これは医療費控除を受ける場合も同じです。</div>'
            . '<div class="help-text furu-help-star">★<span class="furu-help-star-label">住民税</span></div>'
            . '<div class="help-text furu-help-body">　所得税と同様、住民税でも退職所得については現年分離課税と言って退職時点で課税関係が終了します。ところが住民税の場合は寄附金税額控除の計算で分離課税の退職所得は含めません。</div>'
            . '<div class="help-text furu-help-body-last">　つまり所得税では退職所得に関する収入金額や所得金額を入力しますが、住民税ではゼロ(０)または空白とします。</div>'
            . "\n"
            . '<div class="help-text furu-help-line ms-3">　なお同じく退職所得でも下記の２つのケースは源泉分離課税ではなく総合課税の対象となります。これらについては事例が極めて少ないことから次回のバージョンアップ時に対応予定です。</div>'
            . '<div class="help-text furu-help-item">　①所得税の源泉徴収義務のない事業主が支払う退職手当等の場合</div>'
            . '<div class="help-text furu-help-item-last">　②退職手当等の支払を受けるべき日の属する年の１月１日現在、国内に住所を有しない場合</div>'
            . "\n"
            . '<div class="furu-help-head">○税額計算</div>'
            . '<div class="help-text furu-help-star">★<span class="furu-help-star-label">所得税</span></div>'
            . '<div class="help-text furu-help-item mb-1">①「退職所得の受給に関する申告書」を提出した場合</div>'
            . '<div class="help-text furu-help-formula-last">(収入金額 －退職所得控除額)×１/２×税額表の税率</div>'
            . '<div class="help-text furu-help-item mb-1">②「退職所得の受給に関する届出書」を提出しなかった場合</div>'
            . '<div class="help-text furu-help-formula">退職金×20.42%</div>'
            . '<div class="help-text furu-help-note">※退職所得控除額は適用されませんし、２分の１をかけることもしません。退職金そのものに対して税率をかけて計算します。<br>　しかし、これはあくまで源泉徴収税額の計算方法であって確定申告するときは上記①で計算します。</div>'
            . '<div class="help-text furu-help-star">★<span class="furu-help-star-label">住民税</span></div>'
            . '<div class="help-text furu-help-body-last">　上記で説明したように住民税は関係ありませんので省略します。計算式自体は所得税の①と同じです。</div>'
            . "\n"
            . '<div class="furu-help-head">○インプット表に入力する金額</div>'
            . '<div class="help-text furu-help-star">★<span class="furu-help-star-label">収入金額等</span></div>'
            . '<div class="help-text furu-help-body">　所得税の欄に退職金そのものを入力します。寄附金控除を受けるためには上限額を計算する必要上、必ず入力して下さい。</div>'
            . '<div class="help-text furu-help-body-last">　上記で説明したように住民税の場合はゼロ(０)または空白とします。</div>'
            . '<div class="help-text furu-help-star">★<span class="furu-help-star-label">所得金額等</span></div>'
            . '<div class="help-text furu-help-body-last">　上記①で計算した所得を入力します。住民税の場合はゼロ(０)または空白とします。</div>'
            . '<div class="help-text furu-help-star">★<span class="furu-help-star-label">課税所得金額</span></div>'
            . '<div class="help-text furu-help-body-last">　所得金額等(総所得金額等)から所得控除の額(所得から差し引かれる金額)を差し引いて計算しますが、所定の順序に従ってコンピュータで自動計算します。</div>'
            . '<div class="help-text furu-help-star">★<span class="furu-help-star-label">税額計算</span></div>'
            . '<div class="help-text furu-help-body">　所得税の税額表に基づいてコンピュータで自動計算します。</div>',
    ],
     
    'shakaihoken_kojo' => [
        'title' => '社会保険料控除',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-1">　自己または自己と生計を一にしている配偶者その他の親族の負担すべき社会保険料を支払った場合、または納税者の給与等から差し引かれた金額は全て所得から控除されます。</div>'
            . '<div class="help-text furu-help-line">　給与等の額にあまり変動がない場合には前年度と同じ額を入力すればいいでしょう。</div>'
            . '<div class="furu-help-head mt-3">○入力に当たってのポイント</div>'
            . '<div class="help-text furu-help-item mb-3">①社会保険料控除の対象となるのは納税者本人が支払ったものに限ります。配偶者等の年金から控除されている介護保険料等は配偶者本人の所得からでしか控除できません。</div>'
            . '<div class="help-text furu-help-item-last">②本年中に実際に支払った金額だけが対象となります。未納の社会保険料は対象となりません。</div>',
    ],
         

    'shokibo_kyosai_kakekin_kojo' => [
        'title' => '小規模企業共済等掛金控除',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-3">　小規模企業共済等掛金を支払った場合、全額が所得から控除されます。取りあえず前年度と同じ額を入力すればいいでしょう。</div>'
           . '<div class="furu-help-head mb-2">○入力に当たってのポイント</div>'
            . '<div class="help-text furu-help-line ms-3">　社会保険料控除とは異なり小規模企業共済等掛金控除を受けられるのは共済契約者本人に限られます。</div>',
    ],
       
'haitou' => [
    'title' => '配当（収入金額等）',
    'html'  => ''
        . '<div class="help-text furu-help-line mb-3">　配当所得に関しては所得税と住民税で下記のように若干異なった取扱いとなっています。</div>'
        . '<div class="furu-help-head mb-1">○配当所得に関する税務の取扱い</div>'
        . '<table class="help-tax-table">'
        . '  <thead>'
        . '    <tr>'
        . '      <th rowspan="2">区　分</th>'
        . '      <th colspan="2">所得税</th>'
        . '      <th colspan="2">住民税</th>'
        . '    </tr>'
        . '    <tr>'
        . '      <th>源泉徴収</th>'
        . '      <th>課税方式</th>'
        . '      <th>特別徴収</th>'
        . '      <th>課税方式</th>'
        . '    </tr>'
        . '  </thead>'
        . '  <tbody>'
        . '    <tr>'
        . '      <th>上　場</th>'
        . '      <td>15.315％</td>'
        . '      <td>総合課税<br>申告分離課税<br>申告不要</td>'
        . '      <td>5％<br>（配当割）</td>'
        . '      <td>総合課税<br>申告分離課税<br>申告不要</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <th>非上場</th>'
        . '      <td>20.42％</td>'
        . '      <td>総合課税<br>申告不要</td>'
        . '      <td>なし</td>'
        . '      <td>総合課税</td>'
        . '    </tr>'
        . '  </tbody>'
        . '</table>'
        . '<div class="help-text furu-help-line mb-2">　これらのうち、ここで入力するのは上場株式、非上場株式に関わらず全て総合課税方式を採用するケースです。総合課税方式というのは給与所得、事業所得、不動産所得などの所得と合算して税額を計算する方法です。</div>'
        . '<div class="help-text furu-help-line mb-2">　総合課税方式は他の総合課税所得がそれほど多くなく適用税率が低い場合に源泉徴収されている税額を還付請求する場合にメリットが発揮されます。</div>'
        . '<div class="help-text furu-help-line mb-2">　例えば課税所得金額が195万円以下の場合に適用される所得税率は５％（復興特別所得税率加算後は5.105％）なので配当所得加算後の課税所得金額が195万円以下であれば差額の10％（復興特別所得税率加算後は10.210％）が還付されます。それ以外に税額控除の一種である配当控除も適用できるため、より多くの税額が還付されます。</div>',

],


'kisokojo' => [
    'title' => '基礎控除',
    'html'  => ''
        . '<div class="help-text">　基礎控除は、基礎控除（原則部分）と基礎控除の特例（令和７年分以降、所得税のみ）からなり、納税者の合計所得金額に応じて所得金額から控除できます。</div>'
        . '<div class="help-text">　以下の基礎控除額は基礎控除（原則部分）と基礎控除の特例の合計です。<br>　なお住民税の基礎控除額は'
        . '<button type="button"'
        . ' class="btn btn-link p-0 align-baseline js-help-btn"'
        . ' data-help-key="jinteki_hikaku"'
        . ' style="font-size:inherit; line-height:inherit; vertical-align:baseline; text-decoration:underline;">所得税と住民税の人的控除額の比較</button>'
        . 'をご覧下さい。</div>'       
        . '<div class="ms-5 mb-1"><strong>○基礎控除額</strong></div>'
        . '<table class="help-tax-table mx-auto" style="width:520px;">'
        . '  <thead>'
        . '    <tr>'
        . '      <th colspan="2" rowspan="2">合計所得金額</th>'
        . '      <th colspan="2">合計所得金額</th>'
        . '      <th colspan="2">控除額</th>'
        . '    </tr>'
        . '    <tr>'
        . '      <th colspan="2" style="background:#F7F9FB;">給与収入のみの場合</th>'
        . '      <th style="background:#F7F9FB;" style="width:80px;">令和７・８年分</th>'
        . '      <th style="background:#F7F9FB;" style="width:80px;">令和９年分以降</th>'
        . '    </tr>'
        . '  </thead>'
        . '  <tbody>'
        . '    <tr>'
        . '      <td colspan="2" class="text-end nowrap">132万円以下</td>'
        . '      <td colspan="2" class="text-end nowrap">2,003,999円以下</td>'
        . '      <td colspan="2" class="text-center nowrap">95万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td class="text-start nowrap b-r-no" style="width:80px;">132万円超</td>'
        . '      <td class="text-end nowrap b-l-no" style="width:80px;">336万円以下</td>'
        . '      <td class="text-start nowrap b-r-no" style="width:100px;">2,003,999円超</td>'
        . '      <td class="text-end nowrap b-l-no" style="width:100px;">4,751,999円以下</td>'
        . '      <td class="text-center nowrap">88万円</td>'
        . '      <td rowspan="4" class="text-center nowrap">58万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td class="text-start nowrap b-r-no">336万円超</td>'
        . '      <td class="text-end nowrap b-l-no">489万円以下</td>'
        . '      <td class="text-start nowrap b-r-no">4,751,999円超</td>'
        . '      <td class="text-end nowrap b-l-no">6,655,556円以下</td>'
        . '      <td class="text-center nowrap">68万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td class="text-start nowrap b-r-no">489万円超</td>'
        . '      <td class="text-end nowrap b-l-no">655万円以下</td>'
        . '      <td class="text-start nowrap b-r-no">6,655,556円超</td>'
        . '      <td class="text-end nowrap b-l-no">8,500,000円以下</td>'
        . '      <td class="text-center nowrap">63万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td class="text-start nowrap b-r-no">655万円超</td>'
        . '      <td class="text-end nowrap b-l-no">2,350万円以下</td>'
        . '      <td class="text-start nowrap b-r-no">8,500,000円超</td>'
        . '      <td class="text-end nowrap b-l-no">25,450,000円以下</td>'
        . '      <td class="text-center nowrap">58万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td class="text-start nowrap b-r-no">2,350万円超</td>'
        . '      <td class="text-end nowrap b-l-no">2,400万円以下</td>'
        . '      <td class="text-start nowrap b-r-no">25,450,000円超</td>'
        . '      <td class="text-end nowrap b-l-no">25,950,000円以下</td>'
        . '      <td colspan="2" class="text-center nowrap">48万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td class="text-start nowrap b-r-no">2,400万円超</td>'
        . '      <td class="text-end nowrap b-l-no">2,450万円以下</td>'
        . '      <td class="text-start nowrap b-r-no">25,950,000円超</td>'
        . '      <td class="text-end nowrap b-l-no">26,450,000円以下</td>'
        . '      <td colspan="2" class="text-center nowrap">32万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td class="text-start nowrap b-r-no">2,450万円超</td>'
        . '      <td class="text-end nowrap b-l-no">2,500万円以下</td>'
        . '      <td class="text-start nowrap b-r-no">26,450,000円超</td>'
        . '      <td class="text-end nowrap b-l-no">26,950,000円以下</td>'
        . '      <td colspan="2" class="text-center nowrap">16万円</td>'
        . '    </tr>'
        . '    <tr>'
        . '      <td colspan="2" class="text-start nowrap">2,500万円超</td>'
        . '      <td colspan="2" class="text-start nowrap">26,950,000円超</td>'
        . '      <td colspan="2" class="text-center nowrap" style="background:#fff7da;">（適用なし）</td>'
        . '    </tr>'
        . '  </tbody>'
        . '</table>',
],

        'sakimono_torihiki' => [
            'title' => '先物取引',
            'html'  => ''
                . '<div class="help-text furu-help-line">　先物取引によって生じた所得について以前は総合課税所得に分類されていたこともあるのですが、今は事業所得、譲渡所得、雑所得のいずれに該当する場合でも一部の例外を除いて分離課税所得として課税されることとなりました。</div>'
                . '<div class="help-text furu-help-line">　税率は所得税15%、復興特別所得税0.315％、住民税5％です。なお損失が生じた場合は翌期以降３年間まで繰り越すことができますが、他の所得との損益通算はできません。</div>',
        ],
    
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
    
    
    
    'jinteki_hikaku' => [
        'title' => '所得税と住民税の人的控除額の比較',
        'html'  => ''
            /* =========================
             * 1段目
             * ========================= */
            . '<table class="help-tax-table mx-auto mb-0" style="width:520px;">'
            . '  <thead>'
            . '    <tr>'
            . '      <th colspan="4">区　分</th>'
            . '      <th style="width:70px;">所得税</th>'
            . '      <th style="width:70px;">住民税</th>'
            . '      <th style="width:70px;">差　額</th>'
            . '    </tr>'
            . '  </thead>'
            . '  <tbody>'
            . '    <tr>'
            . '      <th colspan="4" class="text-center nowrap" style="background:#e9eff7;">寡婦控除</th>'
            . '      <td class="text-center nowrap">27万円</td>'
            . '      <td class="text-center nowrap">26万円</td>'
            . '      <td class="text-center nowrap">1万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <th colspan="4" class="text-center nowrap" style="background:#e9eff7;">ひとり親控除</th>'
            . '      <td class="text-center nowrap">35万円</td>'
            . '      <td class="text-center nowrap">30万円</td>'
            . '      <td class="text-center nowrap">5万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <th colspan="4" class="text-center nowrap" style="background:#e9eff7;">勤労学生控除</th>'
            . '      <td class="text-center nowrap">27万円</td>'
            . '      <td class="text-center nowrap">26万円</td>'
            . '      <td class="text-center nowrap">1万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <th rowspan="3" colspan="2" class="text-center nowrap" style="background:#e9eff7;">障害者控除</th>'
            . '      <td colspan="2" class="text-center nowrap" style="background:#F7F9FB;">普通</td>'
            . '      <td class="text-center nowrap">27万円</td>'
            . '      <td class="text-center nowrap">26万円</td>'
            . '      <td class="text-center nowrap">1万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td colspan="2" class="text-center nowrap" style="background:#F7F9FB;">特別</td>'
            . '      <td class="text-center nowrap">40万円</td>'
            . '      <td class="text-center nowrap">30万円</td>'
            . '      <td class="text-center nowrap">10万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td colspan="2" class="text-center nowrap" style="background:#F7F9FB;">同居特別</td>'
            . '      <td class="text-center nowrap">75万円</td>'
            . '      <td class="text-center nowrap">53万円</td>'
            . '      <td class="text-center nowrap">22万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <th rowspan="6" class="text-center" style="width:30px;background:#e9eff7;">配<br>偶<br>者<br>控<br>除</th>'
            . '      <td rowspan="6" rowspan="2" class="text-center nowrap" style="width:100px;background:#F7F9FB;">納税者の<br>合計所得金額</td>'
            . '      <td rowspan="2" class="text-center nowrap" style="width:100px;background:#F7F9FB;">900万円以下</td>'
            . '      <td class="text-center nowrap" style="width:80px;">一般</td>'
            . '      <td class="text-center nowrap" style="width:70px;">38万円</td>'
            . '      <td class="text-center nowrap" style="width:70px;">33万円</td>'
            . '      <td class="text-center nowrap" style="width:70px;">5万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td class="text-center nowrap">老人</td>'
            . '      <td class="text-center nowrap">48万円</td>'
            . '      <td class="text-center nowrap">38万円</td>'
            . '      <td class="text-center nowrap">10万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td rowspan="2" class="text-center nowrap" style="background:#F7F9FB;">900万円超<br>950万円以下</td>'
            . '      <td class="text-center nowrap">一般</td>'
            . '      <td class="text-center nowrap">26万円</td>'
            . '      <td class="text-center nowrap">22万円</td>'
            . '      <td class="text-center nowrap">4万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td class="text-center nowrap">老人</td>'
            . '      <td class="text-center nowrap">32万円</td>'
            . '      <td class="text-center nowrap">26万円</td>'
            . '      <td class="text-center nowrap">6万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td rowspan="2" class="text-center nowrap" style="background:#F7F9FB;">950万円超<br>1,000万円以下</td>'
            . '      <td class="text-center nowrap">一般</td>'
            . '      <td class="text-center nowrap">13万円</td>'
            . '      <td class="text-center nowrap">11万円</td>'
            . '      <td class="text-center nowrap">2万円</td>'
            . '    </tr>'
            . '    <tr>'
            . '      <td class="text-center nowrap">老人</td>'
            . '      <td class="text-center nowrap">16万円</td>'
            . '      <td class="text-center nowrap">13万円</td>'
            . '      <td class="text-center nowrap">3万円</td>'
            . '    </tr>'

            . '  </tbody>'
            . '</table>'
            /* =========================
             * 2段目
             * ========================= */
            . '<table class="help-tax-table mx-auto mb-0" style="width:520px;">'
            . '  <tbody>'
            . '    <tr>'
            . '      <th rowspan="28" class="text-center nowrap" style="width:30px;background:#e9eff7;">配<br>偶<br>者<br>特<br>別<br>控<br>除</th>'
            . '      <th class="text-center nowrap" style="width:100px;background:#e9eff7;">納税者の<br>合計所得金額</th>'
            . '      <th class="text-center nowrap" style="width:180px;background:#e9eff7;">配偶者の合計所得金額</th>'
            . '      <th style="width:70px;background:#e9eff7;">所得税</th>'
            . '      <th style="width:70px;background:#e9eff7;">住民税</th>'
            . '      <th style="width:70px;background:#e9eff7;">差　額</th>'
            . '    </tr>'
            . '    <tr>'
            . '      <td rowspan="9" class="text-center nowrap" style="background:#F7F9FB;">900万円以下</td>'
            . '      <td class="text-center nowrap"> 48万円超　95万円以下</td>'
            . '      <td class="text-center nowrap">38万円</td>'
            . '      <td class="text-center nowrap">33万円</td>'
            . '      <td class="text-center nowrap">5万円</td>'
            . '    </tr>'
            . '    <tr><td class="text-center nowrap"> 95万円超　100万円以下</td><td class="text-center nowrap">36万円</td><td class="text-center nowrap">33万円</td><td class="text-center nowrap">3万円</td></tr>'
            . '    <tr><td class="text-center nowrap">100万円超　105万円以下</td><td class="text-center nowrap">31万円</td><td class="text-center nowrap">31万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">105万円超　110万円以下</td><td class="text-center nowrap">26万円</td><td class="text-center nowrap">26万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">110万円超　115万円以下</td><td class="text-center nowrap">21万円</td><td class="text-center nowrap">21万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">115万円超　120万円以下</td><td class="text-center nowrap">16万円</td><td class="text-center nowrap">16万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">120万円超　125万円以下</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">125万円超　130万円以下</td><td class="text-center nowrap">6万円</td><td class="text-center nowrap">6万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">130万円超　133万円以下</td><td class="text-center nowrap">3万円</td><td class="text-center nowrap">3万円</td><td class="text-center nowrap">－</td></tr>'

            . '    <tr>'
            . '      <td rowspan="9" class="text-center nowrap" style="background:#F7F9FB;">900万円超<br>950万円以下</td>'
            . '      <td class="text-center nowrap"> 48万円超　 95万円以下</td>'
            . '      <td class="text-center nowrap">26万円</td>'
            . '      <td class="text-center nowrap">22万円</td>'
            . '      <td class="text-center nowrap">4万円</td>'
            . '    </tr>'
            . '    <tr><td class="text-center nowrap"> 95万円超　100万円以下</td><td class="text-center nowrap">24万円</td><td class="text-center nowrap">22万円</td><td class="text-center nowrap">2万円</td></tr>'
            . '    <tr><td class="text-center nowrap">100万円超　105万円以下</td><td class="text-center nowrap">21万円</td><td class="text-center nowrap">21万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">105万円超　110万円以下</td><td class="text-center nowrap">18万円</td><td class="text-center nowrap">18万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">110万円超　115万円以下</td><td class="text-center nowrap">14万円</td><td class="text-center nowrap">14万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">115万円超　120万円以下</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">120万円超　125万円以下</td><td class="text-center nowrap">8万円</td><td class="text-center nowrap">8万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">125万円超　130万円以下</td><td class="text-center nowrap">4万円</td><td class="text-center nowrap">4万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">130万円超　133万円以下</td><td class="text-center nowrap">2万円</td><td class="text-center nowrap">2万円</td><td class="text-center nowrap">－</td></tr>'

            . '    <tr>'
            . '      <td rowspan="9" class="text-center nowrap" style="background:#F7F9FB;">950万円超<br>1,000万円以下</td>'
            . '      <td class="text-center nowrap"> 48万円超　 95万円以下</td>'
            . '      <td class="text-center nowrap">13万円</td>'
            . '      <td class="text-center nowrap">11万円</td>'
            . '      <td class="text-center nowrap">2万円</td>'
            . '    </tr>'
            . '    <tr><td class="text-center nowrap"> 95万円超　100万円以下</td><td class="text-center nowrap">12万円</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">1万円</td></tr>'
            . '    <tr><td class="text-center nowrap">100万円超　105万円以下</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">105万円超　110万円以下</td><td class="text-center nowrap">9万円</td><td class="text-center nowrap">9万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">110万円超　115万円以下</td><td class="text-center nowrap">7万円</td><td class="text-center nowrap">7万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">115万円超　120万円以下</td><td class="text-center nowrap">6万円</td><td class="text-center nowrap">6万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">120万円超　125万円以下</td><td class="text-center nowrap">4万円</td><td class="text-center nowrap">4万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">125万円超　130万円以下</td><td class="text-center nowrap">2万円</td><td class="text-center nowrap">2万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td class="text-center nowrap">130万円超　133万円以下</td><td class="text-center nowrap">1万円</td><td class="text-center nowrap">1万円</td><td class="text-center nowrap">－</td></tr>'
            . '  </tbody>'
            . '</table>'
            /* =========================
             * 3段目
             * ========================= */
            . '<table class="help-tax-table mx-auto" style="width:520px;">'
            . '  <tbody>'
            . '    <tr>'
            . '      <th rowspan="4" colspan="3" class="text-center nowrap" style="width:210px; background:#e9eff7;">扶養控除</th>'
            . '      <td style="width:100px;">一般</td>'
            . '      <td class="text-center nowrap" style="width:70px;">38万円</td>'
            . '      <td class="text-center nowrap" style="width:70px;">33万円</td>'
            . '      <td class="text-center nowrap" style="width:70px;">5万円</td>'
            . '    </tr>'
            . '    <tr><td class="text-center nowrap">特定</td><td class="text-center nowrap">63万円</td><td class="text-center nowrap">45万円</td><td class="text-center nowrap">18万円</td></tr>'
            . '    <tr><td class="text-center nowrap">老人</td><td class="text-center nowrap">48万円</td><td class="text-center nowrap">38万円</td><td class="text-center nowrap">10万円</td></tr>'
            . '    <tr><td class="text-center nowrap">同居老親等</td><td class="text-center nowrap">58万円</td><td class="text-center nowrap">45万円</td><td class="text-center nowrap">13万円</td></tr>'
            . '    <tr>'
            . '      <th rowspan="9" class="text-center nowrap" style="width:100px; background:#e9eff7;">特定親族<br>特別控除</th>'
            . '      <td rowspan="9" class="text-center nowrap" style="width:30px;background:#F7F9FB;"">特<br>定<br>親<br>族<br>の<br>合<br>計<br>所<br>得<br>金<br>額</td>'
            . '      <td colspan="2" class="text-center nowrap" style="width:180px;"> 58万円超　 85万円以下</td>'
            . '      <td class="text-center nowrap">63万円</td>'
            . '      <td rowspan="3" class="text-center nowrap">45万円</td>'
            . '      <td class="text-center nowrap">18万円</td>'
            . '    </tr>'
            . '    <tr><td colspan="2" class="text-center nowrap"> 85万円超　 90万円以下</td><td class="text-center nowrap">61万円</td><td class="text-center nowrap">16万円</td></tr>'
            . '    <tr><td colspan="2" class="text-center nowrap"> 90万円超　 95万円以下</td><td class="text-center nowrap">51万円</td><td class="text-center nowrap">6万円</td></tr>'
            . '    <tr><td colspan="2" class="text-center nowrap"> 95万円超　100万円以下</td><td class="text-center nowrap">41万円</td><td class="text-center nowrap">41万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td colspan="2" class="text-center nowrap">100万円超　105万円以下</td><td class="text-center nowrap">31万円</td><td class="text-center nowrap">31万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td colspan="2" class="text-center nowrap">105万円超　110万円以下</td><td class="text-center nowrap">21万円</td><td class="text-center nowrap">21万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td colspan="2" class="text-center nowrap">110万円超　115万円以下</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">11万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td colspan="2" class="text-center nowrap">115万円超　120万円以下</td><td class="text-center nowrap">6万円</td><td class="text-center nowrap">6万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr><td colspan="2" class="text-center nowrap">120万円超　123万円以下</td><td class="text-center nowrap">3万円</td><td class="text-center nowrap">3万円</td><td class="text-center nowrap">－</td></tr>'
            . '    <tr>'
            . '      <th rowspan="5" class="text-center nowrap" style="background:#e9eff7;">基礎控除(※)</th>'
            . '      <td rowspan="5" class="text-center nowrap" style="background:#F7F9FB;">合<br>計<br>所<br>得<br>金<br>額</td>'
            . '      <td colspan="2" class="text-end pe-1 nowrap">2,350万円以下</td>'
            . '      <td class="text-center nowrap">58万円</td>'
            . '      <td rowspan="2" class="text-center nowrap">43万円</td>'
            . '      <td class="text-center nowrap">15万円</td>'
            . '    </tr>'
            . '      <td colspan="2" class="text-center nowrap">2,350万円超　2,400万円以下</td>'
            . '      <td class="text-center nowrap">48万円</td>'
            . '      <td class="text-center nowrap">5万円</td>'
            . '    </tr>'
            . '    <tr><td colspan="2" class="text-center nowrap">2,400万円超　2,450万円以下</td><td class="text-center nowrap">32万円</td><td class="text-center nowrap">29万円</td><td class="text-center nowrap">3万円</td></tr>'
            . '    <tr><td colspan="2" class="text-center nowrap">2,450万円超　2,500万円以下</td><td class="text-center nowrap">16万円</td><td class="text-center nowrap">15万円</td><td class="text-center nowrap">1万円</td></tr>'
            . '    <tr><td colspan="2" class="text-start ps-1 nowrap">2,500万円超</td><td class="text-center nowrap">適用なし</td><td class="text-center nowrap">適用なし</td><td class="text-center nowrap">－</td></tr>'
            . '  </tbody>'
            . '</table>'
            . '<div class="help-text ms-3 mt-1">※基礎控除に関する令７・８年分特例分は含んでいません。</div>',
    ],
    'bunri_joto_tansho_choki' => [
        'title' => '分離譲渡（短期・長期）',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-3">　分離譲渡とはその名のとおり他の所得とは分離して単独で課税される譲渡所得のことです。</div>'
            . '<div class="furu-help-head mb-2">○具体例</div>'
            . '<div class="help-text furu-help-line mb-2">　土地や建物などの譲渡から生ずる所得</div>'
            . '<div class="help-text furu-help-note-hanging mb-1">※「譲渡所得の内訳書（確定申告書付表兼計算明細書）【土地・建物用】」で計算し、確定申告書と一緒に提出します。申告書（第一表・第二表）と分離用（第三表）等を使用します。</div>'
            . '<div class="help-text furu-help-body mb-3"><u>なお、このソフトではふるさと納税の計算の仕組みを理解しやすいように第一表と第三表を一緒にした方法を採用しています。所得税や住民税の税理士試験でもこの方式を採用しています。</u></div>'
            . '<div class="furu-help-head mb-2">○短期と長期の区分</div>'
            . '<div class="help-text furu-help-body mb-1">　短期譲渡：譲渡した年の１月１日現在で所有期間が５年以内のもの</div>'
            . '<div class="help-text furu-help-body mb-2">　長期譲渡：譲渡した年の１月１日現在で所有期間が５年超のもの</div>'
            . '<div class="help-text furu-help-note-hanging mb-4">※総合課税の譲渡所得は、取得した時から売った時までの保有期間によって判定しますのでご注意下さい。</div>'
            . '<div class="furu-help-head mb-2">○計算方法</div>'
            . '<div class="help-text furu-help-body mb-4">　　譲渡所得の金額 ＝ 収入金額 － 必要経費 － 特別控除額</div>'
            . '<div class="furu-help-head mb-2">○税率</div>'
            . '<div class="help-text furu-help-body mb-2"><u>いずれも所得税について復興特別所得税が2.1%あります。</u></div>'
            . '<table class="table-base mx-auto mb-1" style="width:460px;">'
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
            . '<div class="help-text furu-help-note-hanging ms-20 mb-1">(注1)譲渡先が国、地方公共団体等</div>'
            . '<div class="help-text furu-help-note-hanging ms-20 mb-1">(注2)譲渡先が国、地方公共団体等＋優良住宅地等のための譲渡</div>'
            . '<div class="help-text furu-help-note-hanging ms-20">(注3)譲渡した年の１月１日現在で所有期間が10年を超える居住用財産の譲渡</div>',
     ],

    'bunri_kabuteki' => [
        'title' => '株式等の譲渡所得等',
        'html' => ''
            . '<div class="help-text furu-help-line mb-4">　ここでは一般株式等の譲渡、上場株式等の譲渡、上場株式等の配当等についてまとめて入力します。</div>'
            . '<div class="furu-help-head mb-2">○入力に当たっての注意点</div>'
            . '<div class="help-text furu-help-item mb-1">①一般株式等の譲渡損失</div>'
            . '<div class="help-text furu-help-body mb-0">　　上場株式等の譲渡益や上場株式等の配当等とは損益通算できない。その損失はその年度で<br>　　打ち切りとなる。ただし非上場株式であっても特定中小会社株式等（いわゆるエンジェル税制等）<br>　　は除く。</div>'
            . '<div class="help-text furu-help-item mb-1">②上場株式等の譲渡損失</div>'
            . '<div class="help-text furu-help-body mb-4">　　上場株式等の配当等と損益通算できる（ただし申告分離課税を選択した配当所得に限る）し、<br>　　控除できない額は翌期以降３年間まで繰越可能。</div>'
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