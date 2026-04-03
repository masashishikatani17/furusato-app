<?php

/**
 * joto_ichiji_details 専用 HELP（総合譲渡＋一時）
 * key はボタンの data-help-key と一致させる
 *
 * 記法：
 * - 行頭 "○" は自動で太字＋色 (#192C4B)
 * - 行頭 "(1)" などは自動で太字（色そのまま）
 * - "__下線__" は下線に変換
 */
return [
    // ------------------------
    // HELP総合譲渡（短期・長期）
    // ------------------------
    'help_sogo_joto' => [
        'title' => '総合譲渡（短期・長期）',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-4">　総合譲渡とは総合課税の譲渡所得のことです。事業所得や給与所得等と合算して税額を計算する仕組みになっています。</div>'
            . '<div class="furu-help-head mb-2">○具体例</div>'
            . '<div class="help-text furu-help-line mb-4">　ゴルフ会員権や金地金、船舶、機械、特許権、漁業権、書画、骨とう、貴金属などの資産の譲渡から生ずる所得</div>'
            . '<div class="help-text furu-help-note-hanging mb-1">※「譲渡所得の内訳書（確定申告書付表）【総合譲渡用】」で計算し、確定申告書と一緒に提出します。</div>'
            . '<div class="help-text furu-help-note-hanging mb-2">※土地や建物、借地権、株式等の譲渡から生じる所得は申告分離課税となります。この場合は申告書（第一表・第二表）と分離用（第三表）等を使用します。</div>'
            . '<div class="help-text furu-help-body mb-0"><u>なお、このソフトではふるさと納税の計算の仕組みを理解しやすいように第一表と第三表を一緒にした方式を採用しています。</u></div>'
            . '<div class="help-text furu-help-body mb-4"><u>所得税や住民税の税理士試験でもこの方式を採用しています。</u></div>'
            . '<div class="furu-help-head mb-2">○短期と長期の区分</div>'
            . '<div class="help-text furu-help-body mb-1">　短期譲渡：譲渡した資産の保有期間が５年以内のもの</div>'
            . '<div class="help-text furu-help-body mb-2">　長期譲渡：保有期間が５年を超えるもの</div>'
            . '<div class="help-text furu-help-note-hanging mb-4">※総合課税の譲渡所得は、取得した時から売った時までの所有期間によって長期と短期の二つに分かれます。分離課税の譲渡所得は譲渡した年の１月１日現在で所有期間を判定しますのでご注意下さい。</div>'
            . '<div class="furu-help-head mb-2">○計算方法</div>'
            . '<div class="help-text furu-help-body mb-2">　譲渡所得の金額 ＝ 収入金額 － 必要経費 － 50万円（注）</div>'
            . '<div class="help-text furu-help-note-hanging mb-2">※総合課税の譲渡所得の金額は上記のように計算し、短期譲渡所得の金額は全額が総合課税の対象になりますが、長期譲渡所得の金額はその２分の１が総合課税の対象になります。</div>'
            . '<div class="help-text furu-help-note-hanging">(注)譲渡所得の特別控除の額は、その年の長期の譲渡益と短期の譲渡益の合計額に対して50万円です。その年に短期と長期の譲渡益があるときは、先に短期の譲渡益から特別控除の50万円を差し引きます。なお、譲渡益の合計額が50万円以下のときは、その金額までしか控除できません。</div>',
     ],

    // ------------------------
    // HELP一時所得
    // ------------------------
    'help_ichiji' => [
        'title' => '一時所得',
        'html'  => ''
            . '<div class="help-text furu-help-line mb-4">一時所得とは臨時・偶発的な所得のことです。</div>'
            . '<div class="furu-help-head">○具体例</div>'
            . '<div class="help-text furu-help-line mb-4">　懸賞や福引の賞金・賞品、競馬・競輪などの払戻金、生命保険の一時金、損害保険の満期返戻金等、遺失物拾得者や埋蔵物発見者の報労金など</div>'
            . '<div class="furu-help-head">○計算方法</div>'
            . '<div class="help-text furu-help-body mb-2">　一時所得の金額 ＝ 収入金額 － 必要経費 － 50万円</div>'
            . '<div class="help-text furu-help-note-hanging mb-4">※一時所得は上記で計算した金額の２分の１が総合課税の対象になります。</div>'
            . '<div class="help-text furu-help-star"><span class="furu-help-star-label"><u>総合課税所得内での損益通算</u></span></div>'
            . '<div class="help-text furu-help-body">　このシステムでは総合課税所得内での損益通算に対応しています。余りにも複雑なので言葉での説明は省略しますが、ソフトの上欄にある「計算結果詳細」のタブをクリックしますと損益通算の状況が画面に表示されますので間違いがないか確認して下さい。</div>',
     ],
];