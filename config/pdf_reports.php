<?php

return [
    // レポートキー => 実装クラス
    // /pdf/{キー} で呼び出し
    'bunri' => \App\Reports\Shinkokusyo\ShinkokusyoBunriReport::class,
    'shinkokusyo_bunri' => \App\Reports\Shinkokusyo\ShinkokusyoBunriReport::class,
    // 人的控除差調整額
    'jintekikojosatyosei' => \App\Reports\Jinteki\JintekikojosatyoseiReport::class,
];