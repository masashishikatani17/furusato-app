<?php

return [
    // レポートキー => 実装クラス
    // /pdf/{キー} で呼び出し
    'bunri' => \App\Reports\Shinkokusyo\ShinkokusyoBunriReport::class,
    // 0〜7帳票の一括ダウンロード（4は当年度ワンストップにより分岐）
    'furusato_bundle' => \App\Reports\Furusato\FurusatoBundleReport::class,
    // 分離申告書
    'shinkokusyo_bunri' => \App\Reports\Shinkokusyo\ShinkokusyoBunriReport::class,
    // 人的控除差調整額
    'jintekikojosatyosei' => \App\Reports\Jinteki\JintekikojosatyoseiReport::class,
    // 特例控除割合
    'tokureikojowariai' => \App\Reports\Tokurei\TokureiKojowariaiReport::class,
    // 表紙（Bladeは 0_ だが URLキーは数字なし）
    'hyoshi' => \App\Reports\Cover\HyoshiReport::class,
    // 寄附金限度額（Bladeは 1_ だが URLキーは数字なし）
    'kifukingendogaku' => \App\Reports\Kifukin\KifukinGendogakuReport::class,
    // 所得金額・所得控除額の予測（Bladeは 2_ だが URLキーは数字なし）
    'syotokukinkojyosoku' => \App\Reports\Shotoku\SyotokukinKojyosokuReport::class,
    // 課税所得金額・税額の予測（Bladeは 3_ だが URLキーは数字なし）
    'kazeigakuzeigakuyosoku' => \App\Reports\Kazei\KazeigakuZeigakuYosokuReport::class,
    // 住民税の軽減額（Bladeは 4_ だが URLキーは数字なし）
    'juminkeigengaku' => \App\Reports\Jumin\JuminKeigengakuReport::class,
    // 住民税の軽減額（ワンストップ特例）
    'juminkeigengaku_onestop' => \App\Reports\Jumin\JuminKeigengakuOnestopReport::class,
    // ======== 「今までに寄付した額」用（2〜4ページだけ差し替え） ========
    'syotokukinkojyosoku_curr' => \App\Reports\Shotoku\SyotokukinKojyosokuReport::class,
    'kazeigakuzeigakuyosoku_curr' => \App\Reports\Kazei\KazeigakuZeigakuYosokuReport::class,
    'juminkeigengaku_curr' => \App\Reports\Jumin\JuminKeigengakuReport::class,
    'juminkeigengaku_onestop_curr' => \App\Reports\Jumin\JuminKeigengakuOnestopReport::class,
    // 寄附金額別損得シミュレーション（Bladeは 5_ だが URLキーは数字なし）
    'sonntokusimulation' => \App\Reports\Simulation\SonntokuSimulationReport::class,
    
 ];
